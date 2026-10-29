import { __ } from "@wordpress/i18n";
import { useBlockProps, InspectorControls } from "@wordpress/block-editor";
import {
  Notice,
  PanelBody,
  Placeholder,
  RangeControl,
  SelectControl,
  TextControl,
  TextareaControl,
  ToggleControl,
} from "@wordpress/components";
import { useState } from "@wordpress/element";
import ServerSideRender from "@wordpress/server-side-render";

import metadata from "./block.json";
import icon from "./icon";

const MODES = [
  { label: __("RSS feed", "purdue-calendar-embed"), value: "feed" },
  {
    label: __("Localist widget embed", "purdue-calendar-embed"),
    value: "widget",
  },
];

const EXPERIENCES = [
  { label: __("Any", "purdue-calendar-embed"), value: "" },
  { label: __("In person", "purdue-calendar-embed"), value: "inperson" },
  { label: __("Hybrid", "purdue-calendar-embed"), value: "hybrid" },
  { label: __("Virtual", "purdue-calendar-embed"), value: "virtual" },
];

const LAYOUTS = [
  { label: __("List", "purdue-calendar-embed"), value: "list" },
  { label: __("Cards", "purdue-calendar-embed"), value: "cards" },
];

// Event titles are headings, so the level has to fit the page's outline (WCAG 1.3.1).
const HEADING_LEVELS = [2, 3, 4, 5, 6].map((level) => ({
  label: `H${level}`,
  value: String(level),
}));

const ALLOWED_HOSTS = ["events.purdue.edu", "localist.com"];
const WIDGET_PATH = "/widget/view";

/**
 * Pulls the widget URL out of whatever was pasted.
 *
 * Only the URL is kept. The snippet itself cannot be stored: WordPress runs block
 * attributes through KSES on save and strips <script> out of them, so the tag never
 * reaches the front end. Bare URLs are accepted too, for anyone who already has one.
 */
function extractWidgetUrl(input) {
  const text = (input || "").trim();

  if (text === "") {
    return "";
  }

  let url = text;

  if (text.includes("<")) {
    // A DOMParser document is inert -- nothing here runs or loads.
    const script = new window.DOMParser()
      .parseFromString(text, "text/html")
      .querySelector("script[src]");

    if (!script) {
      return "";
    }

    url = script.getAttribute("src").trim();
  }

  // The builder emits protocol-relative URLs in places; the server requires https.
  return url.startsWith("//") ? `https:${url}` : url;
}

/**
 * Mirrors the allowlist in WidgetEmbed so the editor can say why a paste was rejected.
 * The server-side check is the one that counts.
 */
function isAllowedWidgetUrl(url) {
  let parsed;

  try {
    parsed = new window.URL(url);
  } catch {
    return false;
  }

  return (
    parsed.protocol === "https:" &&
    parsed.pathname === WIDGET_PATH &&
    ALLOWED_HOSTS.some(
      (host) =>
        parsed.hostname === host || parsed.hostname.endsWith(`.${host}`),
    )
  );
}

