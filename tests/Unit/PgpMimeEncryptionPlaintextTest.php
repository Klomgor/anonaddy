<?php

namespace Tests\Unit;

use App\CustomMailDriver\Mime\Crypto\PgpMimeEncryptionPlaintext;
use PHPUnit\Framework\Attributes\Test;
use Symfony\Component\Mime\Email;
use Tests\TestCase;

class PgpMimeEncryptionPlaintextTest extends TestCase
{
    #[Test]
    public function it_encrypts_mime_body_only_without_protected_headers(): void
    {
        $email = (new Email)
            ->from('sender@example.com')
            ->to('recipient@example.com')
            ->subject('Secret subject')
            ->text('Hello plain')
            ->html('<p>Hello html</p>');

        $plaintext = PgpMimeEncryptionPlaintext::fromEmail($email, false);

        $this->assertStringStartsWith('Content-Type: multipart/alternative;', $plaintext);
        $this->assertStringContainsString('Hello plain', $plaintext);
        $this->assertStringContainsString('<p>Hello html</p>', $plaintext);
        $this->assertStringNotContainsString('From:', $plaintext);
        $this->assertStringNotContainsString('To:', $plaintext);
        $this->assertStringNotContainsString('Subject:', $plaintext);
        $this->assertStringNotContainsString('protected-headers', $plaintext);
    }

    #[Test]
    public function it_encrypts_full_message_with_protected_headers(): void
    {
        $email = (new Email)
            ->from('sender@example.com')
            ->to('recipient@example.com')
            ->subject('Secret subject')
            ->text('Hello plain')
            ->html('<p>Hello html</p>');

        $plaintext = PgpMimeEncryptionPlaintext::fromEmail($email, true);

        $this->assertStringContainsString('From: sender@example.com', $plaintext);
        $this->assertStringContainsString('To: recipient@example.com', $plaintext);
        $this->assertStringContainsString('Subject: Secret subject', $plaintext);
        $this->assertStringContainsString('protected-headers="v1"', $plaintext);
        $this->assertStringContainsString('Hello plain', $plaintext);
    }

    #[Test]
    public function it_removes_spurious_blank_line_after_subject_before_remaining_headers(): void
    {
        $raw = "From: sender@example.com\r\n"
            ."To: recipient@example.com\r\n"
            ."Subject: The three-punch bombshell the media =?utf-8?Q?won=E2=80=99t?= touch\r\n"
            ."\r\n"
            ."Feedback-ID: F:abc:anonaddy\r\n"
            ."MIME-Version: 1.0\r\n"
            ."Content-Type: multipart/alternative; boundary=K54FqHXA\r\n"
            ."\r\n"
            ."--K54FqHXA\r\n"
            ."Content-Type: text/plain; charset=utf-8\r\n"
            ."\r\n"
            ."Hello\r\n"
            ."--K54FqHXA--\r\n";

        $plaintext = PgpMimeEncryptionPlaintext::fromRfc822($raw, true);

        $this->assertStringContainsString(
            "Subject: The three-punch bombshell the media =?utf-8?Q?won=E2=80=99t?= touch\r\nFeedback-ID:",
            $plaintext
        );
        $this->assertStringContainsString('Content-Type: multipart/alternative; boundary=K54FqHXA; protected-headers="v1"', $plaintext);
        $this->assertStringContainsString("\r\n\r\n--K54FqHXA\r\n", $plaintext);
    }

    #[Test]
    public function it_removes_whitespace_only_fold_artifact_line_after_subject(): void
    {
        $raw = "From: sender@example.com\r\n"
            ."Subject: Long subject\r\n"
            ." \r\n"
            ."Feedback-ID: F:abc:anonaddy\r\n"
            ."Content-Type: multipart/alternative; boundary=K54FqHXA\r\n"
            ."\r\n"
            ."--K54FqHXA\r\n"
            ."Content-Type: text/plain; charset=utf-8\r\n"
            ."\r\n"
            ."Hello\r\n"
            ."--K54FqHXA--\r\n";

        $plaintext = PgpMimeEncryptionPlaintext::fromRfc822($raw, true);

        $this->assertStringContainsString("Subject: Long subject\r\nFeedback-ID:", $plaintext);
        $this->assertStringContainsString("boundary=K54FqHXA; protected-headers=\"v1\"\r\n\r\n--K54FqHXA\r\n", $plaintext);
    }

    #[Test]
    public function it_keeps_header_body_separator_when_body_text_looks_like_a_header(): void
    {
        $raw = "From: sender@example.com\r\n"
            ."Subject: Hi\r\n"
            ."Content-Type: text/plain; charset=utf-8\r\n"
            ."\r\n"
            ."Hello: this body line looks like a header\r\n"
            ."More body text\r\n";

        $plaintext = PgpMimeEncryptionPlaintext::fromRfc822($raw, true);

        $this->assertStringContainsString(
            "Content-Type: text/plain; charset=utf-8; protected-headers=\"v1\"\r\n\r\nHello: this body line looks like a header\r\n",
            $plaintext
        );
    }

    #[Test]
    public function it_appends_thunderbird_plain_text_marker_for_protected_headers_text_only(): void
    {
        $email = (new Email)
            ->from('sender@example.com')
            ->to('recipient@example.com')
            ->subject('Secret subject')
            ->text('Hello plain only');

        $plaintext = PgpMimeEncryptionPlaintext::fromEmail($email, true);

        $this->assertStringContainsString('content-type: text/plain', strtolower($plaintext));
        $this->assertStringContainsString('protected-headers="v1"', $plaintext);
        $this->assertStringEndsWith("--\r\n", $plaintext);
    }
}
