<?php

namespace Tests\Unit;

use App\Models\Alias;
use App\Services\ReplyQuotedReverseAliasRewriter;
use Illuminate\Mail\Mailable;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use Symfony\Component\Mime\Address;
use Tests\TestCase;

class ReplyQuotedReverseAliasRewriterTest extends TestCase
{
    #[Test]
    public function it_rewrites_reverse_alias_in_quoted_line_without_known_recipients(): void
    {
        $alias = new Alias([
            'local_part' => 'brief_choke896',
            'domain' => 'addyto.me',
            'email' => 'brief_choke896@addyto.me',
        ]);

        $line = "On Fri, May 15, 2026 at 12:09 PM Will 'contact at help.addy.io' <brief_choke896+contact=help.addy.io@addyto.me> wrote:";

        $out = ReplyQuotedReverseAliasRewriter::rewrite($line, $alias, []);

        $this->assertStringContainsString('Will <contact@help.addy.io> wrote:', $out);
        $this->assertStringNotContainsString('brief_choke896+', $out);
        $this->assertStringNotContainsString("'contact at help.addy.io'", $out);
    }

    #[Test]
    public function it_rewrites_brackets_format_when_use_reply_to_leaves_plain_alias_in_angular_brackets(): void
    {
        $alias = new Alias([
            'local_part' => 'brief_choke896',
            'domain' => 'addyto.me',
            'email' => 'brief_choke896@addyto.me',
        ]);

        $line = 'On Fri, May 15, 2026 at 1:14 PM Will - contact(a)help.addy.io <brief_choke896@addyto.me> wrote:';

        $out = ReplyQuotedReverseAliasRewriter::rewrite($line, $alias, ['contact@help.addy.io']);

        $this->assertStringContainsString('Will <contact@help.addy.io> wrote:', $out);
        $this->assertStringNotContainsString('contact(a)help.addy.io', $out);
        $this->assertStringNotContainsString('brief_choke896@addyto.me', $out);
    }

    #[Test]
    public function it_rewrites_name_format_when_use_reply_to_leaves_plain_alias_in_gmail_mailto_anchor(): void
    {
        $alias = new Alias([
            'local_part' => 'jittery.piano636',
            'domain' => 'addy.io',
            'email' => 'jittery.piano636@addy.io',
        ]);

        $html = 'On Sat, May 16, 2026 at 2:48 PM Will &lt;<a href="mailto:jittery.piano636@addy.io">jittery.piano636@addy.io</a>&gt; wrote:';

        $out = ReplyQuotedReverseAliasRewriter::rewrite($html, $alias, ['contact@help.addy.io']);

        $this->assertStringContainsString('Will &lt;contact@help.addy.io&gt; wrote:', $out);
        $this->assertStringNotContainsString('jittery.piano636@addy.io', $out);
    }

    #[Test]
    public function it_rewrites_name_format_when_use_reply_to_leaves_plain_alias_in_plain_quote_line(): void
    {
        $alias = new Alias([
            'local_part' => 'jittery.piano636',
            'domain' => 'addy.io',
            'email' => 'jittery.piano636@addy.io',
        ]);

        $line = 'On Sat, May 16, 2026 at 2:48 PM Will <jittery.piano636@addy.io> wrote:';

        $out = ReplyQuotedReverseAliasRewriter::rewrite($line, $alias, ['contact@help.addy.io']);

        $this->assertStringContainsString('Will <contact@help.addy.io> wrote:', $out);
        $this->assertStringNotContainsString('jittery.piano636@addy.io', $out);
    }

    #[Test]
    public function it_rewrites_brackets_format_when_use_reply_to_with_html_entities(): void
    {
        $alias = new Alias([
            'local_part' => 'brief_choke896',
            'domain' => 'addyto.me',
            'email' => 'brief_choke896@addyto.me',
        ]);

        $html = 'On Fri, May 15, 2026 at 1:14 PM Will - contact(a)help.addy.io &lt;brief_choke896@addyto.me&gt;<br>wrote:';

        $out = ReplyQuotedReverseAliasRewriter::rewrite($html, $alias, ['contact@help.addy.io']);

        $this->assertStringContainsString('Will &lt;contact@help.addy.io&gt;', $out);
        $this->assertStringNotContainsString('contact(a)help.addy.io', $out);
        $this->assertStringNotContainsString('brief_choke896@addyto.me', $out);
    }

