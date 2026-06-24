<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\Alias;
use Illuminate\Mail\Mailable;
use Illuminate\Support\Str;
use Symfony\Component\Mime\Address;

/**
 * Normalises quoted reply bodies so recipients do not see reverse-routing addresses
 * or redundant obfuscated email fragments copied from forwarded From lines.
 */
final class ReplyQuotedReverseAliasRewriter
{
    /**
     * @param  array<int, string>  $explicitDestinations  Decoded outbound recipients (e.g. from the +extension)
     * @param  array<int, string>  $decodedTosLines  Mailbox strings from the inbound reply, e.g. "<a@b.com>"
     * @param  array<int, string>  $decodedCcsLines
     * @return array<int, string>
     */
    public static function collectRecipientAddresses(
        Mailable $mailable,
        array $explicitDestinations,
        array $decodedTosLines,
        array $decodedCcsLines
    ): array {
        $addresses = $explicitDestinations;

        foreach (array_merge($mailable->to, $mailable->cc) as $recipient) {
            $addr = self::normaliseMailboxRecipient($recipient);
            if ($addr !== null) {
                $addresses[] = $addr;
            }
        }

        foreach (array_merge($decodedTosLines, $decodedCcsLines) as $line) {
            if (! is_string($line)) {
                continue;
            }
            if (preg_match('/<([^>]+@[^>]+)>/', $line, $m)) {
                $addresses[] = $m[1];
            }
        }

        $unique = [];
        foreach ($addresses as $address) {
            $key = strtolower($address);
            if (! isset($unique[$key]) && filter_var($address, FILTER_VALIDATE_EMAIL)) {
                $unique[$key] = $address;
            }
        }

        return array_values($unique);
    }

    /**
     * @param  array<int, string>  $decodedRecipientEmails
     */
    public static function rewrite(string $body, Alias $alias, array $decodedRecipientEmails): string
    {
        [$body, $patternDecoded] = self::replaceAllReverseRoutingAddresses($body, $alias);

        $emails = [];
        foreach (array_merge($decodedRecipientEmails, $patternDecoded) as $email) {
            $key = strtolower($email);
            if (! isset($emails[$key]) && filter_var($email, FILTER_VALIDATE_EMAIL)) {
                $emails[$key] = $email;
            }
        }

        foreach ($emails as $email) {
            $reverse = $alias->local_part.'+'.Str::replaceLast('@', '=', $email).'@'.$alias->domain;
            $quotedReverse = preg_quote($reverse, '/');

            $body = preg_replace('/'.$quotedReverse.'/i', $email, $body) ?? $body;
            $body = preg_replace('/&lt;'.$quotedReverse.'&gt;/i', '&lt;'.$email.'&gt;', $body) ?? $body;
            $body = preg_replace('/mailto:'.$quotedReverse.'/i', 'mailto:'.$email, $body) ?? $body;

            $body = self::stripDisplayFromObfuscation($body, $email, $alias);
            $body = self::replacePlainAliasMailboxInQuoteAttribution($body, $alias, $email);
        }

        return $body;
    }

    /**
     * With "use reply-to", forwards use From: plain alias; clients quote "&lt;alias@domain&gt; wrote:"
     * instead of the reverse-routing address — swap to the real recipient for that reply.
     */
    private static function replacePlainAliasMailboxInQuoteAttribution(string $body, Alias $alias, string $destinationEmail): string
    {
        $aliasEmail = preg_quote($alias->email, '/');
        $gap = '(?:\s|(?:<br\s*\/?>))*';
        $destinationLt = '&lt;'.$destinationEmail.'&gt;';
        $destinationAngle = '<'.$destinationEmail.'>';

        // Gmail often quotes use-reply-to forwards as
        // Will &lt;<a href="mailto:alias@domain">alias@domain</a>&gt; wrote:
        $gmailAnchor = '<a\s[^>]*href="(?:mailto:)?'.$aliasEmail.'"[^>]*>[^<]*<\/a>';

        $body = preg_replace(
            '/&lt;'.$gmailAnchor.'&gt;'.$gap.'wrote:/iu',
            $destinationLt.' wrote:',
            $body
        ) ?? $body;

        $body = preg_replace(
            '/<'.$gmailAnchor.'>'.$gap.'wrote:/iu',
            $destinationAngle.' wrote:',
            $body
        ) ?? $body;

        $body = preg_replace(
            '/'.$gmailAnchor.$gap.'wrote:/iu',
            $destinationAngle.' wrote:',
            $body
        ) ?? $body;

        $body = preg_replace(
            '/&lt;'.$aliasEmail.'&gt;'.$gap.'wrote:/iu',
            $destinationLt.' wrote:',
            $body
        ) ?? $body;

        return preg_replace(
            '/<'.$aliasEmail.'>'.$gap.'wrote:/iu',
            $destinationAngle.' wrote:',
            $body
        ) ?? $body;
    }

