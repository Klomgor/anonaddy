<?php

namespace Tests\Unit;

use Illuminate\Support\Str;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class HtmlDeactivateBannerRegexTest extends TestCase
{
    private function deactivateBannerPattern(): string
    {
        $host = preg_quote(Str::of(config('app.url'))->after('://')->rtrim('/'), '/');

        return '/(?s)<tr\b(?:(?!<tr\b)(?!'.$host.').)*+'.$host.'(?:\/|%2F)deactivate(?:\/|%2F)(?:(?!<\/tr>).)*+<\/tr>/i';
    }

    private function removeDeactivateBanner(string $html): string
    {
        return preg_replace($this->deactivateBannerPattern(), '', $html) ?? $html;
    }

    #[Test]
    public function it_removes_deactivate_banner_row_from_html(): void
    {
        $host = Str::of(config('app.url'))->after('://')->rtrim('/');
        $html = '<table><tr><td><a href="https://'.$host.'/deactivate/abc">here</a></td></tr><tr><td>body</td></tr></table>';

        $result = $this->removeDeactivateBanner($html);

        $this->assertStringNotContainsString('deactivate', $result);
        $this->assertStringContainsString('body', $result);
    }

    #[Test]
    public function it_does_not_exhaust_pcre_jit_stack_on_large_html_bodies(): void
    {
        $host = Str::of(config('app.url'))->after('://')->rtrim('/');
        $inner = '';

        for ($i = 0; $i < 200; $i++) {
            $inner .= $host.' not deactivate '.str_repeat('x', 150).' ';
        }

        $html = substr(
            '<tr><td>'.$inner.$host.'/deactivate/abc</td></tr>'.str_repeat('<tr><td>content</td></tr>', 500),
            0,
            36129
        );

        $result = $this->removeDeactivateBanner($html);

        $this->assertSame(0, preg_last_error());
        $this->assertGreaterThan(0, strlen($result));
    }
}
