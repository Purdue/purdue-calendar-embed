<?php

/**
 * @package Purdue/CalendarBlock
 */

declare(strict_types=1);

namespace Purdue\CalendarBlock\Feed;

use DateTimeImmutable;

if (!defined('ABSPATH')) {
    exit;
}

/**
 * A single Localist event instance, normalized from one RSS <item>.
 *
 * Only fields the Localist RSS feed actually carries are represented. The feed has no
 * end time, no all-day flag and no structured venue -- the xCal namespace is declared
 * on the channel but never emitted. See docs/localist-feed.md.
 */
final class Event
{
    /**
     * @param list<string> $categories
     */
    public function __construct(
        public readonly string $guid,
        public readonly string $name,
        public readonly string $permalink,
        public readonly ?DateTimeImmutable $startsAt,
        public readonly string $description,
        public readonly string $imageUrl,
        public readonly array $categories,
        public readonly ?float $latitude,
        public readonly ?float $longitude,
    ) {
    }

    /**
     * Serializable form, so a parsed feed can live in a transient.
     *
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'guid' => $this->guid,
            'name' => $this->name,
            'permalink' => $this->permalink,
            'startsAt' => $this->startsAt?->format(DATE_ATOM),
            'description' => $this->description,
            'imageUrl' => $this->imageUrl,
            'categories' => $this->categories,
            'latitude' => $this->latitude,
            'longitude' => $this->longitude,
        ];
    }

    /**
     * @param array<string, mixed> $data
     */
    public static function fromArray(array $data): self
    {
        $startsAt = null;

        if (is_string($data['startsAt'] ?? null) && $data['startsAt'] !== '') {
            $parsed = DateTimeImmutable::createFromFormat(DATE_ATOM, $data['startsAt']);
            $startsAt = $parsed === false ? null : $parsed;
        }

        /** @var list<string> $categories */
        $categories = array_values(array_filter(
            (array) ($data['categories'] ?? []),
            'is_string'
        ));

        return new self(
            guid: (string) ($data['guid'] ?? ''),
            name: (string) ($data['name'] ?? ''),
            permalink: (string) ($data['permalink'] ?? ''),
            startsAt: $startsAt,
            description: (string) ($data['description'] ?? ''),
            imageUrl: (string) ($data['imageUrl'] ?? ''),
            categories: $categories,
            latitude: isset($data['latitude']) ? (float) $data['latitude'] : null,
            longitude: isset($data['longitude']) ? (float) $data['longitude'] : null,
        );
    }

    /**
     * Primary category, skipping Localist's non-descriptive buckets.
     *
     * The first <category> is frequently "Featured", which says nothing about the event.
     */
    public function primaryCategory(): string
    {
        $skip = ['featured', 'uncategorized'];

        foreach ($this->categories as $category) {
            if (!in_array(strtolower(trim($category)), $skip, true)) {
                return $category;
            }
        }

        return $this->categories[0] ?? '';
    }
}
