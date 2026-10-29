<?php

/**
 * @package Purdue/CalendarEmbed
 */

declare(strict_types=1);

namespace Purdue\CalendarEmbed\Feed;

use WP_Error;

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Fetches and caches a Localist RSS feed.
 *
 * Caching is a correctness requirement here, not an optimisation. The unfiltered
 * events.purdue.edu feed is ~1.7 MB / 750+ items spanning eleven months, and the origin
 * rate-limits aggressively -- it returns `x-ratelimit-remaining: 0` and HTTP 429 under
 * even light polling. So we cache the *normalized* events rather than the raw XML, and
 * keep a longer-lived stale copy to serve when the origin refuses us.
 */
final class FeedClient
{
    private const CACHE_PREFIX = 'pcb_feed_';
    private const STALE_PREFIX = 'pcb_stale_';
    private const FAILURE_PREFIX = 'pcb_fail_';

    /** Matches the origin's own `cache-control: max-age=600`. */
    private const DEFAULT_TTL = 10 * MINUTE_IN_SECONDS;

    private const STALE_TTL = DAY_IN_SECONDS;

    /**
     * How long to stop asking the origin after a failed fetch. A failure can take the full
     * 15-second timeout, and the block stops page caches from keeping a failed render, so
     * without this every uncached view during an outage would wait out the timeout again
     * and add to the rate limiting that probably caused it.
     */
    private const RETRY_AFTER = MINUTE_IN_SECONDS;

    public function __construct(private readonly FeedParser $parser = new FeedParser())
    {
    }

    /**
     * @return FeedResult|WP_Error
     */
    public function fetch(string $url): FeedResult|WP_Error
    {
        $key = md5($url);
        $cached = get_transient(self::CACHE_PREFIX . $key);

        if (is_array($cached)) {
            return new FeedResult($this->hydrate($cached));
        }

        $failure = get_transient(self::FAILURE_PREFIX . $key);

        if (is_string($failure)) {
            return $this->fallback($key, new WP_Error('purdue_calendar_embed_retry_later', $failure));
        }

        $rss = $this->fetchFeed($url);

        if ($rss instanceof WP_Error) {
            return $this->fail($key, $rss);
        }

        $quantity = $rss->get_item_quantity();

        if ($quantity === 0) {
            return $this->fail(
                $key,
                new WP_Error(
                    'purdue_calendar_embed_empty_feed',
                    __('The Localist feed returned no events.', 'purdue-calendar-embed')
                )
            );
        }

        /** @var array<int, \SimplePie\Item> $items */
        $items = $rss->get_items();
        $events = $this->parser->parse($items);

        $serialized = array_map(static fn(Event $event): array => $event->toArray(), $events);

        set_transient(self::CACHE_PREFIX . $key, $serialized, $this->ttl());
        set_transient(self::STALE_PREFIX . $key, $serialized, self::STALE_TTL);

        return new FeedResult($events);
    }

    /**
     * Record a failed fetch so the origin is left alone for RETRY_AFTER, then fall back.
     *
     * The error message is kept so the editor can still say what went wrong while
     * requests are being skipped.
     *
     * @return FeedResult|WP_Error
     */
    private function fail(string $key, WP_Error $error): FeedResult|WP_Error
    {
        set_transient(self::FAILURE_PREFIX . $key, $error->get_error_message(), self::RETRY_AFTER);

        return $this->fallback($key, $error);
    }

    /**
     * Serve the last known-good copy when the origin errors, rate-limits, or empties out.
     *
     * @return FeedResult|WP_Error
     */
    private function fallback(string $key, WP_Error $error): FeedResult|WP_Error
    {
        $stale = get_transient(self::STALE_PREFIX . $key);

        if (is_array($stale) && $stale !== []) {
            return new FeedResult($this->hydrate($stale), true);
        }

        return $error;
    }

    /**
     * @param array<int, mixed> $rows
     *
     * @return list<Event>
     */
    private function hydrate(array $rows): array
    {
        $events = [];

        foreach ($rows as $row) {
            if (is_array($row)) {
                $events[] = Event::fromArray($row);
            }
        }

        return $events;
    }

    private function ttl(): int
    {
        /**
         * Filters how long parsed Localist events stay fresh.
         *
         * @param int $ttl Seconds.
         */
        return (int) apply_filters('purdue_calendar_embed_cache_ttl', self::DEFAULT_TTL);
    }

    /**
     * Calls fetch_feed() with our SimplePie tuning applied only for the duration of the
     * call.
     *
     * These two hooks are global -- wp_feed_cache_transient_lifetime and wp_feed_options
     * fire for every feed WordPress fetches. Registering them permanently would drop the
     * whole site's feed cache from 12 hours to ten minutes and re-tune feeds belonging to
     * other plugins, so they are added and removed around our own request.
     *
     * @return \SimplePie\SimplePie|WP_Error
     */
    private function fetchFeed(string $url)
    {
        $lifetime = fn(): int => $this->ttl();

        $options = static function ($feed): void {
            $feed->set_timeout(15);
            // Localist orders the feed by start date; SimplePie would re-sort by pubDate.
            $feed->enable_order_by_date(false);
        };

        add_filter('wp_feed_cache_transient_lifetime', $lifetime);
        add_action('wp_feed_options', $options);

        try {
            return fetch_feed($url);
        } finally {
            remove_filter('wp_feed_cache_transient_lifetime', $lifetime);
            remove_action('wp_feed_options', $options);
        }
    }
}