    #[Test]
    public function it_strips_address_display_format_obfuscation_before_quoted_mailbox(): void
    {
        $alias = new Alias([
            'local_part' => 'brief_choke896',
            'domain' => 'addyto.me',
            'email' => 'brief_choke896@addyto.me',
        ]);

        $html = 'On Fri, May 15, 2026 at 12:41 PM contact at <a href="http://help.addy.io">help.addy.io</a> &lt;contact@help.addy.io&gt; wrote:';

        $out = ReplyQuotedReverseAliasRewriter::rewrite($html, $alias, ['contact@help.addy.io']);

        $this->assertStringContainsString('12:41 PM &lt;contact@help.addy.io&gt; wrote:', $out);
        $this->assertStringNotContainsString('contact at <a', $out);
        $this->assertStringNotContainsString('contact at help.addy.io', $out);
    }

    #[Test]
    public function it_rewrites_gmail_split_anchor_reverse_alias_in_html_quote(): void
    {
        $alias = new Alias([
            'local_part' => 'brief_choke896',
            'domain' => 'addyto.me',
            'email' => 'brief_choke896@addyto.me',
        ]);

        $html = '<motion>On Fri, May 15, 2026 at 12:22 PM Will &#39;contact at <a href="http://help.addy.io">help.addy.io</a>&#39; &lt;brief_choke896+contact=<a href="mailto:help.addy.io@addyto.me">help.addy.io@addyto.me</a>&gt; wrote:<br></motion>';
        $html = str_replace(['<motion>', '</motion>'], '', $html);

        $out = ReplyQuotedReverseAliasRewriter::rewrite($html, $alias, ['contact@help.addy.io']);

        $this->assertStringContainsString('Will &lt;contact@help.addy.io&gt; wrote:', $out);
        $this->assertStringNotContainsString('brief_choke896+', $out);
        $this->assertStringNotContainsString('contact at <a', $out);
        $this->assertStringNotContainsString('mailto:help.addy.io@addyto.me', $out);
    }

    #[Test]
    public function it_rewrites_reverse_alias_case_insensitively_and_strips_default_display_obfuscation(): void
    {
        $alias = new Alias([
            'local_part' => 'alias',
            'domain' => 'addy.io',
            'email' => 'alias@addy.io',
        ]);

        $line = "On Thu, May 14, 2026, 6:22 AM Will 'contact at help.addy.io' <alias+contact=help.addy.io@ADDY.IO> wrote:";

        $out = ReplyQuotedReverseAliasRewriter::rewrite($line, $alias, ['contact@help.addy.io']);

        $this->assertStringContainsString('Will <contact@help.addy.io> wrote:', $out);
        $this->assertStringNotContainsString('alias+', strtolower($out));
        $this->assertStringNotContainsString("'contact at help.addy.io'", $out);
    }

    #[Test]
    public function it_rewrites_html_entity_angled_reverse_alias(): void
    {
        $alias = new Alias([
            'local_part' => 'ebay',
            'domain' => 'johndoe.anonaddy.com',
            'email' => 'ebay@johndoe.anonaddy.com',
        ]);

        $fragment = 'Hi &lt;ebay+support=ebay.com@Johndoe.anonaddy.com&gt; there';

        $out = ReplyQuotedReverseAliasRewriter::rewrite($fragment, $alias, ['support@ebay.com']);

        $this->assertSame('Hi &lt;support@ebay.com&gt; there', $out);
    }

    #[Test]
    public function it_rewrites_mailto_reverse_alias(): void
    {
        $alias = new Alias([
            'local_part' => 'ebay',
            'domain' => 'johndoe.anonaddy.com',
            'email' => 'ebay@johndoe.anonaddy.com',
        ]);

        $fragment = 'See mailto:ebay+support=ebay.com@johndoe.anonaddy.com please';

        $out = ReplyQuotedReverseAliasRewriter::rewrite($fragment, $alias, ['support@ebay.com']);

        $this->assertSame('See mailto:support@ebay.com please', $out);
    }

