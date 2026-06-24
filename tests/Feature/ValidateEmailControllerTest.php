<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class ValidateEmailControllerTest extends TestCase
{
    use LazilyRefreshDatabase;

    #[Test]
    public function authenticated_user_can_validate_a_valid_email_address(): void
    {
        $user = $this->createUser();

        $response = $this->actingAs($user)->postJson(route('email.validate'), [
            'email' => 'user@example.com',
        ]);

        $response->assertOk();
        $response->assertJson(['valid' => true]);
    }

    #[Test]
    public function authenticated_user_cannot_validate_an_invalid_email_address(): void
    {
        $user = $this->createUser();

        $response = $this->actingAs($user)->postJson(route('email.validate'), [
            'email' => 'not-an-email',
        ]);

        $response->assertUnprocessable();
        $response->assertJsonValidationErrors('email');
    }

    #[Test]
    public function guest_cannot_validate_an_email_address(): void
    {
        $response = $this->postJson(route('email.validate'), [
            'email' => 'user@example.com',
        ]);

        $response->assertUnauthorized();
    }

    #[Test]
    public function authenticated_user_is_throttled_after_too_many_validation_requests(): void
    {
        $user = $this->createUser();

        for ($i = 0; $i < 30; $i++) {
            $this->actingAs($user)->postJson(route('email.validate'), [
                'email' => 'user@example.com',
            ])->assertOk();
        }

        $response = $this->actingAs($user)->postJson(route('email.validate'), [
            'email' => 'user@example.com',
        ]);

        $response->assertTooManyRequests();
    }
}
