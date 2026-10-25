# Testing Laravel apps

## Which test to write

| Situation | Test type |
|---|---|
| Route / controller / full flow | Feature test (`$this->get()`, `postJson()`) |
| Pure logic (Action, value object, calculation) | Unit test |
| Job / listener behaviour | Feature test calling `handle()` or dispatching with `Queue::fake()` assertions |
| Artisan command | `$this->artisan('cmd')->expectsOutput()->assertSuccessful()` |
| Browser JS behaviour | Laravel Dusk or Pest browser plugin (only when needed) |

## Pest example (feature)

```php
use App\Models\Post;
use App\Models\User;

it('lets an author update their own post', function () {
    $user = User::factory()->create();
    $post = Post::factory()->for($user, 'author')->create();

    $this->actingAs($user)
        ->put(route('posts.update', $post), ['title' => 'New title', 'body' => 'Body'])
        ->assertRedirect(route('posts.show', $post));

    expect($post->fresh()->title)->toBe('New title');
});

it('forbids updating someone else\'s post', function () {
    $post = Post::factory()->create();

    $this->actingAs(User::factory()->create())
        ->put(route('posts.update', $post), ['title' => 'Hack'])
        ->assertForbidden();
});

it('validates the title', function () {
    $post = Post::factory()->create();

    $this->actingAs($post->author)
        ->put(route('posts.update', $post), ['title' => ''])
        ->assertSessionHasErrors('title');
});
```

Pest files rely on `uses(Tests\TestCase::class, RefreshDatabase::class)->in('Feature');` in `tests/Pest.php`.

## PHPUnit equivalent

```php
final class UpdatePostTest extends TestCase
{
    use RefreshDatabase;

    public function test_author_can_update_post(): void
    {
        $post = Post::factory()->create();

        $this->actingAs($post->author)
            ->putJson("/api/posts/{$post->id}", ['title' => 'New', 'body' => 'B'])
            ->assertOk()
            ->assertJsonPath('data.title', 'New');
    }
}
```

## Fakes

```php
Mail::fake();      Mail::assertQueued(InvoiceMail::class, fn ($m) => $m->hasTo($user->email));
Queue::fake();     Queue::assertPushed(ProcessImport::class);
Http::fake(['api.stripe.com/*' => Http::response(['id' => 'ch_1'], 200)]);
Http::preventStrayRequests();
Storage::fake('public');  UploadedFile::fake()->image('a.jpg');
Notification::fake();     Event::fake([OrderShipped::class]);
$this->travelTo(now()->addDays(3));   // time
```

## Factories

- One factory per model; use **states** for variants (`->unpaid()`, `->admin()`), `for()` / `has()` for relations, `sequence()` for alternating data.
- Keep factories minimal and valid; tests set only the attributes they care about.

## Tips

- `LazilyRefreshDatabase` speeds suites that have many tests not touching the DB.
- Use an in-memory SQLite DB only if the app doesn't rely on MySQL/PostgreSQL specifics (JSON ops, full-text); otherwise test on the real engine (CI service container).
- Run in parallel: `php artisan test --parallel`.
- Coverage of authorization and validation paths matters more than a coverage percentage.
