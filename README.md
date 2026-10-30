# Purdue Calendar Embed

A WordPress block that renders Localist events from the [events.purdue.edu](https://events.purdue.edu)
RSS feed, with the Localist widget embed available as a fallback rendering mode.

Requires WordPress 6.5+ and PHP 8.1+.

## Installing

Download `purdue-calendar-embed-<version>.zip` from the
[latest release](https://github.com/Purdue/purdue-calendar-embed/releases/latest) and install it
with **Plugins → Add New → Upload Plugin**, or unzip it into `wp-content/plugins/`. Then activate
**Purdue Calendar Embed**.

You can also `git clone` the repository into `wp-content/plugins/purdue-calendar-embed/`. `build/`
is committed and there are no runtime Composer dependencies, so no build tools are needed on the
server either way.

The folder name matters. WordPress identifies the plugin as `purdue-calendar-embed/index.php`, so a
copy in a different folder (for example the `purdue-calendar-embed-main` folder that GitHub's
**Download ZIP** creates) is treated as a different plugin. The release zip already has the right
folder name.

Sites that are not managed with Composer don't update automatically. To upgrade, install the new
release zip over the old one.

## Support

Report bugs and request features in
[GitHub issues](https://github.com/Purdue/purdue-calendar-embed/issues). For questions, email
Purdue Marketing and Communications at
[digital-marketing@groups.purdue.edu](mailto:digital-marketing@groups.purdue.edu).

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

## Using the block

Activate the plugin on the site. In the block inserter the block is **Purdue Calendar**, under
**Widgets** (not the theme's **Purdue** category); searching "calendar", "events" or "localist"
also finds it.

The step-by-step guide for site editors is the **Website Integration → WordPress** section of
Brand Studio's [Events Calendar](https://www.purdue.edu/brand-studio/toolbox/event-calendar/)
page. It covers widget mode only for now. Keep it in step with this README when the editor
experience changes.

Feed-mode times are shown in the site's timezone (**Settings → General**). A site left on UTC shows
every event four or five hours late.

Event titles are headings. Set **Display → Event title heading level** to the level that follows
the heading above the block, so the page's heading outline stays in order. The default is H3.

### With purdue-home-theme

The block needs nothing from `purdue-home-theme`, Purdue's WordPress theme. The plugin registers
`purdue/calendar` and ships its own stylesheet, so it works under any theme.

Do not add `calendar` to the theme's `$purdueBlocks` list in `inc/blocks.php`. That list is for
blocks built inside the theme, and the theme deregisters every `purdue-<block>-*` asset handle in
it. Those include `purdue-calendar-style` and `purdue-calendar-editor-script`, which are this
plugin's handles, so the block would lose its styles on the front end and its script in the
editor. For the same reason, the theme must never get a block of its own named `calendar`.

## The two modes

The block has one setting that matters more than the rest: **Mode**.

### Feed mode (default)

PHP fetches `calendar.xml`, normalizes each item, caches the result, and server-renders the
list. This is the mode to use — the markup is yours, it is cacheable, and it works with the
site's own styles.

### Widget mode

You paste the embed snippet that Localist's widget builder generates (or just the widget URL)
and the block reproduces it. Use this when you need something the RSS feed cannot express — most
often `days` and `num`, which `/widget/view` honours and `calendar.xml` ignores.

Only the script URL is saved, in the `widgetUrl` attribute. The paste box is not saved: after a
reload it is empty, and the **Using widget URL** notice below it shows what the block will render.
The snippet cannot be stored because WordPress runs block attributes through KSES on save and
strips `<script>` from them. The Purdue sites set `DISALLOW_UNFILTERED_HTML`, so this applies to
administrators too. KSES also rewrites `&` to `&amp;` in the saved URL, and the server decodes it
before use.

On render, the server checks the URL again against a host allowlist (`events.purdue.edu`,
`*.localist.com`, HTTPS, `/widget/view` path only; protocol-relative `//` URLs are treated as
HTTPS). It then rebuilds the markup itself and replaces the `container` parameter, so two widgets
can share a page. Blocks saved before `widgetUrl` existed fall back to the old `widgetSnippet`
attribute, which only works where the `<script>` tag survived the save.

### When nothing renders

Both modes render nothing on the front end when they fail: a rejected widget URL, a feed error with
no cached copy, or no events matching the filters. The reason is shown only in the editor, so check
the block there first.

A feed error also marks the page as not cacheable (`DONOTCACHEPAGE`), so page caches such as WP
Super Cache don't keep the empty render after the feed recovers. Pages cached by a version without
this need their page cache cleared once.

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
applied in PHP after fetching. The feed itself always returns about a year of events.

**Hide past events** hides any event whose start time has passed, including events in progress.
Because the feed has no all-day flag (see below), all-day events start at midnight and disappear
from the list on their own day.

The feed also does not carry:

- **end times** — events render with a start time only,
- **an all-day flag**,
- **a structured venue** — the location is only present inside the title text, and splitting on
  `" at "` misfires on names like *"Men's Golf - Purdue at NCAA Nationals"*, so the title is left
  intact.

The `xCal` namespace is declared on the channel but no `xCal:` elements are ever emitted.
Getting these fields would mean extending `calendar/_feed_event.html.liquid` in Purdue's Localist
theme (`localist-theme`, maintained by Marketing and Communications), which controls the RSS
`<description>` body. That is deliberately out of scope for now.

## Caching

Caching is a correctness requirement, not an optimisation. The unfiltered feed is ~2 MB across
about 1,000 items, and the origin rate-limits hard — it returns `x-ratelimit-remaining: 0` and HTTP 429
under even light polling.

So the block caches **normalized events**, not raw XML:

- a fresh copy for 10 minutes, matching the origin's own `cache-control: max-age=600`,
- a stale copy for 24 hours, served whenever the origin errors, rate-limits, or returns nothing.
  When that happens the editor sees a notice; the front end still renders events.

After a failed fetch the block leaves the origin alone for one minute and serves the stale copy,
or nothing, in the meantime. A failure can take the full 15-second request timeout, and failed
renders are kept out of page caches, so without the pause every uncached page view during an
outage would wait out the timeout again and add to the rate limiting.

The first fetch on a new site has no stale copy to fall back on. The ~2 MB download can hit the
timeout, and the block then renders nothing until a later request succeeds.

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

`composer analyze` runs PHPStan in parallel worker processes over a local TCP port. Where
binding a port is blocked (some sandboxes), run `vendor/bin/phpstan analyse --memory-limit=1G
--debug` instead.

`build/` is committed, because it is what ships. Run `npm run build` and commit its output with
every change under `src/blocks/`. The block is registered from `build/`, so a stale build ships
stale code. CI rebuilds on every push and pull request, and fails if the committed `build/`
differs.

Changes are reviewed by `@Purdue/marketing` (see `.github/CODEOWNERS`).

## Deploying

Deploy a release as described in [Installing](#installing). `vendor/` is optional on the server.
`index.php` uses it when present, and otherwise autoloads the plugin's own classes from `src/`,
which are all it needs at runtime.

Composer-managed sites that use Purdue's Composer repository (`https://purdue.github.io`) can
install it with `composer require purdue/purdue-calendar-embed`. That needs `composer/installers`
in the site's project, which places it in `wp-content/plugins/purdue-calendar-embed/`.

## Releasing

1. Set the same `X.Y.Z` in four places: `Version:` and `PURDUE_CALENDAR_EMBED_VERSION` in
   `index.php`, `version` in `src/blocks/calendar/block.json`, and `version` in `package.json`
   (run `npm version X.Y.Z --no-git-tag-version` so `package-lock.json` follows).
2. Run `npm run build` and commit everything as `Version Bump X.Y.Z` on `main`.
3. Tag `X.Y.Z` and push the tag. Composer reads versions from tags.
4. Build the release zip from the tag. `.gitattributes` keeps development files out of it:

   ```bash
   git archive --format=zip --prefix=purdue-calendar-embed/ \
     -o purdue-calendar-embed-X.Y.Z.zip X.Y.Z
   ```

5. Create a GitHub release for the tag and attach the zip.
6. Rebuild the package on purdue.github.io. Follow the build guide in
   [`Purdue/satis-theme`](https://github.com/Purdue/satis-theme) (Purdue access required), scoping
   the build to this repo by adding
   `--repository-url 'git@github.com:Purdue/purdue-calendar-embed/'` to its build command. This
   repo is public, so the scoped build needs no SSH key or GitHub token.

## Licence

GPL-3.0-or-later, as declared in the plugin header.
