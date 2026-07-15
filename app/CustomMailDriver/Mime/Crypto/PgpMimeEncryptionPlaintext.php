<?php

declare(strict_types=1);

namespace App\CustomMailDriver\Mime\Crypto;

use Illuminate\Support\Str;
use Symfony\Component\Mime\Email;

/**
 * Builds the plaintext MIME entity encrypted inside multipart/encrypted (RFC 3156).
 *
 * Without protected headers, only the MIME body is encrypted so clients re-parse from
 * Content-Type rather than treating a full RFC822 blob as plain text after decrypt.
 * With protected headers, the full message is encrypted and Content-Type gains
 * protected-headers="v1".
 *
 * A blank line ends the RFC822 header block. Spurious blank lines before Content-Type
 * (e.g. after Subject) make clients treat the rest of the MIME structure as plain text.
 */
final class PgpMimeEncryptionPlaintext
{
    public static function fromEmail(Email $message, bool $usesProtectedHeaders): string
    {
        if ($usesProtectedHeaders) {
            $raw = $message->toString();
        } else {
            $body = $message->getBody();
            $raw = $body === null ? $message->toString() : $body->toString();
        }

        return self::fromRfc822($raw, $usesProtectedHeaders);
    }

    public static function fromRfc822(string $raw, bool $usesProtectedHeaders): string
    {
        $lines = preg_split('/\r\n|\r|\n/', rtrim($raw)) ?: [];
        $lines = self::removeSpuriousHeaderBlankLines($lines);

        if ($usesProtectedHeaders) {
            $plainTextOnly = false;

            for ($i = 0; $i < count($lines); $i++) {
                $lineLower = strtolower($lines[$i]);

                if (Str::startsWith($lineLower, 'content-type: text/plain') || Str::startsWith($lineLower, 'content-type: multipart/')) {
                    if (Str::startsWith($lineLower, 'content-type: text/plain')) {
                        $plainTextOnly = true;
                    }

                    $lines[$i] = rtrim($lines[$i])."; protected-headers=\"v1\"\r\n";
                } elseif ($plainTextOnly && $i === count($lines) - 1) {
                    // Thunderbird subject display quirk for plaintext-only protected-header mail.
                    $lines[$i] = rtrim($lines[$i])."\r\n--\r\n";
                } else {
                    $lines[$i] = rtrim($lines[$i])."\r\n";
                }
            }
        } else {
            for ($i = 0; $i < count($lines); $i++) {
                $lines[$i] = rtrim($lines[$i])."\r\n";
            }
        }

        // Remove excess trailing newlines (RFC 3156 section 5.4).
        return rtrim(implode('', $lines))."\r\n";
    }

    /**
     * @param  list<string>  $lines
     * @return list<string>
     */
    private static function removeSpuriousHeaderBlankLines(array $lines): array
    {
        $result = [];
        $inHeaders = true;
        $seenContentType = false;
        $count = count($lines);

        for ($i = 0; $i < $count; $i++) {
            $line = $lines[$i];

            if ($inHeaders && trim($line) === '') {
                // Content-Type is the last header Symfony emits before the body, so any
                // blank line after it is the genuine header/body separator. This also
                // protects body text like "Hello: world" from being mistaken for a header.
                if (! $seenContentType && self::nextNonBlankLooksLikeHeader($lines, $i + 1)) {
                    // Spurious blank (e.g. whitespace-only fold artifact after Subject);
                    // keeping it would end header parsing before Content-Type.
                    continue;
                }

                $inHeaders = false;
                $result[] = '';

                continue;
            }

            if ($inHeaders && stripos($line, 'content-type:') === 0) {
                $seenContentType = true;
            }

            $result[] = $line;
        }

        return $result;
    }

    /**
     * @param  list<string>  $lines
     */
    private static function nextNonBlankLooksLikeHeader(array $lines, int $offset): bool
    {
        $count = count($lines);

        for ($j = $offset; $j < $count; $j++) {
            if (trim($lines[$j]) !== '') {
                return self::looksLikeHeaderLine($lines[$j]);
            }
        }

        return false;
    }

    private static function looksLikeHeaderLine(string $line): bool
    {
        if ($line !== '' && ($line[0] === ' ' || $line[0] === "\t")) {
            return true;
        }

        return (bool) preg_match('/^[A-Za-z0-9][A-Za-z0-9-]*\s*:/', $line);
    }
}
