<?php

/**
 * @package     Purdue/CalendarEmbed
 * @author      Drew Hallett
 *
 * @wordpress-plugin
 * Plugin Name: Purdue Calendar Embed
 * Plugin URI: https://github.com/Purdue/purdue-calendar-embed
 * Description: Gutenberg block that renders Localist events from the events.purdue.edu RSS feed, with a Localist widget embed as a fallback rendering mode.
 * Version: 0.1.0
 * Author: Purdue Marketing and Communications
 * Author URI: https://marcom.purdue.edu
 * License: GPL-3.0-or-later
 * License URI: https://www.gnu.org/licenses/gpl-3.0.en.html
 * Requires at least: 6.5
 * Requires PHP: 8.1
 * Text Domain: purdue-calendar-embed
 */

declare(strict_types=1);

namespace Purdue\CalendarEmbed;

if (!defined('ABSPATH')) {
    exit; // Exit if accessed directly.
}

define('PURDUE_CALENDAR_EMBED_VERSION', '0.1.0');
define('PURDUE_CALENDAR_EMBED_FILE', __FILE__);
define('PURDUE_CALENDAR_EMBED_PATH', plugin_dir_path(__FILE__));

if (file_exists(__DIR__ . '/vendor/autoload.php')) {
    require_once __DIR__ . '/vendor/autoload.php';
} else {
    // There are no runtime Composer dependencies, so a plain checkout or zip -- with build/
    // committed -- only needs our own classes. This is the composer.json PSR-4 mapping.
    spl_autoload_register(static function (string $class): void {
        $prefix = __NAMESPACE__ . '\\';

        if (!str_starts_with($class, $prefix)) {
            return;
        }

        $file = __DIR__ . '/src/' . str_replace('\\', '/', substr($class, strlen($prefix))) . '.php';

        if (is_file($file)) {
            require_once $file;
        }
    });
}

new Plugin();
