# Purdue Calendar Embed

A WordPress block that renders Localist events from the [events.purdue.edu](https://events.purdue.edu)
RSS feed, with the Localist widget embed available as a fallback rendering mode.

## Getting started

```bash
composer install
npm install
npm run build
```

For local development against a throwaway WordPress install:

```bash
npm run env start     # requires Docker (or Colima)
npm start             # rebuild on change
```

## The two modes

The block has one setting that matters more than the rest: **Mode**.

### Feed mode (default)

PHP fetches `calendar.xml`, normalizes each item, caches the result, and server-renders the
list. This is the mode to use — the markup is yours, it is cacheable, and it works with the
site's own styles.

### Widget mode

You paste the embed snippet that Localist's widget builder generates and the block reproduces
it. Use this when you need something the RSS feed cannot express — most often `days` and `num`,
which `/widget/view` honours and `calendar.xml` ignores.

The pasted HTML is never stored or echoed verbatim. The block extracts the script URL, checks it
against a host allowlist (`events.purdue.edu`, `*.localist.com`, HTTPS, `/widget/view` path only)
and rebuilds the markup itself, rewriting the `container` ID so two widgets can share a page.

## What the RSS feed can and cannot do

This is the part worth reading before filing a bug.

`calendar.xml` mirrors the **calendar page's** query string, because the Localist theme builds
it with `{% feed_url xml %}`. So it accepts the calendar page's filters and silently ignores the
widget builder's:

| Parameter | Honoured by `calendar.xml`? |
| --- | --- |
| `event_types[]` (numeric ID), `experience`, `match` | Yes |
| `days`, `num`, `pp`, `page`, `card_size` | **No** — ignored entirely |
| `/calendar/week.xml`, `/calendar/2026/9/1.xml` | **No** — both redirect to plain `calendar.xml` |

Because `days` and `num` do nothing, the **Days ahead** and **Number of events** controls are
applied in PHP after fetching. The feed itself always returns roughly eleven months of events.

The feed also does not carry:

- **end times** — events render with a start time only,
- **an all-day flag**,
- **a structured venue** — the location is only present inside the title text, and splitting on
  `" at "` misfires on names like *"Men's Golf - Purdue at NCAA Nationals"*, so the title is left
  intact.

The `xCal` namespace is declared on the channel but no `xCal:` elements are ever emitted.
Getting these fields would mean extending `calendar/_feed_event.html.liquid` in the
[`localist-theme`](https://github.com/Purdue/localist-theme) repo, which controls the RSS
`<description>` body. That is deliberately out of scope for now.

## Caching

Caching is a correctness requirement, not an optimisation. The unfiltered feed is ~1.7 MB across
750+ items, and the origin rate-limits hard — it returns `x-ratelimit-remaining: 0` and HTTP 429
under even light polling.

So the block caches **normalized events**, not raw XML:

- a fresh copy for 10 minutes, matching the origin's own `cache-control: max-age=600`,
- a stale copy for 24 hours, served whenever the origin errors, rate-limits, or returns nothing.
  When that happens the editor sees a notice; the front end still renders events.

Adjust the fresh TTL with:

```php
add_filter( 'purdue_calendar_embed_cache_ttl', fn() => 5 * MINUTE_IN_SECONDS );
```

## Finding event type IDs

The **Event type IDs** field takes numeric Localist IDs, not names. The quickest way to find one
is to apply the filter on events.purdue.edu and read it out of the URL — for example
`event_types[]=39882150467792` is Sporting Events.

## Development

```bash
composer lint       # PSR-12 via phpcs
composer lint:fix
composer analyze    # PHPStan level 5, with WordPress stubs
npm run build
```

PHP follows **PSR-12 with PSR-4 autoloading**, matching the other Purdue WordPress repos rather
than WordPress Coding Standards.

## Licence

GPL-3.0-or-later. See [LICENSE](LICENSE).