    /**
     * Replace every reverse-routing address for this alias found in the body.
     *
     * @return array{0: string, 1: array<int, string>}
     */
    private static function replaceAllReverseRoutingAddresses(string $body, Alias $alias): array
    {
        $local = preg_quote($alias->local_part, '/');
        $domain = preg_quote($alias->domain, '/');
        $plus = '(?:\+|(?:%2B)|(?:&#0*43;)|(?:&plus;))';

        $decoded = [];

        [$body, $gmailDecoded] = self::replaceGmailSplitReverseAliasAnchors($body, $alias);
        $decoded = array_merge($decoded, $gmailDecoded);

        $pattern = '/'.$local.$plus.'([^@<]+)@'.$domain.'/iu';

        $body = preg_replace_callback($pattern, function (array $matches) use (&$decoded): string {
            $email = str_replace('=', '@', $matches[1]);

            if (! filter_var($email, FILTER_VALIDATE_EMAIL)) {
                return $matches[0];
            }

            $decoded[] = $email;

            return $email;
        }, $body) ?? $body;

        $mailtoPattern = '/mailto:'.$local.$plus.'([^@<&]+)@'.$domain.'/iu';

        $body = preg_replace_callback($mailtoPattern, function (array $matches) use (&$decoded): string {
            $email = str_replace('=', '@', $matches[1]);

            if (! filter_var($email, FILTER_VALIDATE_EMAIL)) {
                return $matches[0];
            }

            $decoded[] = $email;

            return 'mailto:'.$email;
        }, $body) ?? $body;

        return [$body, array_values(array_unique($decoded))];
    }

    /**
     * Gmail splits reverse aliases when quoting, e.g.
     * brief_choke896+contact=<a href="mailto:help.addy.io@addyto.me">help.addy.io@addyto.me</a>
     *
     * @return array{0: string, 1: array<int, string>}
     */
    private static function replaceGmailSplitReverseAliasAnchors(string $body, Alias $alias): array
    {
        $local = preg_quote($alias->local_part, '/');
        $aliasDomain = strtolower($alias->domain);
        $decoded = [];

        $pattern = '/'.$local.'\+([^=]+)=<a\s[^>]*href="mailto:([^"]+)"[^>]*>.*?<\/a>/is';

        $body = preg_replace_callback($pattern, function (array $matches) use ($aliasDomain, &$decoded): string {
            $localPart = $matches[1];
            $mailto = $matches[2];

            if (! str_ends_with(strtolower($mailto), '@'.$aliasDomain)) {
                return $matches[0];
            }

            $encodedDomain = Str::beforeLast($mailto, '@');
            $email = $localPart.'@'.$encodedDomain;

            if (! filter_var($email, FILTER_VALIDATE_EMAIL)) {
                return $matches[0];
            }

            $decoded[] = $email;

            return $email;
        }, $body) ?? $body;

        return [$body, array_values(array_unique($decoded))];
    }

