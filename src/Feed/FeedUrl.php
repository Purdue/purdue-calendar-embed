<?php

/**
 * @package Purdue/CalendarBlock
 */

declare(strict_types=1);

namespace Purdue\CalendarBlock\Feed;

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Builds a Localist RSS URL from block attributes.
 *
 * Only params the feed actually honours are applied. calendar.xml mirrors the calendar
 * page's query string (via the theme's `{% feed_url xml %}` tag), so it accepts
 * event_types[] and experience but silently ignores days/num/pp/page.
 */
final class FeedUrl
{
    public const DEFAULT = 'https://events.purdue.edu/calendar.xml';

    /** Hosts we are willing to fetch a feed from. */
    private const ALLOWED_HOSTS = ['events.purdue.edu', 'localist.com'];

    /**
     * @param list<string> $eventTypes Numeric Localist event-type IDs.
     */
    public function build(string $baseUrl, array $eventTypes, string $experience): string
    {
        $baseUrl = trim($baseUrl) !== '' ? trim($baseUrl) : self::DEFAULT;

        if (!self::isAllowed($baseUrl)) {
            $baseUrl = self::DEFAULT;
        }

        $args = [];

        foreach ($eventTypes as $index => $type) {
            $type = trim($type);

            if ($type !== '') {
                $args[sprintf('event_types[%d]', $index)] = $type;
            }
        }

        if (in_array($experience, ['inperson', 'hybrid', 'virtual'], true)) {
            $args['experience'] = $experience;
        }

        return $args === [] ? $baseUrl : add_query_arg($args, $baseUrl);
    }

    /**
     * Splits the editor's comma-separated event-type field into IDs.
     *
     * @return list<string>
     */
    public static function parseEventTypes(string $raw): array
    {
        $parts = preg_split('/[\s,]+/', trim($raw)) ?: [];

        return array_values(array_filter(
            array_map('trim', $parts),
            static fn(string $part): bool => $part !== '' && ctype_digit($part)
        ));
    }

    public static function isAllowed(string $url): bool
    {
        $host = wp_parse_url($url, PHP_URL_HOST);

        if (!is_string($host) || $host === '') {
            return false;
        }

        $scheme = wp_parse_url($url, PHP_URL_SCHEME);

        if ($scheme !== 'https') {
            return false;
        }

        $host = strtolower($host);

        foreach (self::ALLOWED_HOSTS as $allowed) {
            if ($host === $allowed || str_ends_with($host, '.' . $allowed)) {
                return true;
            }
        }

        return false;
    }
}
