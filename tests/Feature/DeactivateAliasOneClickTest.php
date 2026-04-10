<?php

namespace Tests\Feature;

use App\Models\Alias;
use App\Notifications\AliasDeactivatedByUnsubscribeNotification;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Routing\Middleware\ThrottleRequestsWithRedis;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\URL;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class DeactivateAliasOneClickTest extends TestCase
{
    use LazilyRefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->withoutMiddleware(ThrottleRequestsWithRedis::class);
    }

    #[Test]
    public function signed_post_deactivates_alias_and_sends_notification()
    {
        Notification::fake();

        $user = $this->createUser('johndoe');

        $alias = Alias::factory()->create([
            'user_id' => $user->id,
            'active' => true,
        ]);

        $url = URL::signedRoute('deactivate_post', ['alias' => $alias->id]);

        $response = $this->post($url);

        $response->assertStatus(200);
        $this->assertFalse($alias->refresh()->active);

        Notification::assertSentTo($user, AliasDeactivatedByUnsubscribeNotification::class);
    }

    #[Test]
    public function already_inactive_alias_does_not_send_notification()
    {
        Notification::fake();

        $user = $this->createUser('johndoe');

        $alias = Alias::factory()->create([
            'user_id' => $user->id,
            'active' => false,
        ]);

        $url = URL::signedRoute('deactivate_post', ['alias' => $alias->id]);

        $response = $this->post($url);

        $response->assertStatus(200);
        $this->assertFalse($alias->refresh()->active);

        Notification::assertNotSentTo($user, AliasDeactivatedByUnsubscribeNotification::class);
    }

    #[Test]
    public function duplicate_requests_only_send_one_notification()
    {
        Notification::fake();

        $user = $this->createUser('johndoe');

        $alias = Alias::factory()->create([
            'user_id' => $user->id,
            'active' => true,
        ]);

        $url = URL::signedRoute('deactivate_post', ['alias' => $alias->id]);

        $this->post($url);
        $this->assertFalse($alias->refresh()->active);

        $alias->update(['active' => true]);
        $this->post($url);

        Notification::assertSentToTimes($user, AliasDeactivatedByUnsubscribeNotification::class, 1);
        $this->assertTrue(Cache::has("unsubscribe-deactivate-notify:{$alias->id}"));
    }

    #[Test]
    public function invalid_signature_returns_403()
    {
        $user = $this->createUser('johndoe');

        $alias = Alias::factory()->create([
            'user_id' => $user->id,
            'active' => true,
        ]);

        $response = $this->post(route('deactivate_post', ['alias' => $alias->id]));

        $response->assertStatus(403);
        $this->assertTrue($alias->refresh()->active);
    }

    #[Test]
    public function non_existent_alias_returns_404()
    {
        $url = URL::signedRoute('deactivate_post', ['alias' => '00000000-0000-0000-0000-000000000000']);

        $response = $this->post($url);

        $response->assertStatus(404);
    }
}
