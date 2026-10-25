# Laravel SEO snippets

## Layout head (Blade)

```blade
{{-- resources/views/layouts/app.blade.php --}}
@php($seo = $seo ?? \App\Support\Seo::defaults())
<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{{ $seo->title }}</title>
    <meta name="description" content="{{ $seo->description }}">
    <link rel="canonical" href="{{ $seo->canonical }}">
    @if($seo->noindex)
        <meta name="robots" content="noindex, follow">
    @endif

    @foreach($seo->alternates as $locale => $url)
        <link rel="alternate" hreflang="{{ $locale }}" href="{{ $url }}">
    @endforeach

    <meta property="og:type" content="{{ $seo->ogType }}">
    <meta property="og:title" content="{{ $seo->title }}">
    <meta property="og:description" content="{{ $seo->description }}">
    <meta property="og:url" content="{{ $seo->canonical }}">
    @if($seo->image)<meta property="og:image" content="{{ $seo->image }}">@endif
    <meta name="twitter:card" content="summary_large_image">

    @foreach($seo->jsonLd as $schema)
        <script type="application/ld+json">{!! json_encode($schema, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_HEX_TAG) !!}</script>
    @endforeach
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
```

`JSON_HEX_TAG` prevents `</script>` injection from user content inside JSON-LD.

## Seo value object (minimal)

```php
namespace App\Support;

final class Seo
{
    public function __construct(
        public string $title,
        public string $description = '',
        public ?string $canonical = null,
        public ?string $image = null,
        public bool $noindex = false,
        public string $ogType = 'website',
        public array $alternates = [],
        public array $jsonLd = [],
    ) {
        $this->canonical ??= url()->current(); // without query string
        $this->description = str($this->description)->stripTags()->squish()->limit(160, '…')->toString();
    }

    public static function defaults(): self
    {
        return new self(title: config('app.name'));
    }
}
```

Controller: `return view('posts.show', ['post' => $post, 'seo' => new Seo(title: $post->meta_title ?: $post->title.' | '.config('app.name'), description: $post->meta_description ?: $post->excerpt, image: $post->featured_image_url, ogType: 'article', jsonLd: [PostSchema::for($post), Breadcrumbs::for($post)])]);`

## JSON-LD examples

```php
// BlogPosting
[
    '@context' => 'https://schema.org',
    '@type' => 'BlogPosting',
    'headline' => $post->title,
    'datePublished' => $post->published_at->toAtomString(),
    'dateModified' => $post->updated_at->toAtomString(),
    'author' => ['@type' => 'Person', 'name' => $post->author->name],
    'image' => $post->featured_image_url,
    'mainEntityOfPage' => route('posts.show', $post),
];

// BreadcrumbList
[
    '@context' => 'https://schema.org',
    '@type' => 'BreadcrumbList',
    'itemListElement' => collect($crumbs)->values()->map(fn ($c, $i) => [
        '@type' => 'ListItem', 'position' => $i + 1, 'name' => $c['name'], 'item' => $c['url'],
    ])->all(),
];

// Organization / LocalBusiness (homepage)
[
    '@context' => 'https://schema.org',
    '@type' => 'Organization',   // or LocalBusiness / EducationalOrganization
    'name' => 'Company',
    'url' => 'https://example.com',
    'logo' => 'https://example.com/logo.png',
    'sameAs' => ['https://www.linkedin.com/company/...'],
];
```

## Host & trailing slash normalization (middleware)

```php
class CanonicalHost
{
    public function handle(Request $request, Closure $next)
    {
        $canonicalHost = parse_url(config('app.url'), PHP_URL_HOST);
        if (app()->isProduction() && ($request->getHost() !== $canonicalHost || ! $request->secure())) {
            return redirect()->to('https://'.$canonicalHost.$request->getRequestUri(), 301);
        }
        return $next($request);
    }
}
```

Prefer doing this at the web-server level (Nginx `return 301`) when possible — it's cheaper.

## Sitemap (spatie/laravel-sitemap)

```php
// app/Console/Commands/GenerateSitemap.php  → schedule daily
Sitemap::create()
    ->add(Url::create('/')->setPriority(1.0))
    ->add(Post::published()->get())        // Post implements Sitemapable
    ->writeToFile(public_path('sitemap.xml'));
```

```php
// Post model
public function toSitemapTag(): Url|string|array
{
    return Url::create(route('posts.show', $this))->setLastModificationDate($this->updated_at);
}
```

robots.txt (`public/robots.txt`, or a route serving env-specific content):

```
User-agent: *
Disallow: /admin
Disallow: /search
Sitemap: https://example.com/sitemap.xml
```

On staging, serve `Disallow: /` **and** `X-Robots-Tag: noindex` — and make sure neither reaches production.
