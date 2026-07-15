<?php

namespace Tests\Feature\Api;

use App\Models\Recipient;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class ActiveRecipientsTest extends TestCase
{
    use LazilyRefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        parent::setUpSanctum();
    }

    #[Test]
    public function user_can_activate_recipient(): void
    {
        $recipient = Recipient::factory()->create([
            'user_id' => $this->user->id,
            'active' => false,
        ]);

        $response = $this->json('POST', '/api/v1/active-recipients/', [
            'id' => $recipient->id,
        ]);

        $response->assertSuccessful();
        $this->assertTrue($response->getData()->data->active);
        $this->assertTrue($this->user->recipients()->find($recipient->id)->active);
    }

    #[Test]
    public function user_can_deactivate_recipient(): void
    {
        $recipient = Recipient::factory()->create([
            'user_id' => $this->user->id,
            'active' => true,
        ]);

        $response = $this->json('DELETE', '/api/v1/active-recipients/'.$recipient->id);

        $response->assertNoContent();
        $this->assertFalse($this->user->recipients()->find($recipient->id)->active);
    }

    #[Test]
    public function user_can_not_deactivate_default_recipient(): void
    {
        $response = $this->json('DELETE', '/api/v1/active-recipients/'.$this->user->default_recipient_id);

        $response->assertForbidden();
        $this->assertTrue($this->user->defaultRecipient->active);
    }
}
