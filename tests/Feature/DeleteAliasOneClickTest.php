<?php

namespace Tests\Feature;

use App\Models\Alias;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Routing\Middleware\ThrottleRequestsWithRedis;
use Illuminate\Support\Facades\URL;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class DeleteAliasOneClickTest extends TestCase
{
    use LazilyRefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        // One-Click routes are rate-limited; disable throttling so assertions stay deterministic.
        $this->withoutMiddleware(ThrottleRequestsWithRedis::class);
    }

    #[Test]
    public function signed_post_deletes_alias_and_returns_empty_response()
    {
        $user = $this->createUser('johndoe');

        $alias = Alias::factory()->create([
            'user_id' => $user->id,
            'active' => true,
        ]);

        $this->assertNull($alias->deleted_at);

        $url = URL::signedRoute('delete_post', ['alias' => $alias->id]);

        $response = $this->post($url);

        $response->assertStatus(200);
        $response->assertSee('');

        $alias->refresh();
        $this->assertNotNull($alias->deleted_at);
    }

    #[Test]
    public function invalid_signature_returns_403()
    {
        $user = $this->createUser('johndoe');
        $alias = Alias::factory()->create(['user_id' => $user->id]);

        $response = $this->post(route('delete_post', ['alias' => $alias->id]));

        $response->assertStatus(403);

        $this->assertNull($alias->fresh()->deleted_at);
    }

    #[Test]
    public function non_existent_alias_returns_404()
    {
        $url = URL::signedRoute('delete_post', ['alias' => '00000000-0000-0000-0000-000000000000']);

        $response = $this->post($url);

        $response->assertStatus(404);
    }
}
