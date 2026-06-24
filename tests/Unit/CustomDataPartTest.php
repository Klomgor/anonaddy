<?php

namespace Tests\Unit;

use App\CustomMailDriver\Mime\Part\CustomDataPart;
use PHPUnit\Framework\Attributes\Test;
use Symfony\Component\Mime\Email;
use Tests\TestCase;

class CustomDataPartTest extends TestCase
{
    #[Test]
    public function it_sanitises_content_id_to_avoid_control_character_exceptions(): void
    {
        $part = new CustomDataPart('hello', 'file.txt', 'text/plain');

        $part->setContentId("<cid\r\n@example.com>");

        $headers = $part->getPreparedHeaders();

        $this->assertSame(['cid@example.com'], $headers->getHeaderBody('Content-ID'));
    }

    #[Test]
    public function it_preserves_non_addr_spec_content_id_for_inline_cid_matching(): void
    {
        $part = new CustomDataPart('hello', 'file.txt', 'text/plain');

        $part->setContentId('<7deaa9c7-507c-400a-be89-9424481eb119>');

        $headers = $part->getPreparedHeaders();

        $this->assertSame(['7deaa9c7-507c-400a-be89-9424481eb119'], $headers->getHeaderBody('Content-ID'));
    }

    #[Test]
    public function it_renders_email_without_exception_when_content_id_has_no_at_symbol(): void
    {
        $part = new CustomDataPart('hello', 'file.txt', 'text/plain');
        $part->asInline();
        $part->setContentId('7deaa9c7-507c-400a-be89-9424481eb119');

        $email = (new Email)
            ->from('sender@example.com')
            ->to('to@example.com')
            ->subject('cid test')
            ->html('<img src="cid:7deaa9c7-507c-400a-be89-9424481eb119">');
        $email->addPart($part);

        $raw = $email->toString();

        $this->assertStringContainsString('Content-ID: <7deaa9c7-507c-400a-be89-9424481eb119>', $raw);
    }

    #[Test]
    public function it_falls_back_to_a_generated_content_id_when_invalid(): void
    {
        $part = new CustomDataPart('hello', 'file.txt', 'text/plain');

        $part->setContentId(" \r\n\t ");

        $headers = $part->getPreparedHeaders();

        $contentIds = $headers->getHeaderBody('Content-ID');

        $this->assertIsArray($contentIds);
        $this->assertCount(1, $contentIds);
        $this->assertStringContainsString('@symfony', $contentIds[0]);
    }
}
