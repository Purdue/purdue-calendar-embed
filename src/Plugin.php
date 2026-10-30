<?php

/**
 * @package Purdue/CalendarEmbed
 */

declare(strict_types=1);

namespace Purdue\CalendarEmbed;

use Purdue\CalendarEmbed\Feed\FeedClient;
use Purdue\CalendarEmbed\Feed\FeedUrl;

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Wires the plugin into WordPress.
 */
final class Plugin
{
    public function __construct()
    {
        add_action('init', [$this, 'registerBlocks']);
        add_action(FeedClient::REFRESH_HOOK, [$this, 'refreshFeed']);
    }

    /**
     * WP-Cron callback for the background refetch FeedClient schedules.
     *
     * The URL comes back out of the cron option, so it is checked against the feed host
     * allowlist again rather than trusted.
     */
    public function refreshFeed(mixed $url): void
    {
        if (is_string($url) && FeedUrl::isAllowed($url)) {
            (new FeedClient())->refresh($url);
        }
    }

    public function registerBlocks(): void
    {
        $blocks = glob(PURDUE_CALENDAR_EMBED_PATH . 'build/blocks/*/block.json') ?: [];

        foreach ($blocks as $block) {
            register_block_type(dirname($block));
        }
    }
}
