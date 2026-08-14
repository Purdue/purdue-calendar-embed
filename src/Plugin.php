<?php

/**
 * @package Purdue/CalendarBlock
 */

declare(strict_types=1);

namespace Purdue\CalendarBlock;

use Purdue\CalendarBlock\Feed\FeedClient;

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

        FeedClient::registerFeedCacheLifetime();
        FeedClient::registerFeedOptions();
    }

    public function registerBlocks(): void
    {
        $blocks = glob(PURDUE_CALENDAR_BLOCK_PATH . 'build/blocks/*/block.json') ?: [];

        foreach ($blocks as $block) {
            register_block_type(dirname($block));
        }
    }
}
