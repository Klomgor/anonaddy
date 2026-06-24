<?php

namespace Tests\Unit;

use App\Helpers\CustomEmailValidator;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class CustomEmailValidatorTest extends TestCase
{
    #[Test]
    public function it_accepts_a_standard_email_address(): void
    {
        $this->assertTrue(CustomEmailValidator::isValid('user@example.com'));
    }

    #[Test]
    public function it_rejects_an_invalid_email_address(): void
    {
        $this->assertFalse(CustomEmailValidator::isValid('not-an-email'));
    }

    #[Test]
    public function it_rejects_an_empty_email_address(): void
    {
        $this->assertFalse(CustomEmailValidator::isValid(''));
    }

    #[Test]
    public function it_rejects_an_email_address_longer_than_254_characters(): void
    {
        $this->assertFalse(CustomEmailValidator::isValid(str_repeat('a', 250).'@example.com'));
    }
}
