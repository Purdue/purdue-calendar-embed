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
 * Outcome of a feed fetch: the events, plus whether they came from the stale fallback.
 */
final class FeedResult
{
    /**
     * @param list<Event> $events
     */
    public function __construct(
        public readonly array $events,
        public readonly bool $isStale = false,
    ) {
    }
}
