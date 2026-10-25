# Social Icons Block

**Status:** v1.18 · issue #1023
**Block names:** `artisanpack/social-links` (the list) and `artisanpack/social-link` (one icon)

The Social Icons block links to a site's own profiles. It is a port of WordPress's `core/social-links` / `core/social-link`, and it outputs the same markup, so the bundled block-library CSS styles it unchanged.

---

## Block usage

Insert **Social Icons** from the **Widgets** category, then add icons. Each of the 48 services is its own inserter variation. Click an icon to set its URL in a popover. The block settings add an optional text label and a link relation.

Services: `wordpress`, `fivehundredpx` (500px), `amazon`, `bandcamp`, `behance`, `bluesky`, `chain` (Link), `codepen`, `deviantart`, `discord`, `dribbble`, `dropbox`, `etsy`, `facebook`, `feed` (RSS Feed), `flickr`, `foursquare`, `goodreads`, `google`, `github`, `gravatar`, `instagram`, `lastfm` (Last.fm), `linkedin`, `mail`, `mastodon`, `meetup`, `medium`, `patreon`, `pinterest`, `pocket`, `reddit`, `skype`, `snapchat`, `soundcloud`, `spotify`, `telegram`, `threads`, `tiktok`, `tumblr`, `twitch`, `twitter`, `vimeo`, `vk`, `whatsapp`, `x`, `yelp`, `youtube`.

```html
<!-- wp:artisanpack/social-links {"openInNewTab":true,"className":"is-style-pill-shape"} -->
<ul class="wp-block-artisanpack-social-links is-style-pill-shape">
<!-- wp:artisanpack/social-link {"url":"https://github.com/example","service":"github"} /-->
<!-- wp:artisanpack/social-link {"url":"hello@example.com","service":"mail"} /-->
</ul>
<!-- /wp:artisanpack/social-links -->
```

---

## Settings on the list (`artisanpack/social-links`)

| Attribute | Type | Notes |
|-----------|------|-------|
| `size` | `string` | `has-small-icon-size`, `has-normal-icon-size`, `has-large-icon-size` or `has-huge-icon-size`. Any other value is dropped. |
| `showLabels` | `boolean` (default `false`) | Shows each icon's text next to it. Otherwise the text is screen-reader only. |
| `openInNewTab` | `boolean` (default `false`) | Opens every icon's link in a new tab and adds `noopener nofollow` to its `rel`. |
| `iconColorValue`, `iconBackgroundColorValue` | `string` | Icon color and background. |
| `iconBorderColorValue`, `iconBorderWidth`, `iconBorderStyle` | `string` | Icon border. `iconBorderStyle` is one of `none`, `solid`, `dashed`, `dotted`, `double`. |
| `iconBorderRadius`, `iconPadding` | `string` | Icon corner radius and padding. |
| `iconHoverColorValue`, `iconHoverBackgroundColorValue`, `iconHoverBorderColorValue` | `string` | Hover (and keyboard focus) colors. |

Styles: **Default**, **Logos Only** and **Pill Shape**. Unlike WordPress, Logos Only keeps the background settings.

The block also supports `align` (left, center, right), `anchor`, flex layout justification, block spacing, margin, padding, background color and border.

### How the per-icon styles render

The per-icon settings are set once on the list. They render on the `<ul>` as `--ap-social-links-*` custom properties, each switched on by a `has-icon-*` class (for example `has-icon-color` with `--ap-social-links-color`). The social-icons stylesheet applies them to every icon, including hover and focus. Every value goes through the shared CSS-value check, and any value that fails it is dropped along with its class.

---

## One icon (`artisanpack/social-link`)

| Attribute | Type | Notes |
|-----------|------|-------|
| `service` | `string` | A service slug from the list above. An unknown slug still renders, with the generic link glyph. An empty or invalid slug renders as `chain`. |
| `url` | `string` | The profile URL. An icon with no URL renders nothing on the front end. |
| `label` | `string` | Text for the icon. Defaults to the service name. |
| `rel` | `string` | Link relation tokens. `openInNewTab` on the list adds `noopener nofollow`. |

The list passes `openInNewTab` and `showLabels` to its icons through block context (`artisanpack/socialLinksOpenInNewTab`, `artisanpack/socialLinksShowLabels`). On the front end, the renderers stamp those settings onto each icon as `_veSocialLinks` and overwrite any stored value, so saved content can't set them.

### How the URL is rendered

- A bare address on the **Mail** service, such as `hello@example.com`, becomes `mailto:hello@example.com`.
- A bare domain, such as `twitter.com/example`, gets `https://`, as in WordPress. A URL with a scheme, a fragment (`#contact`), a query or a relative path (`/feed`) is left as typed.
- Every URL then goes through the shared URL sanitizer, so `javascript:`, `data:` and `vbscript:` links render nothing.

The Blade, React and Vue renderers apply the same rules. The `social-links-*` fixtures in `packages/renderer-markup-parity` cover them.

---

## Icons

The glyphs are Font Awesome Free (CC BY 4.0), plus Simple Icons (CC0 1.0) for Gravatar; see `NOTICE.md`. One list in `scripts/sync-social-link-services.mjs` generates the four service registries (editor, Blade, React, Vue):

```bash
npm run sync:social-links
```

A test fails if the four registries drift apart.

## Related

- [[blocks]] — block library overview
- [[renderers]] — the Blade, React and Vue renderers
