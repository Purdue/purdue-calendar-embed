<?php

/**
 * @package Purdue/CalendarEmbed
 */

declare(strict_types=1);

namespace Purdue\CalendarEmbed\Feed;

use DateTimeImmutable;

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Applies the narrowing the feed URL cannot do itself.
 *
 * Localist's calendar.xml honours the calendar page's filter params (event_types[],
 * experience, match) but ignores the widget params -- days, num, pp and page have no
 * effect, verified against the live feed. So windowing and counting happen here.
 */
final class FeedFilter
{
    /**
     * @param list<Event> $events
     *
     * @return list<Event>
     */
    public function apply(array $events, int $count, int $windowDays, bool $hidePast): array
    {
        $now = new DateTimeImmutable('now', wp_timezone());
        $horizon = $windowDays > 0 ? $now->modify(sprintf('+%d days', $windowDays)) : null;

        $filtered = [];

        foreach ($events as $event) {
            if ($event->startsAt === null) {
                continue;
            }

            if ($hidePast && $event->startsAt < $now) {
                continue;
            }

            if ($horizon !== null && $event->startsAt > $horizon) {
                continue;
            }

            $filtered[] = $event;
        }

        usort(
            $filtered,
            static fn(Event $a, Event $b): int => $a->startsAt <=> $b->startsAt
        );

        return $count > 0 ? array_slice($filtered, 0, $count) : $filtered;
    }
}
