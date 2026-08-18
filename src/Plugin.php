<?php

/**
 * @package Purdue/CalendarEmbed
 */

declare(strict_types=1);

namespace Purdue\CalendarEmbed;

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
    }

    public function registerBlocks(): void
    {
        $blocks = glob(PURDUE_CALENDAR_EMBED_PATH . 'build/blocks/*/block.json') ?: [];

        foreach ($blocks as $block) {
            register_block_type(dirname($block));
        }
    }
}
