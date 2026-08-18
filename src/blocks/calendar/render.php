<?php

/**
 * Server-side render for the purdue/calendar block.
 *
 * @package Purdue/CalendarEmbed
 *
 * @var array<string, mixed> $attributes
 */

declare(strict_types=1);

use Purdue\CalendarEmbed\Feed\Event;
use Purdue\CalendarEmbed\Feed\FeedClient;
use Purdue\CalendarEmbed\Feed\FeedFilter;
use Purdue\CalendarEmbed\Feed\FeedUrl;
use Purdue\CalendarEmbed\Widget\WidgetEmbed;

if (!defined('ABSPATH')) {
    exit;
}

if (!function_exists('purdue_calendar_embed_notice')) {
    /**
     * Editor-only placeholder. Nothing is emitted on the front end so a failing feed
     * never leaves a broken shell on a live page.
     */
    function purdue_calendar_embed_notice(string $message): string
    {
        if (!is_admin() && !defined('REST_REQUEST')) {
            return '';
        }

        return sprintf(
            '<div class="purdue-calendar-embed__notice">%s</div>',
            esc_html($message)
        );
    }
}

$mode = in_array($attributes['mode'] ?? 'feed', ['feed', 'widget'], true)
    ? $attributes['mode']
    : 'feed';

/* -------------------------------------------------------------------------
 * Widget mode: reconstruct the Localist embed from the pasted snippet.
 * ---------------------------------------------------------------------- */

if ($mode === 'widget') {
    $containerId = 'purdue-calendar-widget-' . wp_generate_uuid4();
    $embed = WidgetEmbed::fromUrl((string) ($attributes['widgetUrl'] ?? ''), $containerId)
        ?? WidgetEmbed::fromSnippet((string) ($attributes['widgetSnippet'] ?? ''), $containerId);

    if ($embed === null) {
        echo purdue_calendar_embed_notice(
            __('Paste a Localist widget embed snippet to display events.', 'purdue-calendar-embed')
        );
        return;
    }

    wp_enqueue_script(
        'purdue-calendar-widget-' . md5($embed->scriptUrl),
        $embed->scriptUrl,
        [],
        null,
        ['strategy' => 'defer', 'in_footer' => true]
    );

    printf(
        // get_block_wrapper_attributes() returns an already-escaped attribute string.
        '<div %s><div id="%s" class="localist-widget"></div></div>',
        get_block_wrapper_attributes(['class' => 'purdue-calendar-embed']),
        esc_attr($embed->containerId)
    );
    return;
}

/* -------------------------------------------------------------------------
 * Feed mode.
 * ---------------------------------------------------------------------- */

$layout = in_array($attributes['layout'] ?? 'list', ['list', 'cards'], true)
    ? $attributes['layout']
    : 'list';

$url = (new FeedUrl())->build(
    (string) ($attributes['feedUrl'] ?? FeedUrl::DEFAULT),
    FeedUrl::parseEventTypes((string) ($attributes['eventTypes'] ?? '')),
    (string) ($attributes['experience'] ?? '')
);

$result = (new FeedClient())->fetch($url);

if (is_wp_error($result)) {
    echo purdue_calendar_embed_notice(
        sprintf(
            /* translators: %s: error message from the feed request. */
            __('Could not load events: %s', 'purdue-calendar-embed'),
            $result->get_error_message()
        )
    );
    return;
}

$events = (new FeedFilter())->apply(
    $result->events,
    (int) ($attributes['count'] ?? 5),
    (int) ($attributes['windowDays'] ?? 90),
    (bool) ($attributes['hidePast'] ?? true)
);

if ($events === []) {
    echo purdue_calendar_embed_notice(
        __('No upcoming events matched these settings.', 'purdue-calendar-embed')
    );
    return;
}

$showImage = (bool) ($attributes['showImage'] ?? true);
$showDescription = (bool) ($attributes['showDescription'] ?? true);
$showCategory = (bool) ($attributes['showCategory'] ?? true);

$wrapper = get_block_wrapper_attributes([
    'class' => 'purdue-calendar-embed purdue-calendar-embed--' . $layout,
]);

$dateFormat = (string) get_option('date_format', 'F j, Y');
$timeFormat = (string) get_option('time_format', 'g:i a');

?>
<div <?php echo $wrapper; // phpcs:ignore -- already escaped by core. ?>>
    <ul class="purdue-calendar-embed__list">
        <?php foreach ($events as $event) : ?>
            <?php
            /** @var Event $event */
            $category = $showCategory ? $event->primaryCategory() : '';
            ?>
            <li class="purdue-calendar-embed__item">
                <?php if ($showImage && $event->imageUrl !== '') : ?>
                    <div class="purdue-calendar-embed__media">
                        <img
                            class="purdue-calendar-embed__image"
                            src="<?php echo esc_url($event->imageUrl); ?>"
                            alt=""
                            loading="lazy"
                            decoding="async"
                        />
                    </div>
                <?php endif; ?>

                <div class="purdue-calendar-embed__body">
                    <?php if ($event->startsAt !== null) : ?>
                        <time
                            class="purdue-calendar-embed__date"
                            datetime="<?php echo esc_attr($event->startsAt->format(DATE_ATOM)); ?>"
                        >
                            <?php
                            echo esc_html(
                                wp_date($dateFormat . ' · ' . $timeFormat, $event->startsAt->getTimestamp())
                            );
                            ?>
                        </time>
                    <?php endif; ?>

                    <h3 class="purdue-calendar-embed__title">
                        <a class="purdue-calendar-embed__link" href="<?php echo esc_url($event->permalink); ?>">
                            <?php echo esc_html($event->name); ?>
                        </a>
                    </h3>

                    <?php if ($category !== '') : ?>
                        <span class="purdue-calendar-embed__category"><?php echo esc_html($category); ?></span>
                    <?php endif; ?>

                    <?php if ($showDescription && $event->description !== '') : ?>
                        <p class="purdue-calendar-embed__description">
                            <?php echo esc_html(wp_trim_words($event->description, 28)); ?>
                        </p>
                    <?php endif; ?>
                </div>
            </li>
        <?php endforeach; ?>
    </ul>

    <?php if ($result->isStale) : ?>
        <?php
        echo purdue_calendar_embed_notice(
            __('Showing cached events — the Localist feed is currently unavailable.', 'purdue-calendar-embed')
        );
        ?>
    <?php endif; ?>
</div>