    /**
     * Strip obfuscated sender labels produced by each display-from format option when a client
     * quotes a forward, leaving only the real mailbox before "wrote:".
     *
     * | Format     | Forward display (sender contact@help.addy.io) | Quoted mailbox (reply-to off) | Quoted mailbox (reply-to on) |
     * |------------|-----------------------------------------------|-------------------------------|------------------------------|
     * | DEFAULT    | Will 'contact at help.addy.io'                | reverse alias                 | plain alias                  |
     * | BRACKETS   | Will - contact(a)help.addy.io                 | reverse alias                 | plain alias                  |
     * | DOMAIN     | Will - help.addy.io                           | reverse alias                 | plain alias                  |
     * | NAME       | Will                                          | reverse alias                 | plain alias                  |
     * | ADDRESS    | contact at help.addy.io                       | reverse alias                 | plain alias                  |
     * | NONE       | (empty)                                       | reverse alias                 | plain alias                  |
     * | DOMAINONLY | help.addy.io                                  | reverse alias                 | plain alias                  |
     */
    private static function stripDisplayFromObfuscation(string $body, string $email, Alias $alias): string
    {
        $atForm = str_replace('@', ' at ', $email);
        $bracketForm = str_replace('@', '(a)', $email);
        $domain = Str::after($email, '@');
        $encodedLocal = preg_quote(Str::before($email, '@'), '/');
        $encodedDomain = preg_quote($domain, '/');
        $quote = '[\'\x{2018}\x{2019}]';

        $mailboxLt = '&lt;'.preg_quote($email, '/').'&gt;';
        $mailbox = '<'.preg_quote($email, '/').'>';
        $aliasEmail = preg_quote($alias->email, '/');
        $aliasLt = '&lt;'.$aliasEmail.'&gt;';
        $aliasPlain = '<'.$aliasEmail.'>';
        $aliasGmailAnchor = '&lt;<a\s[^>]*href="(?:mailto:)?'.$aliasEmail.'"[^>]*>[^<]*<\/a>&gt;';
        $aliasGmailAnchorPlain = '<a\s[^>]*href="(?:mailto:)?'.$aliasEmail.'"[^>]*>[^<]*<\/a>';
        $beforeMailbox = '(?='.$mailboxLt.'|'.$mailbox.'|'.$aliasLt.'|'.$aliasPlain.'|'.$aliasGmailAnchor.'|'.$aliasGmailAnchorPlain.')';

        // DEFAULT (0): quoted "local at domain" before reverse alias or mailbox.
        $body = preg_replace('/\s+'.$quote.preg_quote($atForm, '/').$quote.'\s*(?=<|&lt;)/iu', ' ', $body) ?? $body;

        $body = preg_replace(
            '/&#39;[^&#]*?'.preg_quote($atForm, '/').'[^<]*(?:<a\s[^>]*>[^<]*<\/a>)?[^<]*?&#39;\s*(?=&lt;|<)/iu',
            '',
            $body
        ) ?? $body;

        $body = preg_replace(
            '/&#39;[^&#]*?'.$encodedLocal.'\s+at\s+<a\s[^>]*>'.$encodedDomain.'<\/a>&#39;\s*(?=&lt;|<)/iu',
            '',
            $body
        ) ?? $body;

        $body = preg_replace(
            '/'.$quote.'[^\'\x{2018}\x{2019}]*?'.preg_quote($atForm, '/').'[^<]*(?:<a\s[^>]*>[^<]*<\/a>)?[^<]*?'.$quote.'\s*(?=&lt;|<)/iu',
            '',
            $body
        ) ?? $body;

        // BRACKETS (1): "Name - local(a)domain" before reverse alias or mailbox.
        $body = preg_replace('/\s+-\s*'.preg_quote($bracketForm, '/').'\s*(?=<|&lt;)/iu', ' ', $body) ?? $body;

        return self::stripRedundantSenderLabelsBeforeMailbox(
            $body,
            $atForm,
            $bracketForm,
            $domain,
            $encodedDomain,
            $encodedLocal,
            $beforeMailbox
        );
    }

    private static function stripRedundantSenderLabelsBeforeMailbox(
        string $body,
        string $atForm,
        string $bracketForm,
        string $domain,
        string $encodedDomain,
        string $encodedLocal,
        string $beforeMailbox
    ): string {
        foreach ([$atForm, $bracketForm, $domain] as $label) {
            $body = preg_replace('/'.preg_quote($label, '/').'\s*'.$beforeMailbox.'/iu', '', $body) ?? $body;
        }

        foreach ([$atForm, $domain] as $label) {
            $body = preg_replace(
                '/'.preg_quote($label, '/').'\s+<a\s[^>]*>'.$encodedDomain.'<\/a>\s*'.$beforeMailbox.'/iu',
                '',
                $body
            ) ?? $body;
        }

        $body = preg_replace(
            '/'.$encodedLocal.'\s+at\s+<a\s[^>]*>'.$encodedDomain.'<\/a>\s*'.$beforeMailbox.'/iu',
            '',
            $body
        ) ?? $body;

        $body = preg_replace('/<a\s[^>]*>'.$encodedDomain.'<\/a>\s*'.$beforeMailbox.'/iu', '', $body) ?? $body;

        // DOMAIN (2) / BRACKETS (1) with display name: "Name - domain|bracketForm" before mailbox.
        $body = preg_replace('/\s+-\s*'.$encodedDomain.'\s*'.$beforeMailbox.'/iu', ' ', $body) ?? $body;
        $body = preg_replace('/\s+-\s*'.preg_quote($bracketForm, '/').'\s*'.$beforeMailbox.'/iu', ' ', $body) ?? $body;
        $body = preg_replace('/\s+-\s*<a\s[^>]*>'.$encodedDomain.'<\/a>\s*'.$beforeMailbox.'/iu', ' ', $body) ?? $body;

        // DOMAIN / BRACKETS: orphaned "Name - " after the domain or bracket label was removed.
        return preg_replace('/\s+-\s*'.$beforeMailbox.'/iu', ' ', $body) ?? $body;
    }

    /**
     * @param  array<string, mixed>|Address|string  $recipient
     */
    private static function normaliseMailboxRecipient(Address|array|string $recipient): ?string
    {
        if ($recipient instanceof Address) {
            return $recipient->getAddress();
        }

        if (is_array($recipient) && isset($recipient['address']) && is_string($recipient['address'])) {
            return $recipient['address'];
        }

        if (is_string($recipient) && preg_match('/<([^>]+@[^>]+)>/', $recipient, $m)) {
            return $m[1];
        }

        return null;
    }
}
