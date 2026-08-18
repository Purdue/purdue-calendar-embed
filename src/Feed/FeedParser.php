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
 * Normalizes Localist RSS items into Event objects.
 *
 * Feed items are deliberately not type-declared. WordPress renamed SimplePie's classes in
 * 6.7 (SimplePie_Item -> SimplePie\Item) when it moved to SimplePie 1.8; a hard type hint
 * for either name fatals on the other. Every method called here exists on both, so the
 * types live in docblocks where they inform static analysis without binding the runtime.
 */
final class FeedParser
{
    /** Yahoo Media RSS -- carries the event photo as <media:content url="..."/>. */
    private const NS_MEDIA = 'http://search.yahoo.com/mrss/';

    /** W3C Basic Geo -- <geo:lat> / <geo:long>, present on roughly half of items. */
    private const NS_GEO = 'http://www.w3.org/2003/01/geo/wgs84_pos#';

    /**
     * Localist prefixes every RSS title with the instance date, e.g.
     * "Aug 14, 2026: Volleyball -  Purdue vs. Ball State at West Lafayette, Ind."
     */
    private const TITLE_DATE_PREFIX = '/^[A-Z][a-z]{2}\s+\d{1,2},\s+\d{4}:\s*/';

    /**
     * @param array<int, \SimplePie\Item> $items
     *
     * @return list<Event>
     */
    public function parse(array $items): array
    {
        $events = [];

        foreach ($items as $item) {
            $events[] = $this->parseItem($item);
        }

        return $events;
    }

    /**
     * @param \SimplePie\Item $item
     */
    private function parseItem($item): Event
    {
        $rawTitle = (string) $item->get_title();
        $name = trim((string) preg_replace(self::TITLE_DATE_PREFIX, '', $rawTitle));

        return new Event(
            guid: (string) $item->get_id(),
            name: $name !== '' ? $name : $rawTitle,
            permalink: (string) $item->get_link(),
            startsAt: $this->parseDate($item),
            description: $this->parseDescription($item),
            imageUrl: $this->parseImageUrl($item),
            categories: $this->parseCategories($item),
            latitude: $this->parseGeo($item, 'lat'),
            longitude: $this->parseGeo($item, 'long'),
        );
    }

    /**
     * Localist emits both <pubDate> and <dc:date>, each carrying a real UTC offset.
     *
     * SimplePie's 'U' format preserves that offset. Formatting to a display string and
     * running it back through strtotime() -- as the purdue-home-theme news block does --
     * silently drops it.
     *
     * @param \SimplePie\Item $item
     */
    private function parseDate($item): ?DateTimeImmutable
    {
        $timestamp = $item->get_date('U');

        if ($timestamp === null) {
            return null;
        }

        $date = DateTimeImmutable::createFromFormat('U', (string) $timestamp);

        return $date === false ? null : $date->setTimezone(wp_timezone());
    }

    /**
     * The description is a rendered artifact, not data: Localist's calendar/_feed_event
     * Liquid template appends a "View on site | Email this event" paragraph to every item.
     *
     * @param \SimplePie\Item $item
     */
    private function parseDescription($item): string
    {
        $description = (string) $item->get_description();

        $description = (string) preg_replace(
            '#<p>\s*<a[^>]*>\s*View on site\s*</a>.*?</p>\s*$#is',
            '',
            $description
        );

        return trim(wp_strip_all_tags($description));
    }

    /**
     * @param \SimplePie\Item $item
     */
    private function parseImageUrl($item): string
    {
        $tags = $item->get_item_tags(self::NS_MEDIA, 'content');

        if (!is_array($tags)) {
            return '';
        }

        foreach ($tags as $tag) {
            $url = $tag['attribs']['']['url'] ?? '';

            if (is_string($url) && $url !== '') {
                return $url;
            }
        }

        return '';
    }

    /**
     * @param \SimplePie\Item $item
     *
     * @return list<string>
     */
    private function parseCategories($item): array
    {
        $categories = $item->get_categories();

        if (!is_array($categories)) {
            return [];
        }

        $labels = [];

        foreach ($categories as $category) {
            $label = trim((string) $category->get_label());

            if ($label !== '' && !in_array($label, $labels, true)) {
                $labels[] = $label;
            }
        }

        return $labels;
    }

    /**
     * @param \SimplePie\Item $item
     */
    private function parseGeo($item, string $element): ?float
    {
        $tags = $item->get_item_tags(self::NS_GEO, $element);

        if (!is_array($tags)) {
            return null;
        }

        $value = $tags[0]['data'] ?? '';

        return is_numeric($value) ? (float) $value : null;
    }
}
