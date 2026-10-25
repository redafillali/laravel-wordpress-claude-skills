# Import pipeline skeleton (Laravel)

## 1. Second DB connection

`config/database.php`:

```php
'wordpress' => [
    'driver' => 'mysql',
    'host' => env('WP_DB_HOST', '127.0.0.1'),
    'port' => env('WP_DB_PORT', '3306'),
    'database' => env('WP_DB_DATABASE'),
    'username' => env('WP_DB_USERNAME'),
    'password' => env('WP_DB_PASSWORD'),
    'charset' => 'utf8mb4',
    'collation' => 'utf8mb4_unicode_ci',
    'prefix' => env('WP_DB_PREFIX', 'wp_'),
],
```

Point it at a **copy** of the production WordPress database.

## 2. Migration with wp_id and SEO fields

```php
Schema::create('posts', function (Blueprint $table) {
    $table->id();
    $table->unsignedBigInteger('wp_id')->nullable()->unique();
    $table->foreignId('author_id')->nullable()->constrained('users')->nullOnDelete();
    $table->string('title');
    $table->string('slug')->unique();
    $table->longText('body')->nullable();
    $table->text('excerpt')->nullable();
    $table->foreignId('featured_media_id')->nullable()->constrained('media')->nullOnDelete();
    $table->string('status')->default('draft')->index();
    $table->timestamp('published_at')->nullable()->index();
    $table->string('meta_title')->nullable();
    $table->string('meta_description', 320)->nullable();
    $table->string('canonical_url')->nullable();
    $table->boolean('noindex')->default(false);
    $table->string('old_url')->nullable()->index();
    $table->timestamps();
});

Schema::create('redirects', function (Blueprint $table) {
    $table->id();
    $table->string('from_path')->unique();   // normalized: leading slash, no domain, lowercase
    $table->string('to_url');
    $table->unsignedSmallInteger('status_code')->default(301);
    $table->unsignedInteger('hits')->default(0);
    $table->timestamps();
});
```

## 3. Artisan command (idempotent upsert)

```php
namespace App\Console\Commands;

use App\Models\Post;
use App\Support\WpContentCleaner;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class ImportWpPosts extends Command
{
    protected $signature = 'wp:import-posts {--type=post} {--since= : only posts modified after this date (delta)}';
    protected $description = 'Import WordPress posts into the posts table (re-runnable)';

    public function handle(WpContentCleaner $cleaner): int
    {
        $wp = DB::connection('wordpress');

        $query = $wp->table('posts')
            ->where('post_type', $this->option('type'))
            ->whereIn('post_status', ['publish', 'draft', 'future', 'private'])
            ->orderBy('ID');

        if ($since = $this->option('since')) {
            $query->where('post_modified_gmt', '>=', $since);
        }

        $bar = $this->output->createProgressBar((clone $query)->count());
        $imported = 0;

        $query->chunkById(200, function ($rows) use ($wp, $cleaner, $bar, &$imported) {
            $meta = $wp->table('postmeta')
                ->whereIn('post_id', $rows->pluck('ID'))
                ->whereIn('meta_key', ['_yoast_wpseo_title', '_yoast_wpseo_metadesc', '_yoast_wpseo_canonical', '_thumbnail_id'])
                ->get()
                ->groupBy('post_id');

            foreach ($rows as $row) {
                $m = ($meta[$row->ID] ?? collect())->pluck('meta_value', 'meta_key');

                Post::updateOrCreate(
                    ['wp_id' => $row->ID],
                    [
                        'title' => html_entity_decode($row->post_title, ENT_QUOTES),
                        'slug' => $row->post_name ?: 'post-'.$row->ID,
                        'body' => $cleaner->clean($row->post_content),
                        'excerpt' => $row->post_excerpt ?: null,
                        'status' => $row->post_status === 'publish' ? 'published' : 'draft',
                        'published_at' => $row->post_date_gmt !== '0000-00-00 00:00:00' ? $row->post_date_gmt : null,
                        'meta_title' => $m['_yoast_wpseo_title'] ?? null,
                        'meta_description' => $m['_yoast_wpseo_metadesc'] ?? null,
                        'canonical_url' => $m['_yoast_wpseo_canonical'] ?? null,
                        // featured_media_id resolved after media import via wp_id lookup
                    ]
                );
                $imported++;
                $bar->advance();
            }
        }, 'ID');

        $bar->finish();
        $this->newLine();
        $this->info("Imported/updated {$imported} {$this->option('type')} rows.");

        return self::SUCCESS;
    }
}
```

Run order: `wp:import-users` → `wp:import-terms` → `wp:import-media` → `wp:import-posts --type=post` → `--type=page` → `wp:import-relations` → `wp:import-redirects`. Wrap them in a `wp:import-all` command for staging rehearsals.

## 4. Redirect middleware

```php
class HandleRedirects
{
    public function handle(Request $request, Closure $next)
    {
        $response = $next($request);

        if ($response->getStatusCode() === 404) {
            $path = '/'.trim(strtolower($request->getPathInfo()), '/');
            $redirect = Redirect::where('from_path', $path)
                ->orWhere('from_path', $path.'/')
                ->first();

            if ($redirect) {
                $redirect->increment('hits');
                return redirect()->to($redirect->to_url, $redirect->status_code);
            }
        }

        return $response;
    }
}
```

Checking only on 404 keeps the hot path fast. Cache the redirect map if it is large, or export it to Nginx `map` rules for very high traffic.

## 5. URL verification command

Feed it the list of old URLs (Search Console export + crawl) and assert each returns 200, or a single 301 hop to a 200 — report chains, loops and 404s as CSV.