    /**
     * Every display-from format × use-reply-to on/off (plain-text client quotes).
     *
     * @return array<string, array{input: string, mustContain: string, mustNotContain: list<string>}>
     */
    public static function displayFromFormatReplyToMatrixProvider(): array
    {
        $aliasLocal = 'brief_choke896';
        $aliasDomain = 'addyto.me';
        $reverse = $aliasLocal.'+contact=help.addy.io@'.$aliasDomain;
        $plain = $aliasLocal.'@'.$aliasDomain;
        $recipient = 'contact@help.addy.io';

        $expect = 'Will <'.$recipient.'> wrote:';
        $expectRecipientOnly = '<'.$recipient.'> wrote:';
        $mustNot = [$reverse, $plain];

        return [
            'default, reply-to off' => [
                'input' => "On 1 Jan, Will 'contact at help.addy.io' <{$reverse}> wrote:",
                'mustContain' => $expect,
                'mustNotContain' => array_merge($mustNot, ["'contact at help.addy.io'"]),
            ],
            'default, reply-to on' => [
                'input' => "On 1 Jan, Will 'contact at help.addy.io' <{$plain}> wrote:",
                'mustContain' => $expect,
                'mustNotContain' => array_merge($mustNot, ["'contact at help.addy.io'"]),
            ],
            'brackets, reply-to off' => [
                'input' => "On 1 Jan, Will - contact(a)help.addy.io <{$reverse}> wrote:",
                'mustContain' => $expect,
                'mustNotContain' => array_merge($mustNot, ['contact(a)help.addy.io']),
            ],
            'brackets, reply-to on' => [
                'input' => "On 1 Jan, Will - contact(a)help.addy.io <{$plain}> wrote:",
                'mustContain' => $expect,
                'mustNotContain' => array_merge($mustNot, ['contact(a)help.addy.io']),
            ],
            'domain, reply-to off' => [
                'input' => "On 1 Jan, Will - help.addy.io <{$reverse}> wrote:",
                'mustContain' => $expect,
                'mustNotContain' => array_merge($mustNot, ['- help.addy.io']),
            ],
            'domain, reply-to on' => [
                'input' => "On 1 Jan, Will - help.addy.io <{$plain}> wrote:",
                'mustContain' => $expect,
                'mustNotContain' => array_merge($mustNot, ['- help.addy.io']),
            ],
            'name, reply-to off' => [
                'input' => "On 1 Jan, Will <{$reverse}> wrote:",
                'mustContain' => $expect,
                'mustNotContain' => $mustNot,
            ],
            'name, reply-to on' => [
                'input' => "On 1 Jan, Will <{$plain}> wrote:",
                'mustContain' => $expect,
                'mustNotContain' => $mustNot,
            ],
            'address, reply-to off' => [
                'input' => "On 1 Jan, contact at help.addy.io <{$reverse}> wrote:",
                'mustContain' => $expectRecipientOnly,
                'mustNotContain' => array_merge($mustNot, ['contact at help.addy.io ']),
            ],
            'address, reply-to on' => [
                'input' => "On 1 Jan, contact at help.addy.io <{$plain}> wrote:",
                'mustContain' => $expectRecipientOnly,
                'mustNotContain' => array_merge($mustNot, ['contact at help.addy.io ']),
            ],
            'none, reply-to off' => [
                'input' => "On 1 Jan, <{$reverse}> wrote:",
                'mustContain' => $expectRecipientOnly,
                'mustNotContain' => $mustNot,
            ],
            'none, reply-to on' => [
                'input' => "On 1 Jan, <{$plain}> wrote:",
                'mustContain' => $expectRecipientOnly,
                'mustNotContain' => $mustNot,
            ],
            'domainonly, reply-to off' => [
                'input' => "On 1 Jan, help.addy.io <{$reverse}> wrote:",
                'mustContain' => $expectRecipientOnly,
                'mustNotContain' => array_merge($mustNot, ['help.addy.io <']),
            ],
            'domainonly, reply-to on' => [
                'input' => "On 1 Jan, help.addy.io <{$plain}> wrote:",
                'mustContain' => $expectRecipientOnly,
                'mustNotContain' => array_merge($mustNot, ['help.addy.io <']),
            ],
        ];
    }

