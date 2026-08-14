<?php

/**
 * @package Purdue/CalendarBlock
 */

declare(strict_types=1);

namespace Purdue\CalendarBlock\Feed;

use SimplePie\SimplePie;
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

    /** Matches the origin's own `cache-control: max-age=600`. */
    private const DEFAULT_TTL = 10 * MINUTE_IN_SECONDS;

    private const STALE_TTL = DAY_IN_SECONDS;

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

        $rss = fetch_feed($url);

        if ($rss instanceof WP_Error) {
            return $this->fallback($key, $rss);
        }

        $quantity = $rss->get_item_quantity();

        if ($quantity === 0) {
            return $this->fallback(
                $key,
                new WP_Error(
                    'purdue_calendar_block_empty_feed',
                    __('The Localist feed returned no events.', 'purdue-calendar-block')
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
        return (int) apply_filters('purdue_calendar_block_cache_ttl', self::DEFAULT_TTL);
    }

    /**
     * SimplePie caches raw XML separately from our normalized cache; keep the two aligned
     * so a stale SimplePie copy cannot outlive the events we derived from it.
     */
    public static function registerFeedCacheLifetime(): void
    {
        add_filter(
            'wp_feed_cache_transient_lifetime',
            static fn(): int => (int) apply_filters(
                'purdue_calendar_block_cache_ttl',
                self::DEFAULT_TTL
            )
        );
    }

    /**
     * SimplePie strips most markup by default; the Localist description is trusted HTML
     * from our own calendar, and we strip tags ourselves in the parser.
     */
    public static function registerFeedOptions(): void
    {
        add_action('wp_feed_options', static function (SimplePie $feed): void {
            $feed->set_timeout(15);
            $feed->enable_order_by_date(false);
        });
    }
}
