<?php

namespace Tests\Unit;

use App\Mail\ForwardEmail;
use App\Models\Alias;
use App\Models\EmailData;
use App\Models\Recipient;
use App\Models\Username;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use PhpMimeMailParser\Parser;
use PHPUnit\Framework\Attributes\Test;
use ReflectionObject;
use Symfony\Component\Mime\Email;
use Tests\TestCase;

class ForwardEmailNullSenderTest extends TestCase
{
    use LazilyRefreshDatabase;

    #[Test]
    public function with_symfony_message_skips_original_sender_headers_when_sender_is_null(): void
    {
        $user = $this->createUser('johndoe');

        $recipient = Recipient::factory()->create([
            'user_id' => $user->id,
            'email' => 'john@example.com',
        ]);

        $domain = config('anonaddy.domain');
        $alias = Alias::factory()->create([
            'user_id' => $user->id,
            'email' => 'ebay@johndoe.'.$domain,
            'local_part' => 'ebay',
            'domain' => 'johndoe.'.$domain,
            'aliasable_id' => $user->default_username_id,
            'aliasable_type' => Username::class,
        ]);

        $parser = new Parser;
        $parser->setPath(base_path('tests/emails/email.eml'));
        $emailData = new EmailData($parser, 'will@anonaddy.com', 1000);

        $mailable = new ForwardEmail($alias, $emailData, $recipient);

        $reflection = new ReflectionObject($mailable);
        foreach (['sender', 'originalEnvelopeFrom'] as $property) {
            $prop = $reflection->getProperty($property);
            $prop->setAccessible(true);
            $prop->setValue($mailable, null);
        }

        $mailable->build();

        $message = new Email;
        foreach ($mailable->callbacks as $callback) {
            $callback($message);
        }

        $this->assertNull($message->getHeaders()->get('X-AnonAddy-Original-Sender'));
        $this->assertNull($message->getHeaders()->get('X-AnonAddy-Original-Envelope-From'));
        $this->assertNotNull($message->getHeaders()->get('Feedback-ID'));
    }

    #[Test]
    public function with_symfony_message_adds_original_sender_headers_when_sender_is_present(): void
    {
        $user = $this->createUser('johndoe');

        $recipient = Recipient::factory()->create([
            'user_id' => $user->id,
            'email' => 'john@example.com',
        ]);

        $domain = config('anonaddy.domain');
        $alias = Alias::factory()->create([
            'user_id' => $user->id,
            'email' => 'ebay@johndoe.'.$domain,
            'local_part' => 'ebay',
            'domain' => 'johndoe.'.$domain,
            'aliasable_id' => $user->default_username_id,
            'aliasable_type' => Username::class,
        ]);

        $parser = new Parser;
        $parser->setPath(base_path('tests/emails/email.eml'));
        $emailData = new EmailData($parser, 'will@anonaddy.com', 1000);

        $mailable = new ForwardEmail($alias, $emailData, $recipient);
        $mailable->build();

        $message = new Email;
        foreach ($mailable->callbacks as $callback) {
            $callback($message);
        }

        $this->assertSame('will@anonaddy.com', $message->getHeaders()->get('X-AnonAddy-Original-Sender')?->getValue());
        $this->assertSame('will@anonaddy.com', $message->getHeaders()->get('X-AnonAddy-Original-Envelope-From')?->getValue());
    }

    #[Test]
    public function email_data_resend_falls_back_to_original_sender_header_when_sender_is_null(): void
    {
        $raw = file_get_contents(base_path('tests/emails/email.eml'));
        $raw = "X-AnonAddy-Original-Sender: original@example.com\r\n".
            "X-AnonAddy-Original-Envelope-From: envelope@example.com\r\n".
            $raw;

        $parser = new Parser;
        $parser->setText($raw);

        $emailData = new EmailData($parser, null, 1000, 'F', true);

        $this->assertSame('original@example.com', $emailData->sender);
        $this->assertSame('envelope@example.com', $emailData->originalEnvelopeFrom);
    }
}