    /**
     * Gmail HTML quotes with use-reply-to on (plain alias inside mailto anchor).
     *
     * @return array<string, array{input: string, mustContain: string, mustNotContain: list<string>}>
     */
    public static function displayFromFormatReplyToOnGmailHtmlProvider(): array
    {
        $plain = 'brief_choke896@addyto.me';
        $recipient = 'contact@help.addy.io';
        $anchor = '&lt;<a href="mailto:'.$plain.'">'.$plain.'</a>&gt;';
        $mustNot = [$plain, 'brief_choke896+'];

        return [
            'default' => [
                'input' => 'On 1 Jan, Will &#39;contact at help.addy.io&#39; '.$anchor.' wrote:',
                'mustContain' => 'Will &lt;'.$recipient.'&gt; wrote:',
                'mustNotContain' => array_merge($mustNot, ['contact at help.addy.io']),
            ],
            'brackets' => [
                'input' => 'On 1 Jan, Will - contact(a)help.addy.io '.$anchor.' wrote:',
                'mustContain' => 'Will &lt;'.$recipient.'&gt; wrote:',
                'mustNotContain' => array_merge($mustNot, ['contact(a)help.addy.io']),
            ],
            'domain' => [
                'input' => 'On 1 Jan, Will - help.addy.io '.$anchor.' wrote:',
                'mustContain' => 'Will &lt;'.$recipient.'&gt; wrote:',
                'mustNotContain' => array_merge($mustNot, ['- help.addy.io']),
            ],
            'name' => [
                'input' => 'On 1 Jan, Will '.$anchor.' wrote:',
                'mustContain' => 'Will &lt;'.$recipient.'&gt; wrote:',
                'mustNotContain' => $mustNot,
            ],
            'address' => [
                'input' => 'On 1 Jan, contact at help.addy.io '.$anchor.' wrote:',
                'mustContain' => '&lt;'.$recipient.'&gt; wrote:',
                'mustNotContain' => array_merge($mustNot, ['contact at help.addy.io']),
            ],
            'none' => [
                'input' => 'On 1 Jan, '.$anchor.' wrote:',
                'mustContain' => '&lt;'.$recipient.'&gt; wrote:',
                'mustNotContain' => $mustNot,
            ],
            'domainonly' => [
                'input' => 'On 1 Jan, help.addy.io '.$anchor.' wrote:',
                'mustContain' => '&lt;'.$recipient.'&gt; wrote:',
                'mustNotContain' => array_merge($mustNot, ['help.addy.io &lt;']),
            ],
        ];
    }

    #[DataProvider('displayFromFormatReplyToMatrixProvider')]
    #[Test]
    public function it_rewrites_each_display_from_format_with_and_without_reply_to(
        string $input,
        string $mustContain,
        array $mustNotContain
    ): void {
        $alias = new Alias([
            'local_part' => 'brief_choke896',
            'domain' => 'addyto.me',
            'email' => 'brief_choke896@addyto.me',
        ]);

        $out = ReplyQuotedReverseAliasRewriter::rewrite($input, $alias, ['contact@help.addy.io']);

        $this->assertStringContainsString($mustContain, $out);
        foreach ($mustNotContain as $fragment) {
            $this->assertStringNotContainsString($fragment, $out);
        }
    }

    #[DataProvider('displayFromFormatReplyToOnGmailHtmlProvider')]
    #[Test]
    public function it_rewrites_each_display_from_format_when_reply_to_on_and_gmail_html_quote(
        string $input,
        string $mustContain,
        array $mustNotContain
    ): void {
        $alias = new Alias([
            'local_part' => 'brief_choke896',
            'domain' => 'addyto.me',
            'email' => 'brief_choke896@addyto.me',
        ]);

        $out = ReplyQuotedReverseAliasRewriter::rewrite($input, $alias, ['contact@help.addy.io']);

        $this->assertStringContainsString($mustContain, $out);
        foreach ($mustNotContain as $fragment) {
            $this->assertStringNotContainsString($fragment, $out);
        }
    }

