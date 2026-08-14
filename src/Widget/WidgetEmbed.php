<?php

/**
 * @package Purdue/CalendarBlock
 */

declare(strict_types=1);

namespace Purdue\CalendarBlock\Widget;

use DOMDocument;

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Parses the embed snippet produced by Localist's widget builder.
 *
 * The pasted HTML is never stored or echoed verbatim -- doing so would let anyone who can
 * edit a post inject an arbitrary <script src>. We extract the URL, check it against a
 * host allowlist, and reconstruct the markup ourselves.
 *
 * This mode exists because /widget/view honours `days` and `num`, which calendar.xml
 * ignores. It is the escape hatch for anything the RSS feed cannot express.
 */
final class WidgetEmbed
{
    private const ALLOWED_HOSTS = ['events.purdue.edu', 'localist.com'];

    private const WIDGET_PATH = '/widget/view';

    private function __construct(
        public readonly string $scriptUrl,
        public readonly string $containerId,
    ) {
    }

    /**
     * Returns null when the snippet is absent, malformed, or points somewhere we do not
     * trust. Callers render nothing in that case.
     */
    public static function fromSnippet(string $snippet, string $containerId): ?self
    {
        $snippet = trim($snippet);

        if ($snippet === '') {
            return null;
        }

        $src = self::extractSrc($snippet);

        if ($src === null || !self::isAllowed($src)) {
            return null;
        }

        // Point the widget at this block instance. The two embeds already in production
        // both hardcode container="localist-widget-24463163", which collides as soon as
        // two of them share a page.
        $url = add_query_arg('container', $containerId, remove_query_arg('container', $src));

        return new self($url, $containerId);
    }

    private static function extractSrc(string $snippet): ?string
    {
        $document = new DOMDocument();

        $previous = libxml_use_internal_errors(true);
        $loaded = $document->loadHTML(
            '<?xml encoding="utf-8" ?><body>' . $snippet . '</body>',
            LIBXML_NONET | LIBXML_NOERROR | LIBXML_NOWARNING
        );
        libxml_clear_errors();
        libxml_use_internal_errors($previous);

        if ($loaded === false) {
            return null;
        }

        foreach ($document->getElementsByTagName('script') as $script) {
            $src = trim($script->getAttribute('src'));

            if ($src !== '') {
                return $src;
            }
        }

        return null;
    }

    private static function isAllowed(string $url): bool
    {
        $parts = wp_parse_url($url);

        if (!is_array($parts)) {
            return false;
        }

        $scheme = $parts['scheme'] ?? '';
        $host = strtolower((string) ($parts['host'] ?? ''));
        $path = (string) ($parts['path'] ?? '');

        if ($scheme !== 'https' || $host === '') {
            return false;
        }

        if ($path !== self::WIDGET_PATH) {
            return false;
        }

        foreach (self::ALLOWED_HOSTS as $allowed) {
            if ($host === $allowed || str_ends_with($host, '.' . $allowed)) {
                return true;
            }
        }

        return false;
    }
}
