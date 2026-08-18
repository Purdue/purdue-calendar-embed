<?php

/**
 * @package Purdue/CalendarEmbed
 */

declare(strict_types=1);

namespace Purdue\CalendarEmbed\Widget;

use DOMDocument;

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Builds the Localist widget embed from the URL stored on the block.
 *
 * The block stores the widget URL, not the pasted snippet. Storing the snippet does not
 * work: KSES filters block attributes on save and strips <script> out of them, so the tag
 * never survives to the front end on any site that sets DISALLOW_UNFILTERED_HTML (ours
 * does, which means not even administrators are exempt). The editor pulls the URL out of
 * the pasted snippet instead, and we re-check it here -- the editor is convenience, this
 * class is the trust boundary.
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
     * Returns null when the URL is absent, malformed, or points somewhere we do not trust.
     * Callers render nothing in that case.
     */
    public static function fromUrl(string $url, string $containerId): ?self
    {
        // KSES rewrites bare `&` to `&amp;` in block attributes on every save, which would
        // otherwise leave us with a parameter literally named `amp;days` and hide the
        // existing `container` from remove_query_arg(). The encoding is idempotent, so one
        // pass is enough to get back to the URL the author pasted.
        $url = trim(html_entity_decode($url, ENT_QUOTES, 'UTF-8'));

        if (str_starts_with($url, '//')) {
            $url = 'https:' . $url;
        }

        if ($url === '' || !self::isAllowed($url)) {
            return null;
        }

        // Point the widget at this block instance. The two embeds already in production
        // both hardcode container="localist-widget-24463163", which collides as soon as
        // two of them share a page.
        $url = add_query_arg('container', $containerId, remove_query_arg('container', $url));

        return new self($url, $containerId);
    }

    /**
     * Fallback for blocks saved before the URL was stored on its own attribute.
     *
     * This only finds anything where the snippet still carries its <script> -- which, on a
     * site filtering post content through KSES, it will not. Kept so content authored
     * elsewhere still renders rather than failing silently.
     */
    public static function fromSnippet(string $snippet, string $containerId): ?self
    {
        $snippet = trim($snippet);

        if ($snippet === '') {
            return null;
        }

        $src = self::extractSrc($snippet);

        if ($src === null) {
            return null;
        }

        return self::fromUrl($src, $containerId);
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