    /**
     * @return array<string, array{input: string, mustContain: string, mustNotContain: list<string>}>
     */
    public static function displayFromFormatQuoteLinesProvider(): array
    {
        return [
            'default with mailbox' => [
                'input' => "On 1 Jan, Will 'contact at help.addy.io' &lt;contact@help.addy.io&gt; wrote:",
                'mustContain' => 'Will &lt;contact@help.addy.io&gt; wrote:',
                'mustNotContain' => ["'contact at help.addy.io'"],
            ],
            'brackets with display name' => [
                'input' => 'On 1 Jan, Will - contact(a)help.addy.io &lt;contact@help.addy.io&gt; wrote:',
                'mustContain' => 'Will &lt;contact@help.addy.io&gt; wrote:',
                'mustNotContain' => ['contact(a)help.addy.io'],
            ],
            'brackets sender only' => [
                'input' => 'On 1 Jan, contact(a)help.addy.io &lt;contact@help.addy.io&gt; wrote:',
                'mustContain' => '&lt;contact@help.addy.io&gt; wrote:',
                'mustNotContain' => ['contact(a)help.addy.io'],
            ],
            'domain with display name' => [
                'input' => 'On 1 Jan, Will - help.addy.io &lt;contact@help.addy.io&gt; wrote:',
                'mustContain' => 'Will &lt;contact@help.addy.io&gt; wrote:',
                'mustNotContain' => ['- help.addy.io'],
            ],
            'domainonly' => [
                'input' => 'On 1 Jan, help.addy.io &lt;contact@help.addy.io&gt; wrote:',
                'mustContain' => '&lt;contact@help.addy.io&gt; wrote:',
                'mustNotContain' => ['help.addy.io &lt;'],
            ],
            'domainonly with gmail link' => [
                'input' => 'On 1 Jan, <a href="http://help.addy.io">help.addy.io</a> &lt;contact@help.addy.io&gt; wrote:',
                'mustContain' => '&lt;contact@help.addy.io&gt; wrote:',
                'mustNotContain' => ['<a href="http://help.addy.io">'],
            ],
            'domain with linked domain' => [
                'input' => 'On 1 Jan, Will - <a href="http://help.addy.io">help.addy.io</a> &lt;contact@help.addy.io&gt; wrote:',
                'mustContain' => 'Will &lt;contact@help.addy.io&gt; wrote:',
                'mustNotContain' => ['- <a href'],
            ],
        ];
    }

    #[DataProvider('displayFromFormatQuoteLinesProvider')]
    #[Test]
    public function it_strips_obfuscation_for_each_display_from_format(string $input, string $mustContain, array $mustNotContain): void
    {
        $alias = new Alias([
            'local_part' => 'brief_choke896',
            'domain' => 'addyto.me',
            'email' => 'brief_choke896@addyto.me',
        ]);

        $out = ReplyQuotedReverseAliasRewriter::rewrite($input, $alias, ['contact@help.addy.io']);

        $this->assertStringContainsString($mustContain, $out);
        foreach ($mustNotContain as $fragment) {
            $this->assertStringNotContainsString($fragment, $out);
        }
    }

    #[Test]
    public function it_strips_brackets_display_format_obfuscation_before_angle_address(): void
    {
        $alias = new Alias([
            'local_part' => 'ebay',
            'domain' => 'johndoe.anonaddy.com',
            'email' => 'ebay@johndoe.anonaddy.com',
        ]);

        $line = 'On 1 Jan, Jane - support(a)ebay.com <ebay+support=ebay.com@johndoe.anonaddy.com> wrote:';

        $out = ReplyQuotedReverseAliasRewriter::rewrite($line, $alias, ['support@ebay.com']);

        $this->assertStringContainsString('Jane <support@ebay.com>', $out);
        $this->assertStringNotContainsString('support(a)ebay.com', $out);
    }

    #[Test]
    public function it_collects_recipient_addresses_from_mailable_to_cc_and_decoded_lines(): void
    {
        $mailable = new class extends Mailable
        {
            public function __construct()
            {
                $this->to = [new Address('contact@ebay.com')];
                $this->cc = [new Address('sales@ebay.com')];
            }

            public function build()
            {
                return $this;
            }
        };

        $addresses = ReplyQuotedReverseAliasRewriter::collectRecipientAddresses(
            $mailable,
            ['support@ebay.com'],
            ['<support@ebay.com>'],
            []
        );

        $this->assertEqualsCanonicalizing(
            ['contact@ebay.com', 'sales@ebay.com', 'support@ebay.com'],
            $addresses
        );
    }
}