export default function Edit({ attributes, setAttributes }) {
  const {
    mode,
    feedUrl,
    eventTypes,
    experience,
    count,
    windowDays,
    hidePast,
    layout,
    headingLevel,
    showImage,
    showDescription,
    showCategory,
    widgetUrl,
  } = attributes;

  const blockProps = useBlockProps();
  const isWidget = mode === "widget";

  // The paste is scratch input, not saved state -- after a reload the box is empty and
  // the stored URL below it is what tells you the block is configured.
  const [snippet, setSnippet] = useState("");

  const onSnippetChange = (value) => {
    setSnippet(value);

    const extracted = extractWidgetUrl(value);

    setAttributes({
      widgetUrl: isAllowedWidgetUrl(extracted) ? extracted : "",
    });
  };

  return (
    <>
      <InspectorControls>
        <PanelBody title={__("Source", "purdue-calendar-embed")}>
          <SelectControl
            label={__("Mode", "purdue-calendar-embed")}
            value={mode}
            options={MODES}
            onChange={(value) => setAttributes({ mode: value })}
            help={__(
              "Widget mode embeds Localist directly. Use it when you need day or result limits, which the RSS feed ignores.",
              "purdue-calendar-embed",
            )}
            __nextHasNoMarginBottom
          />

          {isWidget && (
            <>
              <TextareaControl
                label={__("Widget embed snippet", "purdue-calendar-embed")}
                value={snippet}
                onChange={onSnippetChange}
                help={__(
                  "Paste the snippet from the Localist widget builder, or just the widget URL. Only the URL is saved — WordPress strips script tags out of saved content.",
                  "purdue-calendar-embed",
                )}
                rows={6}
                __nextHasNoMarginBottom
              />

              {widgetUrl && (
                <Notice status="success" isDismissible={false}>
                  {__("Using widget URL:", "purdue-calendar-embed")}{" "}
                  <code>{widgetUrl}</code>
                </Notice>
              )}

              {!widgetUrl && snippet.trim() !== "" && (
                <Notice status="error" isDismissible={false}>
                  {__(
                    "No usable widget URL found. It must be an https script URL on events.purdue.edu or localist.com with the /widget/view path.",
                    "purdue-calendar-embed",
                  )}
                </Notice>
              )}
            </>
          )}

          {!isWidget && (
            <>
              <TextControl
                label={__("Feed URL", "purdue-calendar-embed")}
                value={feedUrl}
                onChange={(value) => setAttributes({ feedUrl: value })}
                __nextHasNoMarginBottom
              />
              <TextControl
                label={__("Event type IDs", "purdue-calendar-embed")}
                value={eventTypes}
                onChange={(value) => setAttributes({ eventTypes: value })}
                help={__(
                  "Comma-separated numeric Localist event type IDs, e.g. 39882150467792 for Sporting Events.",
                  "purdue-calendar-embed",
                )}
                __nextHasNoMarginBottom
              />
              <SelectControl
                label={__("Experience", "purdue-calendar-embed")}
                value={experience}
                options={EXPERIENCES}
                onChange={(value) => setAttributes({ experience: value })}
                __nextHasNoMarginBottom
              />
            </>
          )}
        </PanelBody>

        {!isWidget && (
          <>
            <PanelBody
              title={__("Filtering", "purdue-calendar-embed")}
              initialOpen={false}
            >
              <RangeControl
                label={__("Number of events", "purdue-calendar-embed")}
                value={count}
                onChange={(value) => setAttributes({ count: value })}
                min={1}
                max={50}
                __nextHasNoMarginBottom
              />
              <RangeControl
                label={__("Days ahead", "purdue-calendar-embed")}
                value={windowDays}
                onChange={(value) => setAttributes({ windowDays: value })}
                min={1}
                max={365}
                help={__(
                  "Applied after fetching — the Localist feed always returns roughly eleven months.",
                  "purdue-calendar-embed",
                )}
                __nextHasNoMarginBottom
              />
              <ToggleControl
                label={__("Hide past events", "purdue-calendar-embed")}
                checked={hidePast}
                onChange={(value) => setAttributes({ hidePast: value })}
                __nextHasNoMarginBottom
              />
            </PanelBody>

            <PanelBody
              title={__("Display", "purdue-calendar-embed")}
              initialOpen={false}
            >
              <SelectControl
                label={__("Layout", "purdue-calendar-embed")}
                value={layout}
                options={LAYOUTS}
                onChange={(value) => setAttributes({ layout: value })}
                __nextHasNoMarginBottom
              />
              <SelectControl
                label={__("Event title heading level", "purdue-calendar-embed")}
                value={String(headingLevel)}
                options={HEADING_LEVELS}
                onChange={(value) =>
                  setAttributes({ headingLevel: Number(value) })
                }
                help={__(
                  "Pick the level that follows the heading above the block, so the page outline stays in order.",
                  "purdue-calendar-embed",
                )}
                __nextHasNoMarginBottom
              />
              <ToggleControl
                label={__("Show image", "purdue-calendar-embed")}
                checked={showImage}
                onChange={(value) => setAttributes({ showImage: value })}
                __nextHasNoMarginBottom
              />
              <ToggleControl
                label={__("Show description", "purdue-calendar-embed")}
                checked={showDescription}
                onChange={(value) => setAttributes({ showDescription: value })}
                __nextHasNoMarginBottom
              />
              <ToggleControl
                label={__("Show category", "purdue-calendar-embed")}
                checked={showCategory}
                onChange={(value) => setAttributes({ showCategory: value })}
                __nextHasNoMarginBottom
              />
            </PanelBody>
          </>
        )}
      </InspectorControls>

      <div {...blockProps}>
        {isWidget ? (
          <Placeholder
            icon={icon}
            label={__("Localist widget", "purdue-calendar-embed")}
            instructions={
              widgetUrl
                ? __(
                    "The Localist widget renders on the front end. It is not previewed here so its script does not run in the editor.",
                    "purdue-calendar-embed",
                  )
                : __(
                    "Paste a Localist widget embed snippet in the block settings.",
                    "purdue-calendar-embed",
                  )
            }
          />
        ) : (
          <ServerSideRender block={metadata.name} attributes={attributes} />
        )}
      </div>
    </>
  );
}
