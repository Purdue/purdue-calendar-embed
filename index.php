<?php

/**
 * @package     Purdue/CalendarBlock
 * @author      Drew Hallett
 *
 * @wordpress-plugin
 * Plugin Name: Purdue Calendar Block
 * Plugin URI: https://github.com/Purdue/wp-plugin-calendar-block
 * Description: Gutenberg block that renders Localist events from the events.purdue.edu RSS feed, with a Localist widget embed as a fallback rendering mode.
 * Version: 0.1.0
 * Author: Purdue Marketing and Communications
 * Author URI: https://marcom.purdue.edu
 * License: GPL-3.0-or-later
 * License URI: https://www.gnu.org/licenses/gpl-3.0.en.html
 * Requires at least: 6.5
 * Requires PHP: 8.1
 * Text Domain: purdue-calendar-block
 */

declare(strict_types=1);

namespace Purdue\CalendarBlock;

if (!defined('ABSPATH')) {
    exit; // Exit if accessed directly.
}

define('PURDUE_CALENDAR_BLOCK_VERSION', '0.1.0');
define('PURDUE_CALENDAR_BLOCK_FILE', __FILE__);
define('PURDUE_CALENDAR_BLOCK_PATH', plugin_dir_path(__FILE__));

if (file_exists(__DIR__ . '/vendor/autoload.php')) {
    require_once __DIR__ . '/vendor/autoload.php';
}

new Plugin();
