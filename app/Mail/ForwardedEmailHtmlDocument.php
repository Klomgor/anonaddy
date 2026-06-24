<?php

namespace App\Mail;

final class ForwardedEmailHtmlDocument
{
    /**
     * Return content suitable for embedding in the forward wrapper: CSS from the original head
     * plus the inner HTML of the first body element, without nested document roots (html/head/body).
     *
     * Body-only embedding drops style blocks and stylesheet link tags; many
     * senders (MJML, newsletters) keep rules in head only. Prepends those fragments so layout and
     * classes apply when the message is wrapped by the banner.
     *
     * If the string has no body element, it is returned unchanged.
     */
    public static function innerHtmlForEmbedding(string $html): string
    {
        if ($html === '') {
            return $html;
        }

        if (! preg_match('#<body\b[^>]*>#i', $html, $openBodyMatch, PREG_OFFSET_CAPTURE)) {
            return $html;
        }

        $openingBodyTag = $openBodyMatch[0][0];
        $bodyStartOffset = $openBodyMatch[0][1] + strlen($openingBodyTag);
        $closingBodyOffset = strripos($html, '</body>');

        if ($closingBodyOffset === false || $closingBodyOffset < $bodyStartOffset) {
            return $html;
        }

        $bodyInner = substr($html, $bodyStartOffset, $closingBodyOffset - $bodyStartOffset);
        $headCssFragments = self::extractHeadCssFragments($html);

        if ($headCssFragments === '') {
            return $bodyInner;
        }

        return $headCssFragments.$bodyInner;
    }

    /**
     * Collect style blocks and stylesheet link tags from the first head … /head segment.
     */
    private static function extractHeadCssFragments(string $html): string
    {
        if (! preg_match('#<head\b[^>]*>#i', $html, $headOpen, PREG_OFFSET_CAPTURE)) {
            return '';
        }

        $headContentStart = $headOpen[0][1] + strlen($headOpen[0][0]);
        $headCloseOffset = stripos($html, '</head>', $headContentStart);

        if ($headCloseOffset === false) {
            return '';
        }

        $headInner = substr($html, $headContentStart, $headCloseOffset - $headContentStart);
        $parts = [];

        if (preg_match_all('#<style\b[^>]*>.*?</style>#is', $headInner, $styleMatches)) {
            foreach ($styleMatches[0] as $styleTag) {
                $parts[] = $styleTag;
            }
        }

        if (preg_match_all('#<link\b[^>]*>#i', $headInner, $linkMatches)) {
            foreach ($linkMatches[0] as $linkTag) {
                if (self::linkTagIsStylesheet($linkTag)) {
                    $parts[] = $linkTag;
                }
            }
        }

        return $parts === [] ? '' : implode('', $parts);
    }

    private static function linkTagIsStylesheet(string $linkTag): bool
    {
        return (bool) preg_match(
            '#\brel\s*=\s*(?:"stylesheet"|\'stylesheet\'|stylesheet(?:[\s/>]|$))#i',
            $linkTag
        );
    }
}
