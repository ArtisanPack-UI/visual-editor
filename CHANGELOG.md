# ArtisanPack UI Visual Editor — Changelog

All notable changes to this project are documented in this file.
The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.1.0/);
this project adheres to [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

## [Unreleased]

## [1.17.0] - 2026-10-23

### Added

- **Batched binding resolves** ([#1009](https://github.com/ArtisanPack-UI/visual-editor/issues/1009)). `POST /visual-editor/api/bindings/resolve` also accepts `{ items: [{ key, attrs, bindings, context }] }` (at most 100 items) and answers `{ results: [...] }` in request order: `{ key, values }` per item, or `{ key, error, message }` with `invalid_item` or `forbidden`, without failing the rest of the batch. Items on the same record share one lookup and one `view` policy check. A batch that isn't a list or is over the cap fails with 422. The single-block payload is unchanged. The bindings API exports `resolveBindingsBatch()`.
- **Batched block previews** ([#1010](https://github.com/ArtisanPack-UI/visual-editor/issues/1010)). `POST /visual-editor/api/blocks/preview` also accepts `{ items: [{ key, name, attributes, innerBlocks, bindings, context }] }` (at most 25 items) and answers `{ results: [...] }`: `{ key, name, html }` per item, or `{ key, name, error, message, status }` where `status` is what the single-block request would have returned. A context record shared by several items is loaded and its `view` policy checked once. The single-block payload is unchanged.
- **Batched icon SVG lookups** ([#1012](https://github.com/ArtisanPack-UI/visual-editor/issues/1012)). `GET /visual-editor/api/icons/svg` also accepts `?icons=set:name,…` (deduplicated, at most 100) and answers a `{ "set:name": svg }` map, with `null` for unknown or malformed icons. The `Cache-Control: private, max-age=86400` header is sent only when every icon resolved. The `set` + `name` form is unchanged.

### Changed

- **Bound blocks resolve together** ([#1009](https://github.com/ArtisanPack-UI/visual-editor/issues/1009)). Every bound block used to post its own `bindings/resolve` request, so inserting a pattern with dozens of bound blocks sent dozens of requests at once. Bound blocks now share one 250ms debounce (with a 1s ceiling, so a block being typed into can't hold the others back) and go out as one batched request. Identical payloads share one item, and results are cached for the editor session, so remounts and undo/redo don't refetch. Failures aren't cached.
- **Server-rendered block previews are batched, cached and lazy** ([#1010](https://github.com/ArtisanPack-UI/visual-editor/issues/1010)). Blocks previewed through `ServerSideRender` (including the synthesized edit of a block registered with only a PHP render callback) used to send one `blocks/preview` request each. Previews requested together now go out as one batched request, identical blocks share one render, and rendered HTML is cached for the editor session. A block doesn't request its preview until it comes within 400px of the viewport.
- **Distinct icons share one request** ([#1012](https://github.com/ArtisanPack-UI/visual-editor/issues/1012)). Building on the 1.16.0 cache, which shared requests only for identical icons, the Icon block and inline-icon format now queue every cache miss for one microtask and fetch them in one batched request. A lone miss still uses the single-icon form, so its browser cache entry is reused.

### Fixed

- **Saved pages no longer show "Unsaved changes" on load** ([#1011](https://github.com/ArtisanPack-UI/visual-editor/issues/1011)). The save status stayed at its initial `idle` after the content loaded, and the top bar labels `idle` as "Unsaved changes", so a page that wrote nothing on load looked dirty until its first save. A successful load now sets the status to `saved`. Queuing a block edit marks the editor dirty straight away, so the indicator never reads "Saved" over a pending edit during the autosave debounce.

## [1.16.0] - 2026-10-22

### Added

- **Record list and lookup endpoints for any resource** ([#1003](https://github.com/ArtisanPack-UI/visual-editor/issues/1003)). New `GET /visual-editor/api/{resource}` and `GET /visual-editor/api/{resource}/{id}` (`ResourceRecordController`) serve records of any resource in the resource map in a minimal `{ id, title, slug }` shape for record pickers. The list takes `search` (matched against `title` when the table has one), `per_page` (default 25, capped at 100) and `include` (a comma-separated id list for batched lookups, parsed by the new `Support\RecordIdList`; non-numeric ids are dropped for integer keys, and an `include` with no valid ids returns no records rather than the unfiltered list). Every record is checked against the model's `view` policy: the list leaves out records the user can't view and the single lookup answers 403. `ResourceResolver::query()` returns the scoped query both endpoints use. The `posts` and `pages` adapters keep their own routes, which now accept `search` and `include` too. See [The resource map](docs/content-model.md#2-the-resource-map).

### Changed

- **Single-content blocks preview the picked entry** ([#1003](https://github.com/ArtisanPack-UI/visual-editor/issues/1003)). The canvas now shows the picked entry's real title and content for every registered content type, not just posts and pages, using one batched `POST /query/resolve` request per content type on the page however many blocks it has. The entry picker is mounted, and lists records, only while the block is selected. Inside single-content the entry's title is now a read-only preview (posts and pages were editable inline), and an entry the resolver doesn't return (a draft, a non-public type, or a page with no query runtime bound) falls back to the inner blocks' placeholders, as on the front end.
- **Generic `GET` routes are registered last.** The new record routes sit after every other package route, so static paths still win, but a `GET` to a POST-only path (e.g. `blocks/preview`) now answers 404 instead of 405, and a host route added under `/visual-editor/api` after the package routes is shadowed by them.

### Fixed

- **Single-content blocks no longer fetch on mount per instance** ([#1003](https://github.com/ArtisanPack-UI/visual-editor/issues/1003)). Each block requested `GET /{plural}?per_page=50` and `GET /{plural}/{id}` on mount for an inspector that was hidden, and both 404'd for dynamic content types, because only `posts` and `pages` had collection routes. On a page with several such blocks this could fill a small server's PHP-FPM pool. Requests now go through a shared session cache that de-duplicates in-flight calls and batches title lookups into one `?include=` request per collection; 403 and 404 answers stay cached for the session, and network failures are retried.
- **Icon blocks share one SVG request per icon** ([#1004](https://github.com/ArtisanPack-UI/visual-editor/issues/1004)). Every Icon block fetched `GET icons/svg` on its own, so a page with seven copies of an icon sent seven identical requests at once. The Icon block and the inline-icon format now share one memoized lookup per `set` and `name` for the editor session, including in-flight requests; failed lookups are evicted so they can be retried. Successful `icons/svg` responses also send `Cache-Control: private, max-age=86400`, so the browser reuses them across sessions.

## [1.15.4] - 2026-10-20

### Fixed

- **Table rows and columns can be added and removed** ([#999](https://github.com/ArtisanPack-UI/visual-editor/issues/999)). A table's size was fixed once it was created, so removing a row or column meant rebuilding the block. The Table block's toolbar now has an **Edit table** menu with Insert row before / after, Delete row, Insert column before / after and Delete column, which act on the focused cell and are disabled until a cell has focus. Column changes apply to the header, body and footer together so they stay the same width; new header cells are `th` and new rows keep their column's alignment. Deleting the last row or column brings back the Create table placeholder. Saved markup is unchanged. `@wordpress/icons` is now a direct dependency (pinned to 12.2.0, the version already installed).

## [1.15.3] - 2026-10-19

### Added

- **Icon vertical alignment.** The Icon block's toolbar has a vertical alignment control (`verticalAlignment`: `top`, `center` or `bottom`) that positions the icon inside a wrapper stretched taller than the icon, e.g. by a flex Row. The wrapper becomes a column flexbox with an `is-vertically-aligned-{value}` class, in the canvas and on the front end; icons without the attribute render as before, and any other value is dropped.
- **Post Title heading level** ([#994](https://github.com/ArtisanPack-UI/visual-editor/issues/994)). Post Title has a toolbar level dropdown offering Paragraph and H1–H6, limited by `levelOptions` when set. `level: 0` renders a `<p>` in the Blade, React and Vue renderers.
- **Template previews use real entries** ([#993](https://github.com/ArtisanPack-UI/visual-editor/issues/993)). In the site editor, single-entry templates (`single`, `single-post`, `single-post-{slug}`, `single-{type}`, `single-{type}-{slug}`, `page`, `page-{slug}`) preview their Post Title, Post Excerpt and Post Content blocks with the latest matching entry, preferring the one whose slug the template names. A **Preview content** picker in the canvas switches entries. The blocks stay read-only, and templates with no matching entry (or that don't render a single entry) keep their placeholders. `POST /query/resolve` now returns registered custom content types in their own shape through a new `ContentTypeResource`.
- **Title and excerpt saves for custom content types** ([#992](https://github.com/ArtisanPack-UI/visual-editor/issues/992)). New `PUT /visual-editor/api/{resource}/{id}/entry` endpoint (`ResourceEntryController`, `UpdateResourceEntryRequest`) saves `title` and `excerpt` for any resource in the resource map. It is gated by the record's `update` policy and answers 422 when a sent field has no column on the record. The editor uses it for custom entries only when the host doesn't pass `onMetadataChange`, so hosts that persist metadata themselves don't write twice.

### Fixed

- **React and Vue renderers print block supports** ([#920](https://github.com/ArtisanPack-UI/visual-editor/issues/920)). The React and Vue renderers dropped colour, background, spacing, border, typography and dimensions supports on most core blocks. A shared `compileBlockSupports()`, ported from Blade's `BlockSupports::compile()`, now builds them for every core block wrapper (the Cover overlay included), with 19 new parity fixtures locked to Blade's goldens. Every support value is validated before it reaches an inline style in all three renderers, so a stored value can't append extra declarations, and every `background-*` declaration goes to the Cover overlay with its image.
- **Post blocks show the entry being edited** ([#992](https://github.com/ArtisanPack-UI/visual-editor/issues/992)). Post Title, Post Excerpt and Post Content showed placeholder chips for custom content types, and Post Content did for every entry. They now show the edited entry's real values for posts, pages and custom content types. Title and excerpt are editable inline and stay in sync with the document fields in both directions; Post Content shows a read-only preview of the canvas, skipping nested Post Content blocks.
- **Saved titles stick** ([#992](https://github.com/ArtisanPack-UI/visual-editor/issues/992)). A saved title could snap back to its old value after autosave, edits made while a save was in flight were lost, and a rejected metadata save retried in a tight loop. Saved edits are now folded into the stored record, in-flight edits wait for the next save, and a rejected save no longer retries.
- **Bound attributes are locked in the editor** ([#995](https://github.com/ArtisanPack-UI/visual-editor/issues/995)). Controls for an attribute connected to a binding looked editable but every keystroke was replaced by the bound value. Server-block inspector controls for a bound attribute now render disabled with help text naming the source ("Connected to … Disconnect it in the Block bindings panel to edit."), and a Heading whose content is bound renders it read-only, with scripts and event handlers stripped from the bound value.

## [1.15.2] - 2026-10-18

### Added

- **Post Reading Time block** ([#943](https://github.com/ArtisanPack-UI/visual-editor/issues/943)). `artisanpack/post-reading-time` prints a post's estimated reading time as `{prefix}{minutes} {suffix}` (e.g. "6 min read"), in Query loops and on single templates, in all three renderers.
- **Accordion panels can start open** ([#934](https://github.com/ArtisanPack-UI/visual-editor/issues/934)). An Accordion panel has an **Open by default** toggle (`defaultOpen`); Blade opens it only for a boolean `true`, and React and Vue match.
- **Social Share: LinkedIn and copy link** ([#963](https://github.com/ArtisanPack-UI/visual-editor/issues/963)). Social Share Content adds `linkedin` and `copyLink`. The copy-link chip is a plain link without JS; the interactivity script turns it into a button that copies the permalink and announces the result in a polite live region.
- **Query Total block** ([#962](https://github.com/ArtisanPack-UI/visual-editor/issues/962)). `artisanpack/query-total`, WordPress 6.8's Query Total, prints how many results the surrounding Query has. `displayType` is `total-results` (the default: "<strong>25</strong> results") or `range-display` (the current page: "<strong>1–9</strong> of <strong>25</strong> results"); `prefix` and `suffix` wrap the line and `singular` / `plural` (default `result` / `results`) name the results, with the singular used only for a total of one. The numbers are in `<strong>`, and the block has typography, colour, spacing and border supports. `QueryInliner` stamps `_resolvedTotal`, `_resolvedItems`, `_resolvedCurrentPage`, `_resolvedPerPage` and `_resolvedTotalPages` on the block; outside a resolved query it renders nothing. The Blade, React and Vue renderers render it and the `core/query-total` name alike, and the canvas previews the query's resolved count (25 results, 9 per page, until it resolves).
- **Navigation collapse breakpoint** ([#977](https://github.com/ArtisanPack-UI/visual-editor/issues/977)). A `core/navigation` with the `mobile` overlay collapsed below a fixed 600px, which matches none of the breakpoint registry's widths, so a wide header overflowed between 600px and the tablet width. The new `collapseBreakpoint` attribute (`mobile` or `tablet`; empty keeps 600px) moves the collapse point to that registry breakpoint's max-width (767px / 1023px by default). The `<nav>` gains `collapses-at-{key}`, and the Blade, React and Vue renderers emit the rules for each breakpoint once per page in `<style data-ve-navigation-collapse>`, overriding the 600px rules in both directions. The editor adds a **Collapse below** select in a Responsive panel, and the canvas preview collapses at the same width. Navigation without the attribute renders as before.
- **Form block layout** ([#916](https://github.com/ArtisanPack-UI/visual-editor/issues/916)). `artisanpack/form` has a `layout` attribute (`stacked`, the default, or `inline`) with a Layout toggle in the block's settings. Inline adds `data-layout="inline"` and an `is-layout-inline` class to the wrapper so the host's form island and themes can put the submit button on the same row as the fields; the canvas preview does the same. Stacked output is unchanged, and any other value is dropped. The React and Vue renderers don't render this block, so they're unchanged.

### Fixed

- **Query loops keep a saved status off the public site** ([cms-framework #403](https://github.com/ArtisanPack-UI/cms-framework/issues/403)). `QueryInliner` drops a loop's saved `status` before resolving on the front end, passes the loop's `queryId` (so each loop pages on its own query var), and gives Related Posts the host post's `taxQuery`.
- **Post Date honours its format** ([#941](https://github.com/ArtisanPack-UI/visual-editor/issues/941)). `post-date`'s `format` attribute was never read, so every date printed as `F j, Y`. The resolver formats with it (Carbon `translatedFormat()`), React and Vue use a PHP-format port, and the editor has a Date format control.
- **Cover "Use featured image" renders the image** ([#942](https://github.com/ArtisanPack-UI/visual-editor/issues/942)). A Cover with `useFeaturedImage` rendered no image on the front end; the post's featured image now resolves into it.
- **Post Featured Image crops and sizes like core** ([#948](https://github.com/ArtisanPack-UI/visual-editor/issues/948)). A fixed frame (`aspectRatio` or `height`) crops with `scale`, defaulting to `cover`, and `width` / `height` are printed, in all three renderers.
- **Avatar border and radius on the image** ([#952](https://github.com/ArtisanPack-UI/visual-editor/issues/952)). The Blade renderer painted the Avatar's border on the wrapper `div`, so `border-radius: 50%` left a square avatar. It now goes on the `<img>`, as in core.
- **Breakpoint flex keywords beat an arbitrary base** ([#949](https://github.com/ArtisanPack-UI/visual-editor/issues/949)). A breakpoint keyword such as `mobile:ap-basis-auto` lost to an arbitrary base value on the same property (`ap-basis-[280px]`) at every width. The renderers re-emit competing breakpoint keywords after the arbitrary rules.
- **Query Pagination prints nothing for one page** ([#955](https://github.com/ArtisanPack-UI/visual-editor/issues/955)). A Query whose results fit on one page printed a lone "1" in an empty `<nav>` landmark; it now prints nothing, as WordPress does.
- **Query Pagination honours its Justification** ([#956](https://github.com/ArtisanPack-UI/visual-editor/issues/956)). The saved Justification had no effect because the CSS forced a `1fr auto 1fr` grid; the wrapper is now a flex layout with the saved justification.
- **Post Template grids collapse on smaller screens** ([#937](https://github.com/ArtisanPack-UI/visual-editor/issues/937)). A Post Template grid kept its column count at every width; three or more columns now drop to two at ≤1023px and one at ≤767px, as the Grid block does.
- **Post Terms wraps each term** ([#939](https://github.com/ArtisanPack-UI/visual-editor/issues/939)). An unlinked term (any taxonomy without archives) was a bare text node, so themes couldn't style one term; each term is now its own element in all three renderers.
- **Related Posts honours its layout and Block spacing** ([#964](https://github.com/ArtisanPack-UI/visual-editor/issues/964)). Related Posts reads `displayLayout` (list or grid with columns) and Block spacing when its items sit directly under it, reusing the Post Template grid; the editor gains List / Grid toggles and a Columns control.
- **Table cell spans and fixed layout render** ([#971](https://github.com/ArtisanPack-UI/visual-editor/issues/971)). The table renderers dropped `colspan`, `rowspan`, `scope` and `has-fixed-layout`; all three now print them.
- **Image string width and height render** ([#985](https://github.com/ArtisanPack-UI/visual-editor/issues/985)). Image `width` / `height` are strings (`"auto"`, `"30px"`, `"50%"`); the renderers cast them to integers, so `"auto"` printed `width="0"`. They now print the inline style and `is-resized` as the editor's `save()` does; legacy numeric values render as before.
- **Columns subtract their own gap** ([#914](https://github.com/ArtisanPack-UI/visual-editor/issues/914)). Column widths subtracted the global block gap, not the Columns block's own horizontal gap, so rows with a custom gap under- or overflowed.
- **Flex panel wrap and alignment win over the flex baseline** ([#927](https://github.com/ArtisanPack-UI/visual-editor/issues/927)). The layout baseline's `.is-layout-flex { flex-wrap: wrap; align-items: center }` beat the Flex panel's choices on the front end and in the canvas.
- **Breakpoint Position overrides apply over a positioned base** ([#929](https://github.com/ArtisanPack-UI/visual-editor/issues/929)). A responsive Position override never applied when the base position wasn't static (a `static` layer printed nothing, and the inline base won every media query).
- **Grid Item Flex alignment works** ([#930](https://github.com/ArtisanPack-UI/visual-editor/issues/930)). A Grid Item's Flex Layout panel had no effect because the inner-layout presets out-ranked it.
- **Accordions' Block spacing sets the panel gap** ([#933](https://github.com/ArtisanPack-UI/visual-editor/issues/933)). `accordion.css` hard-coded `gap: 0.5rem`; the block's own Block spacing now applies (the default stays 0.5rem).
- **Query resolution requires content-editor access.** `POST /visual-editor/api/query/resolve`, which the editor canvas uses to preview `core/query` and Related Posts blocks, now runs `EnsureContentEditorAccess` like the other content-authoring endpoints, and `QueryResolveRequest::authorize()` checks the same `visual-editor.edit-content` gate. A user who fails the gate gets a JSON 403; guests still get a 401. With `content_access.capability` unset, every authenticated user still passes, so set it (or redefine the gate) to limit the endpoint to authors. Front-end rendering is unchanged: the renderers read the results `QueryInliner` resolves server-side.
- **Menus inside a navigation overlay** ([#973](https://github.com/ArtisanPack-UI/visual-editor/issues/973)). The Blade renderer sent a navigation overlay part's blocks straight to the renderer, skipping the passes the page tree gets, so a Navigation inside the overlay that used a menu location (`__unstableLocation`) or `ref` rendered an empty `<ul>`, and a nested template part or synced pattern wasn't expanded. The overlay part now goes through the same template-part, synced-pattern and navigation passes, resolving menu locations against the theme the page renders with (`<x-ve-blocks default-theme>`, `<x-ve-template theme>` or `BlockRenderer::renderMarkup()`'s `$defaultTheme`; without one, locations stay unresolved as they do on the page). The passes live in a new request-scoped `BlockTreeReferenceResolver` that the components, `renderMarkup()` and the overlay share; `renderMarkup()` now also resolves menu locations when given a theme. A Navigation inside the overlay should set `overlayMenu: "never"` so it doesn't open a drawer of its own; the recursion guard still stops an overlay that references itself. The React and Vue renderers already inline template parts and patterns inside an overlay and never resolve menus themselves (the host supplies resolved navigation items), so they're unchanged.
- **Screen-size visibility reaches phones** ([#974](https://github.com/ArtisanPack-UI/visual-editor/issues/974)). The Screen size rule only offered the legacy min-width keys, each a range starting at 640px or more, so nothing could be hidden below 640px. It now also accepts the desktop-first device keys: `mobile` hides at `@media (max-width:767px)` and `tablet` at `@media (max-width:1023px)` (following the host's Tablet / Mobile widths), stored as `{"screenSize":{"direction":"hide","breakpoints":["mobile"]}}`. The panel offers **Mobile (≤767px)**, **Tablet (≤1023px)** and **Desktop (≥1024px)** (stored as `lg`, `xl`, `2xl`), and lists the legacy ranges only when a saved rule uses one; legacy keys render exactly as before. `direction: "show"` with a device key hides every unlisted legacy range above it, so `show` + `mobile` hides from 768px up. The Blade, React and Vue renderers and the editor canvas emit the same rules.
- **Navigation marks the current page** ([#975](https://github.com/ArtisanPack-UI/visual-editor/issues/975)). `core/navigation-link` and `core/navigation-submenu` never printed `aria-current` or a current class, so screen readers couldn't tell which page you were on and themes had nothing to style. As in WordPress, the item whose URL is the page being viewed now gets `current-menu-item` on its `<li>` and `aria-current="page"` on its link; a link to a parent path (`/blog` on `/blog/hello`) and a submenu leading to the page get `current-menu-ancestor`, without `aria-current`. Paths are compared ignoring a trailing slash, the query and the fragment; an absolute link must name the request's host, so a link to another site is never current, and `/` is only current on the home page. Blade reads the request; the React and Vue renderers take a new `currentUrl` prop on `<BlockTree>` and `<Template>` and mark nothing without it.
- **A menu inside a Navigation overlay part is no longer hidden** ([#984](https://github.com/ArtisanPack-UI/visual-editor/issues/984)). The overlay swap CSS hid every `.wp-block-navigation__container` under the drawer content, including a Navigation placed inside the overlay template part, so the drawer showed only its other blocks. The swap rules now target the drawer content's direct children (Blade, React and Vue).
- **Categories block: published counts, styleable counts, current term, any taxonomy** ([#960](https://github.com/ArtisanPack-UI/visual-editor/issues/960)). `artisanpack/categories` counted drafts and scheduled posts, so a category holding only drafts was listed even with **Show empty terms** off; counts now use cms-framework's published scope. The block reads its `taxonomy` attribute: `category` (the default), `post_tag` (cms-framework tags), or any other taxonomy the host supplies through the new `ap.visualEditor.terms` filter (an unanswered taxonomy renders the empty shell). A new `countStyle` attribute keeps today's ` (N)` after the link (`inline`, the default, markup unchanged) or puts the count inside the link as `<span class="wp-block-categories__count">N<span class="screen-reader-text"> posts</span></span>` next to a `wp-block-categories__name` span (`badge`). The item whose URL matches the request (path, plus any query parameter its URL carries, ignoring paging) gets `current-cat` and `aria-current="page"`. `showAll` + `allLabel` ("All") prepend a `cat-item-all` item linking to the unfiltered list: the posts index (`visual-editor.categories.posts_index_url`, default `/blog`) with the published-post total for `category` / `post_tag`, or the URL (and optional total) a host returns from the new `ap.visualEditor.terms.allUrl` filter. The editor's Settings panel adds the taxonomy, count style and "All" controls. The React and Vue renderers don't render this block (it is server-rendered), so they're unchanged.
- **Search forms submit where you choose** ([#961](https://github.com/ArtisanPack-UI/visual-editor/issues/961)). The Search block's form always submitted to `/`, so searching from `/blog` landed on the homepage, and Search Filters fell back to the site root because nothing stamped its `_resolvedFormAction`. Both blocks now have an `action` attribute with a **Submit to** control: Site search (the default, the new `visual-editor.search.url` config value, `/search`), This page (the current URL without its query string or a trailing `/page/N`), or a custom path, used only when it is a same-site path starting with `/`; anything else falls back to the site search URL. A host-stamped `_resolvedFormAction` still wins. The Search input is now filled from the request (`?s=`, or the block's `query.name`), escaped. The React and Vue renderers have no request or config: they use `/search` for Site search, omit the action for This page so the browser submits to the current document, and read the term from `_resolvedSearchValue`.
- **"This page" search action keeps a single leading slash.** In the Blade renderer, a Search or Search Filters block set to submit to the current page copied the request path into the form's `action` as-is, so a path requested with a doubled leading slash (`//packages`) printed `action="//packages"`, which browsers read as another host. The path's leading run of `/` and `\` now collapses to one `/` (`//packages` → `/packages`), and a path that still isn't a same-site path falls back to `/`. Normal paths and the `/page/N` stripping are unchanged. The React and Vue renderers omit the action for "This page", so they're unchanged.
- **Search button position and hidden label** ([#961](https://github.com/ArtisanPack-UI/visual-editor/issues/961)). The Search renderers ignored `buttonPosition` and added `wp-block-search__button-inside` whenever the label was hidden. The Blade, React and Vue renderers now follow WordPress: a `wp-block-search__button-outside` (default), `__button-inside`, `__no-button` or `__button-only` class, plus `__icon-button` / `__text-button` when a button renders; `no-button` renders no button, so Enter submits. WordPress hides the field for `button-only` until its interactivity script expands it; no such script ships here, so the field stays visible. A hidden label is no longer removed: it renders with `screen-reader-text`, so the input keeps an accessible name.
- **Button width reaches the page** ([#921](https://github.com/ArtisanPack-UI/visual-editor/issues/921)). A button's width (`style.dimensions.width`) showed in the canvas but was never saved or rendered, so buttons were their natural width on the front end. `save()` and the Blade, React and Vue renderers now add WordPress's width classes to the `.wp-block-button` wrapper: `has-custom-width wp-block-button__width wp-block-button__width-{25|50|75|100}` for the preset widths. Another percentage sets `--wp--block-button--width` for the block library's generic width rule, and a plain length (`px`, `rem`, `em`, `vw`, `ch`) is set as the wrapper's width; the renderers drop any other value. The canvas uses the same classes and style. Buttons saved without the classes still load: a deprecation matches the old markup and re-saves it with them. Buttons that still store a legacy numeric `width` now keep it, including markup WordPress saved with the width classes already on the wrapper, which used to validate and silently drop the width; the classes `save()` writes itself are no longer kept as a custom class.
- **Tab sections keep their own settings** ([#922](https://github.com/ArtisanPack-UI/visual-editor/issues/922)). The Tabs renderers built each panel with a fixed `wp-block-artisanpack-tab-section ap-tab-section` wrapper, so a Tab Section's additional class, block style, colour, padding, margin, border, gradient border, shadow and aria-label were dropped on the front end. The Blade renderer now prints the section's supports on its panel; the React and Vue renderers print its class (they don't print supports yet, see #920). An aria-label set on a section replaces the panel's `aria-labelledby`, which would otherwise take precedence. The panel id stays `tabs-panel-{tabId}`, so tab links still find it.
- **Columns without a width stay flexible** ([#925](https://github.com/ArtisanPack-UI/visual-editor/issues/925)). The Column block's legacy deprecation matches almost any column whose saved HTML lacks its support output (its `save()` is a bare `<div>`), and its `migrate()` turned the width into a percentage unconditionally, so a column with no width was stored as `"width":"undefined%"` and collapsed on the front end (`flex-basis: undefined%`). It also kept `wp-block-column` as a custom class. `migrate()` now converts only a numeric width and drops that class token. The Blade, React and Vue renderers treat a width that isn't a length (a number with an optional unit, or a CSS function such as `calc()` or `var()`) as no width, so content already saved with `"undefined%"` renders correctly; a breakpoint width that isn't a length is skipped. As a side effect, a width like `0x1A`, which no browser parses, is no longer printed.
- **Breakpoint spacing overrides render** ([#918](https://github.com/ArtisanPack-UI/visual-editor/issues/918)). The editor stored a breakpoint's padding, margin or block gap under whichever leaf changed, so the key depended on the block's base value: `style.spacing` for a block with no spacing yet, or `style.spacing.blockGap.left` / `style.spacing.padding.top` when the base value was already per-axis or per-side (the common case for Columns' gap). The renderers only read `style.spacing.padding`, `style.spacing.margin` and `style.spacing.blockGap`, so those overrides were dropped on the front end. The editor now stores every override under the path the renderers read, merging one side into the breakpoint's existing value, and the same goes for border radius corners, border, dimension and typography values under a coarse `border`, `dimensions` or `typography` root. Content saved by earlier versions renders as-is: the Blade, React and Vue renderers fold the legacy keys into those paths (a value stored under the path itself wins at the same breakpoint), and the editor migrates them on the next breakpoint edit. A per-corner border radius override now prints `border-top-left-radius` and friends. The canvas read view now shows what the front end renders: per-side values combine across breakpoints the way the media rules do, and sides an override leaves out come from a scalar base value. Scope classes are unchanged for content that only uses the renderer paths.
- **Gradient borders show on Cover** ([#912](https://github.com/ArtisanPack-UI/visual-editor/issues/912)). The ring is a `::before` that sits outside the padding box, and Cover's `overflow: clip` clipped it away, so a Cover with a gradient border showed a transparent border. The gradient-border wrapper rule now sets `overflow-clip-margin: border-box`, in the Blade emitter and the canvas emitter, which only takes effect on blocks with `overflow: clip`. Browsers without `overflow-clip-margin` (Safari) get an `@supports not` fallback: a gradient-bordered Cover stops clipping, and its background layers take the inner radius (the wrapper's radius minus the ring width), so they keep their rounded corners.
- **Stack on mobile wins over column widths below 782px** ([#910](https://github.com/ArtisanPack-UI/visual-editor/issues/910)). A column's "All sizes" width was promoted to a scoped `!important` rule with no media query, so it beat WordPress's `flex-basis: 100%` stacking rule and stacked columns stayed narrow on phones. The Blade, React and Vue renderers now apply it from `@media (min-width: 782px)` up, and everywhere inside a Columns block with **Stack on mobile** off. Explicit per-breakpoint widths still win in their ranges.
- **Cover save keeps its own wrapper props** ([#908](https://github.com/ArtisanPack-UI/visual-editor/issues/908)). `save()` serialized the inner blocks before reading the wrapper's block props, so a cover with inner blocks was saved with its last descendant's classes and styles on its root (and no `wp-block-cover`), and showed as invalid when reopened. It now reads the wrapper props first, as upstream does. Covers with inner blocks saved by earlier versions show as invalid in the editor; **Attempt recovery** re-saves them with the fixed markup, but may keep the stray classes in the block's Additional CSS class(es). The front end is unaffected: renderers build the cover from its attributes.
- **Cover overlay and aspect ratio match the canvas** ([#906](https://github.com/ArtisanPack-UI/visual-editor/issues/906)). The Blade, React and Vue renderers defaulted a missing `dimRatio` to 50, but `block.json` defaults it to 100 and the editor omits defaults, so a full-overlay cover rendered at half opacity. They now fall back to 100. An aspect ratio (`style.dimensions.aspectRatio`) now also emits `min-height: unset`, replaces any min-height (the dimensions value and cover's own `minHeight`) and adds the `has-aspect-ratio` class, as WordPress's dimensions support does, so the block library's default cover min-height no longer overrides the ratio.
- **Text clip over the block's own gradient** ([#904](https://github.com/ArtisanPack-UI/visual-editor/issues/904)). Effects → Text clip on a block with a gradient preset or a custom gradient painted a solid gradient box instead of gradient text, on the front end and in the canvas. The gradient reaches the block as a `background` shorthand (the `!important` `.has-{slug}-gradient-background` class, or inline), which resets `background-clip`, and the canvas loads the global styles after the effects styles. The clip declarations now print `!important` in their own doubled-class rule, with the forced-colors reset at the same specificity, in the Blade, canvas, React and Vue compilers. Scope hashes still cover every declaration, so class names stay in parity across renderers.

### Documentation

- **Sticky template parts** ([#976](https://github.com/ArtisanPack-UI/visual-editor/issues/976)). Template parts already accept Position (sticky, offsets, z-index) through the `artisanpack/template-part` fork. This release adds tests, a parity fixture and a `docs/position.md` note to put sticky on the template part, not a Group inside it.

## [1.15.1] - 2026-10-16

### Upgrade notes

- **Grids collapse on small screens by default (#901).** An
  `artisanpack/grid` with no Tablet column count now shows at most two
  columns at 1023px and below, and one with no Mobile column count shows
  one column at 767px and below. Wider items are clamped so they don't
  overflow. To keep a grid's column count on a device, set **Columns**
  with that viewport active. Grids that set a legacy breakpoint
  (`sm` … `2xl`) are unchanged.
- **Custom breakpoints and the static Flex Layout sheet (#895).** With
  customized breakpoints, `<x-ve-blocks-styles>` now prints the Flex
  Layout token rules inline instead of linking `flex-layout.css`. React
  and Vue hosts that call `setBreakpoints()` get the same rules from
  `<LayoutBaseline />`, and should stop linking the published
  `flex-layout.css`, whose default-width rules would still fire. Default
  breakpoints are unchanged.
- **New wrapper classes (#899).** First-party blocks now render their
  `wp-block-artisanpack-{slug}` class first, so `styles.blocks` rules and
  block style variations reach them on the front end. Theme CSS that
  targets `.wp-block-artisanpack-accordions` (or another of these blocks)
  now applies there too.

### Added

- **Editor translations (#896).** Publish a Jed JSON file per locale at
  `lang/vendor/visual-editor/{locale}.json` (`po2json -f jed1.x` from a
  translated `.po`), and the post editor and site editor load it for the
  app locale. Resolution falls back to the language, then the app's
  fallback locale. The `ap.visualEditor.localeData` filter can supply
  the data, and `MountConfig.localeData` / `data-locale-data` take it
  directly. Translations are applied before the editor bundle loads, so
  block variation titles and preset names are translated too. See
  `docs/translations.md`.
- **WordPress's default shadow presets (#900).** `natural`, `deep`,
  `sharp`, `outlined` and `crisp` are declared on the front end and in
  the editor canvas unless `settings.shadow.defaultPresets` is `false`. A
  slug the theme declares keeps the theme's value.

### Fixed

- **String shadow values render (#900).** A `style.shadow` saved as a
  string, the form WordPress core and the WordPress importer use, now
  renders. `var:preset|shadow|{slug}` and
  `var(--wp--preset--shadow--{slug})` are read as presets, and any
  other safe string as a raw `box-shadow` value, in the Blade renderer
  and the canvas. Editing such a shadow in the inspector no longer
  corrupts it.
- **Block style variations on interactive blocks (#899).** Accordions,
  Accordion (with its title and body), Tabs and Tab Section render their
  `wp-block-artisanpack-*` class in Blade, React and Vue, so variations
  such as a "Boxed" accordion or "Pill" tabs apply on the front end. So
  do FAQ, HowTo, TOC, Breadcrumbs, the business-info blocks, Comments
  Number, Copyright, the search blocks, Single Content, Social Share
  Content, Author Social Icons, Skills Slider and Log in/out.
- **React Tabs markup (#899).** The React renderer no longer wraps each
  tab panel twice, and renders `data-ap-tabs=""` like Blade and Vue.
- **Flex Layout token classes at custom breakpoints (#895).** Classes
  such as `tablet:ap-flex-col` now switch at the host's breakpoint widths
  on the front end (Blade, React, Vue) and in the editor canvas.
- **Flex Layout `grow-2` (#895).** `md:`, `lg:`, `tablet:` and `mobile:`
  `ap-grow-2` classes now have a rule, as the other breakpoints did.

## [1.15.0] - 2026-10-15

### Upgrade notes

- **Global styles now print in `<head>`.** `<x-ve-blocks-styles>` prints
  the compiled global styles (`<style data-ve-global-styles>`) in the
  head, taking over the once-per-request emission `<x-ve-blocks>` /
  `<x-ve-template>` used to make in the body (#879). This works with
  single-file views, `@extends` layouts and component layouts: when the
  blocks render first inside an `@section` or a component slot, they
  queue the styles on the new `ve-global-styles` Blade stack, and
  `<x-ve-blocks-styles>` prints that stack. The front end now uses the
  editor canvas (and WordPress) order: global styles first, then the
  theme's own stylesheet, so a `style.css` rule beats the theme.json rule
  it overrides on both surfaces. Mount `<x-ve-blocks-styles>` before the
  `<link>` to your theme stylesheet, and don't also print
  `@stack('ve-global-styles')` in a layout that has the component.
  Nothing is printed twice.
  - A layout that renders blocks inside a section or slot but has no
    `<x-ve-blocks-styles>` (or `@stack('ve-global-styles')`) in its head
    is covered by the new `InjectQueuedGlobalStyles` middleware, which
    the Blade renderer appends to the `web` middleware group. On a
    `text/html` response it injects the queued styles into `<head>`
    before the first stylesheet (else before `</head>`, else after
    `<body>`). It does nothing when the styles were already printed, and
    skips streamed, file, JSON, redirect and encoded responses.
  - Output that never becomes an HTTP response, such as mail or
    `Blade::render()` into a string, gets no injection. Set the new
    `global_styles_in_head` config key to `false` there, which restores
    the 1.14 placement: the first `<x-ve-blocks>` / `<x-ve-template>`
    prints the global styles inline in the body.
  - `:global-styles="false"` on `<x-ve-blocks-styles>` now means the
    component never claims the emission itself. In a single-file view
    the blocks then print the styles in the body; in a layout where the
    blocks render first the component still prints the queued stack.
    Use `global_styles_in_head => false` for body placement everywhere.
- **Per-block Custom CSS is denied by default.** The new
  `visual-editor.edit-block-css` gate requires the `edit_block_css`
  capability (config `effects.custom_css_capability`), checked through the
  user's `checkPermissionTo()` / `hasCapability()` / `hasPermissionTo()` /
  `hasPermission()`; the first method the user model defines decides.
  Nobody has it until you grant it to a role, or redefine the gate. Users
  without it don't see the Custom CSS field, and API clients that write
  `artisanpackEffects.customCss` need the ability.
  - The guard runs on every Eloquent save of block content, not only in
    visual-editor's controllers: every model using `HasBlockContent`
    (including cms-framework's block patterns, templates, template parts,
    posts and pages) and the snippet model. That covers cms-framework's
    `/api/v1/blocks`, `/api/v1/block-patterns/patterns`,
    `/api/v1/templates` and `/api/v1/template-parts`. Without the
    ability, a save keeps only the Custom CSS already stored on that
    record. Guard other models with
    `CustomCssModelGuard::watch( Model::class, 'column' )`. Query-builder
    mass updates fire no model events and aren't covered.
  - Code that writes block content with Custom CSS while a user without
    the ability is logged in (a web-triggered theme import,
    `PatternResolver::cloneToUser()` from an admin action on
    cms-framework before 2.15, or a test that seeds records inside
    `actingAs()`) must wrap the write in
    `app( CustomCssModelGuard::class )->trusted( fn () => … )`, or the
    CSS is stripped. Console commands, seeders and queued jobs with no
    authenticated user are not guarded.
  - A permission backend that throws for an unknown permission (such as
    Spatie's `hasPermissionTo()`) now denies and reports the exception,
    instead of returning a 500 on every editor page. Spatie hosts don't
    need to seed `edit_block_css` just to keep the editor working. The
    same applies to the `visual-editor.edit-content` and
    `visual-editor.use-ai` gates and the Font Library's
    `FontPolicy::manage()`.
- **Custom CSS URLs must be quoted.** Write `url("…")` or `url('…')`. An
  unquoted `url(https://…)` now rejects the block's whole Custom CSS
  value, as do braces, parentheses and brackets that don't nest
  correctly. Re-save affected blocks with quoted URLs. Set the new
  `effects.custom_css_allow_urls` config key to `false` to drop any
  Custom CSS that loads a resource (`url(`, `image-set(`, `image(`,
  `src(`); React and Vue hosts must then pass `customCssAllowUrls={false}`
  to `<BlockTree>` (or `<Template>`) as well.
- **Binding previews check the record's `view` policy.**
  `POST /visual-editor/api/bindings/resolve` and
  `POST /visual-editor/api/blocks/preview` now return 403 when the
  context record's model has a registered policy and the user lacks its
  `view` ability. The canvas then falls back to the blocks' static
  values. Models without a policy behave as before.
- **Block style variations are registered at boot.** The site editor's
  `GlobalStylesResolver` is now request-scoped, so call
  `VisualEditor::registerBlockStyle()` / `unregisterBlockStyle()` from a
  service provider's `boot()`. On Octane, a call made mid-request leaks
  into later requests.
- **Flex and grid containers now get a `gap`.** `:where(.is-layout-flex)`
  and `:where(.is-layout-grid)` use the block's Block spacing, else the
  theme's `styles.spacing.blockGap`, else `0.5em`, in the Blade, React,
  Vue and canvas baselines (#883). Rows, Stacks and grids that rendered
  with no spacing on the front end now match the canvas. The rules have
  zero specificity, so columns, gallery, post-template, the Flex panel
  and any theme rule still win.
- **Block spacing applies per container.** A container's own Block
  spacing now reaches its children through its `wp-container-{hash}`
  rule (`gap` on flex and grid, `margin-block-start` on flow and
  constrained), as in WordPress, and nested containers no longer inherit
  an ancestor's value. Each layout container resets
  `--wp--style--block-gap` to the root value (`--ap-root-block-gap`) at
  zero specificity.
  - Containers with their own Block spacing gain a `wp-container-{hash}`
    class, and existing hashes change because the gap is now part of the
    hashed rule.
  - A `--wp--style--block-gap` set on a wrapper element around
    `<x-ve-blocks>` no longer reaches top-level containers. Set it on
    `body` / `:root`, or through theme.json `styles.spacing.blockGap`.
    A Tailwind (layered) utility that sets it on a layout container
    loses to the unlayered reset; use the block's Block spacing control.
  - Column percentage widths now read
    `calc(X% - var(--ap-columns-gap, var(--wp--style--block-gap, 0.5em)) * f)`.
    The result is the same wherever the layout baseline is loaded.
- **Dynamic blocks get their block supports on the front end.** The Blade
  renderer merges `BlockSupports::compile()` output (color, spacing,
  border, anchor, className, states, responsive, shadow, position,
  animations, …) into the wrapper element a `DynamicBlock::render()`
  returns (#874). It never overrides the handler's output: classes are
  deduped, a declaration is added only when the existing `style` doesn't
  already set that property, a shorthand that resets it or a longhand it
  would reset, and `id` / `data-*` are added only when missing. Leading
  `<svg>` and `aria-hidden="true"` elements are skipped when looking for
  the wrapper; mark a different wrapper with a `data-ve-wrapper`
  attribute. A custom dynamic block that already applies supports
  through `BlockSupports::wrapperAttrs()`, or renders no wrapper of its
  own, should override the new `DynamicBlock::appliesOwnSupports()` to
  return `true`, or pass `'appliesOwnSupports' => true` to
  `registerDynamicBlock()` / `registerServerBlock()`.
- **Hover glow follows the theme's colors.** The `glow` hover animation no
  longer hard-codes indigo. It uses the block's new **Glow color** pick,
  then `settings.custom.animation.glowColor`, then the `primary` palette
  color at 35% (`color-mix()`), then the old indigo. Because the package
  default palette always declares `primary` (`#2563eb`), existing glows
  change color unless the theme sets `settings.custom.animation.glowColor`.
  Glow blur and size and the `lift` shadow can be themed the same way
  (`glowBlur`, `glowSize`, `liftShadow`). The Blade compiler now flattens
  `settings.custom` into `--wp--custom--*` properties, as WordPress and
  cms-framework do.
- **Heading anchors are id-safe.** Auto-generated anchors are reduced to
  the characters every renderer keeps in an `id` before the uniqueness
  check, so auto anchors on headings with non-ASCII letters or symbols
  change (for example `creme--x` → `creme-x`, and `日本語` → `heading`).
  Author-set anchors and anchors the editor already saved are kept.
  Update inbound links to old auto anchors on such headings, or give
  those headings an author anchor.
- **State styles gain a reduced-motion guard.** State CSS now ends with a
  `@media (prefers-reduced-motion: reduce)` block that turns off
  transitions and holds hover/focus transforms at their idle value, on
  the front end and in saved markup. Blocks with state styles saved by
  1.14 (raw `var:preset|…` values, no guard) or by 1.15 development
  builds validate through new save deprecations and are migrated silently
  on their next save. Palette picks in state values are saved as
  `var(--wp--preset--…)`.
- **Text clip needs a background.** Gradient text (`textClip`) is
  emitted only when the block itself has a gradient preset,
  `style.color.gradient` or `style.background.backgroundImage`. A block
  that relied on a gradient from a block style variation or global styles
  loses its text clip; add the gradient to the block.
- **Binding allowlist (opt-in).** Set `VE_BINDINGS_ENFORCE_ALLOWLIST=true`
  (config `bindings.enforce_allowlist`, default `false`) to stop block
  bindings from reading any attribute of a related model that isn't
  allowlisted (#869). With it off, nothing changes. Enforcement is planned
  to become the default in 2.0, so until then add sensitive columns such
  as `email` to your `User` model's `$hidden`.
- **Link and image URLs follow the browser's scheme rules.** A
  `host:port` value without a scheme, such as `localhost:8080/x`, is now
  read as the scheme `localhost` and dropped, as a browser would. Use
  `http://localhost:8080/x` or a relative path. See **Security**.
- **New classes and markup.**
  - `artisanpack/grid`, `grid-item`, `callout` and `marquee` now carry
    `wp-block-artisanpack-{slug}` first in their class list on the front
    end (#880), so theme.json `styles.blocks` rules for them now apply.
  - Every block whose block.json declares `supports.layout` renders
    WordPress's layout classes in all three renderers: `is-layout-{type}`,
    `wp-block-{slug}-is-layout-{type}`, a `wp-container-{hash}` class and
    rule when its settings or Block spacing need one (#871), and the
    `is-{orientation}`, `is-content-justification-{value}` and
    `is-nowrap` modifiers. Newly covered are Buttons, Column, Cover's
    inner container, Navigation, Query, Quote, Details and Comments
    Pagination; Group and Post Content gain the modifiers. Navigation's
    `<nav>` is now a flex container. Columns, Quote, Details and Query
    children get flow spacing from the baseline. A legacy
    `layout.inherit: true` renders as constrained. A Grid with no column
    count or minimum width gets WordPress's default
    `repeat(auto-fill, minmax(min(12rem, 100%), 1fr))` track list.
    Columns, Gallery, Post Template and Query Pagination keep their own
    layout CSS.
  - The React and Vue renderers now add `ve-r-{hash}` (responsive),
    `ve-fx-{hash}` (effects) and `wp-container-{hash}` (layout) classes
    with matching `<style data-ve-responsive>`, `<style data-ve-effects>`
    and `<style data-ve-layout>` tags, plus one `<style data-ve-duotone>`
    and hidden `<svg data-ve-duotone>` per tree. Their headings get an
    `id` (author anchor or an auto slug, as Blade does), and Cover gets
    `is-layout-flex wp-block-cover-is-layout-flex`. Pullquote no longer
    wraps its value in a `<span>`, and Image orders its classes
    `wp-block-image size-* [wp-duotone-*] align*`, both as Blade does.
  - The Blade renderer prints one `<style data-ve-duotone>` plus a
    visually hidden `<svg>` of duotone filters per render, with each
    filter id printed once per request. Hosts that call
    `BlockRenderer::render()` / `renderMarkup()` directly get it from
    `BlockRenderer::flushSupportAssets()`.
  - In Blade, Accordion title and body inside an accordion now carry
    their full block supports on the `.ap-accordion__title` /
    `.ap-accordion__body` elements.
- **Element pseudo-states follow WordPress.** Only the `link` and `button`
  elements compile pseudo-state sub-nodes now, in the order
  `:link, :any-link, :visited, :hover, :focus, :focus-visible, :active`.
  A `:hover` on another element (for example `heading`) in
  `styles.elements` is no longer emitted.
- **Stricter theme values.** Theme preset values (including font
  families) and every `styles.*` value that could break out of the
  generated stylesheet are now dropped instead of printed. That covers
  `; { } < >`, comments, escapes, control characters, unbalanced or
  interleaved quotes, and unbalanced or crossed `( )` / `[ ]`. Check the
  front end for any theme value that has gone missing.
- **Default font families are always declared.** The front end declares
  `--wp--preset--font-family--system`, `--serif` and `--mono`, plus any
  theme or host font families, with matching `.has-{slug}-font-family`
  utilities. A host `presets.font_families` list in `replace` mode still
  drops the defaults.
- **Patterns marked `Inserter: no` leave the inserter.** The Patterns
  panel and the page-starter modal skip patterns whose `inserter` is
  false. The site editor's pattern library still lists them. The field is
  filled by cms-framework 2.15's pattern headers.
- **Editor canvas styles.** Only theme editor styles that resolve inside
  the theme's `assets/` directory are inlined into the canvas. Absolute
  and CDN entries in `Theme::editorStyles()` are skipped there, and so is
  an entry whose `media` isn't a safe media query. Handles dedupe as
  `@themeEditorStyles` does: the first entry claims its handle, even an
  absolute URL the canvas then skips.
- **cms-framework pairing.** 1.15 works with cms-framework 2.11 and
  later. cms-framework 2.15 is needed for the pattern header fields
  (`keywords`, `inserter`, `template_types`), full global-styles parity
  and the `ap.cmsFramework.siteEditor.blockContent` filter, through which
  visual-editor also runs its Custom CSS guard on the Site Editor API.
  The model-level guard covers older cms-framework versions as well.
- **License metadata is MIT.** `composer.json`, the Blade renderer's
  `composer.json` and the React and Vue renderer `package.json` files now
  declare `MIT`, matching `LICENSE`. They said `GPL-3.0-or-later` before.
- **Smaller Composer dist.** Tests, the renderer parity fixtures,
  `issues/`, `scripts/`, `themes/`, `docs/plans/` and other dev-only files
  are now `export-ignore`d and no longer ship in the package archive.

### Added

- Theme block style variations (#876, #889). Themes declare them in
  `theme.json` `styles.blocks.{block}.variations.{slug}` or in
  `styles/*.json` partials with `blockTypes`. Code registers them with
  `VisualEditor::registerBlockStyle( $block, $slug, $label, $style,
  $isDefault )` / `unregisterBlockStyle()`. Variations appear in the
  native Styles picker in both editors, under both the `core/` and
  `artisanpack/` names. Their `:root :where(.wp-block-{name}.is-style-{slug})`
  CSS reaches the canvas, the front end (even without cms-framework) and
  `<x-ve-blocks-styles>`, and is emitted once. It supports nested
  `elements` and `blocks` and follows core's block.json `selectors`. The
  merged list passes through the new `ap.visualEditor.blockStyles` filter.
  Extra partial directories go in the new `block_styles.directories`
  config key.
- A block-level **Effects** panel (Styles → Effects) on every block
  (#881): opacity, horizontal and vertical overflow, backdrop blur and
  saturation, blend mode, isolation, z-index, clip shapes (circle,
  ellipse, triangle, diamond, hexagon, slanted bottom) and gradient text.
  It is stored in a new `artisanpackEffects` attribute and compiled to a
  hashed `ve-fx-{hash}` class with identical output in Blade, React, Vue
  and the canvas. A block opts out with `supports.artisanpackEffects:
  false`.
- Per-block **Custom CSS** in the Effects panel, printed inside the
  block's rule with native CSS nesting (`& img { … }`, `@media`,
  `@supports`, `@container`). It is guarded by the new
  `visual-editor.edit-block-css` gate and `effects.custom_css_capability`
  config (see Upgrade notes). Invalid Custom CSS is marked
  `aria-invalid`, explained in the field's help and announced to screen
  readers.
- `effects.custom_css_allow_urls` config key (default `true`), the React
  and Vue `<BlockTree>` / `<Template>` `customCssAllowUrls` prop and the editor's
  matching `customCssAllowUrls` mount option.
- `global_styles_in_head` config key (default `true`), the
  `ve-global-styles` Blade stack and the `InjectQueuedGlobalStyles`
  middleware (see Upgrade notes).
- `CustomCssModelGuard` (`watch()`, `trusted()`) and
  `Support\CapabilityCheck`, the shared permission probe behind the
  package's capability gates.
- An opt-in allowlist for the attributes block bindings can read from
  related models (#869). Models can implement the new
  `DefinesBindableAttributes` interface, hosts can list attributes in the
  new `bindings.allowed_attributes` config (`'*'` allows all), and the
  new `ap.visualEditor.bindings.allowedAttributes` filter can adjust the
  list. When enforcement is on, a relation path can't end on a whole
  record, and the field picker hides `author_name` unless it is allowed.
- A **Glow color** picker in the Hover animation panel, offering the
  theme, default (unless `color.defaultPalette` is `false`) and custom
  palettes, and `settings.custom.animation.{glowColor,glowBlur,glowSize,liftShadow}`
  theme settings (#878).
- `DynamicBlock::appliesOwnSupports()` and
  `DynamicBlock::supportAttributes()`, plus the `appliesOwnSupports`
  option for closure registrations (#874).
- A search field in the inserter's Patterns panel. It matches titles,
  slugs, categories and keywords, ignoring case and accents, and
  announces politely when no pattern matches. Patterns now carry `keywords`,
  `inserter` and `template_types` from their headers (#893).
- `outline` (`color`, `offset`, `style`, `width`) in theme.json element,
  block and variation styles, for focus indicators such as
  `elements.button[":focus"].outline` (#893).
- `<x-ve-blocks-styles>` takes `:global-styles` and
  `:block-style-variations` flags.
- `MountConfig` accepts `canEditBlockCss`, `customCssAllowUrls` and
  `blockStyles`, and the Vue `<VisualEditor>` takes the matching
  `can-edit-block-css`, `custom-css-allow-urls` and `block-styles` props,
  so Vue and Inertia hosts get Custom CSS access and theme block styles.
  `<x-visual-editor>` resolves both flags itself, and they are part of the
  `ap.visualEditor.editorConfig` schema.
- React and Vue render background images (Group, Quote, Pullquote, Verse,
  Post Content) and duotone (Image, Featured Image, Cover, Avatar, Site
  Logo, Post Author) byte-for-byte with Blade. The new `duotonePresets`
  prop on `<BlockTree>` and `<Template>` takes the theme's
  `settings.color.duotone` so `var:preset|duotone|…` picks render.
- React and Vue `setBreakpoints()` accepts device (desktop-first)
  breakpoints: pass the server's `BreakpointRegistry::toArray()` as is,
  or a config-style map such as
  `setBreakpoints( { mobile: { maxWidthPx: 599, previewWidthPx: 375 } } )`,
  layered over the defaults like the `breakpoints` config. New exports:
  `getDeviceBreakpoints()` and the `DeviceBreakpointDefinition`,
  `BreakpointInput` and `BreakpointMap` types. Existing
  `setBreakpoints([{ key, minWidthPx }])` calls behave as before.
- `UrlSanitizer::scheme()`, `BlockSupports::cssNumber()` and
  `BindingAttributeAllowlist::containsModel()`.

### Changed

- The editor canvas's `GET /visual-editor/api/global-styles/css` now
  includes the active theme's `style.css` and `editor.css`, followed by
  its `Theme::editorStyles()` entries (after the `ap.themes.editorStyles`
  filter), with relative `url()`s rewritten so they resolve in the canvas
  (#879). Pattern previews share the same reader.
- Hover and focus state UI now covers Group, Column, Columns and Grid Item
  (color, border, shadow, transform, transition) and Heading and
  Paragraph (color, gradient, border, text decoration, transition)
  (#875). Templates, parts
  and patterns saved by 1.14 are migrated automatically when opened.
- Heading, Paragraph, Group and Cover offer per-breakpoint font size, text
  and background color and min-height (#877). Cover routes
  `minHeightUnit` per breakpoint.
- When a preset and a custom value share a property (font size, text
  color, background color), setting one at a breakpoint stores a `false`
  marker for the other there. The canvas and the front end then agree on
  which wins at each breakpoint (#887).
- The React and Vue renderers no longer hard-code Tablet 1023px / Mobile
  767px. Per-breakpoint overrides, column widths and the Flex Layout
  panel's classes and media queries use the configured device
  breakpoints, as Blade does. Saved editor markup keeps the package
  defaults, so blocks don't go invalid when a host changes breakpoints.
- Effects overflow and blend-mode options show translated labels, and
  the clip-shape labels follow the locale loaded after import.
- Text clip adds a `forced-colors` fallback that shows the text in its
  normal color, and the Effects panel warns when text clip is on without
  a gradient or background image.
- `PatternPreviewRenderer::themeBaseUrl()` and its
  `STYLESHEET_READER_FQCN` / `THEME_ASSETS_ROUTE` constants are
  deprecated in favor of `ThemeStylesheets::baseUrl()` and the
  `ThemeStylesheets` constants.
- Every editor string now uses the `artisanpack-visual-editor` text
  domain, including the Animation, Custom keyframe and Bindings panels and
  the blocks ported from core, which were previously never extracted.
  Strings that block `save()` functions serialize stay untranslated
  literals, so saved markup is identical in every locale. About 250
  editor strings that were never wrapped in `__()` (AI panels, viewport
  and state switchers, notices, API error messages, option lists, block
  sidebar labels and placeholders) are now translatable, and a guard test
  keeps new ones from slipping in. The translation extractor
  (`npm run i18n:extract`) now reads the TypeScript AST, so calls with a
  trailing comma or comments between arguments are no longer skipped,
  escaped quotes are decoded correctly, and `translators:` comments are
  carried into the template. The editor translation template
  (`languages/artisanpack-visual-editor.pot`) is regenerated and lists
  1,552 strings (924 in 1.14). "colour" and "neighbours" are now spelled
  "color" and "neighbors".

### Fixed

- Layout-panel values (content and wide size, justification,
  orientation, wrap, grid column count and minimum width) now render on
  the front end for Group (including Row and Stack) and Post Content in
  all three renderers (#871), and for every other block with
  `supports.layout` (see Upgrade notes).
- A Row, Stack, Grid or Group nested inside a container with its own
  Block spacing no longer picks that spacing up as its own `gap`.
- theme.json shadow presets, `color.gradients` (the legacy
  `color.gradient` key still works) and font-family presets are now
  declared on the front end, and font-family picks render (#872).
- Group, Quote, Pullquote, Verse and Post Content background images
  (size, position, repeat, attachment), and Image, Featured Image, Cover,
  Avatar, Site Logo and Post Author duotone (custom stops in hex, `rgb()`,
  `hsl()` or named colors, or theme presets) now render on the front end
  in Blade, React and Vue (#873). Image aspect ratio and scale render in
  all three renderers. Duotone is skipped when no image renders, each
  filter is printed once per request, and preset duotone no longer
  resolves the global styles once per block.
- Dynamic blocks such as Icon no longer lose their gradient, spacing or
  anchor on the front end (#874). Icon renders palette and custom
  gradients itself, so the React and Vue server-render path gets them too,
  and `var:preset|gradient|camelCaseSlug` references resolve to the
  kebab-case custom property. Attributes a dynamic block's
  `validateAttrs()` drops no longer reach its supports merge.
- Hover and focus values picked from a palette (`var:preset|…`) now render
  on every state path, not just the preset-flagged ones (#875), and
  saved state markup no longer shows as invalid content.
- Per-breakpoint spacer height and width, cover min-height (including a
  unit-only override), font size and colors now render on the front end
  (#877). Two blocks that differ only in their base value no longer share
  one responsive rule. Very small or very large values no longer print in
  exponent notation (`1.0E-8px`), which browsers ignore.
- The React and Vue renderers now render per-breakpoint overrides,
  byte-for-byte with Blade (#886), and Cover layout classes and Heading
  preset classes and anchors (#888). Code, Preformatted, Verse, Embed and
  Accordion title/body now carry their `className` in React and Vue, so
  their responsive and Effects styles apply. The React accordion no
  longer renders the body inside the title, and Embed provider classes
  keep the provider's case.
- A heading anchor with characters the renderers strip (such as
  `foo bar`, or non-Latin text) no longer produces Table of Contents links
  to an `id` that doesn't exist, or duplicate ids (#888). React and Vue
  now claim and stamp the ids of headings inside post content, as Blade
  does, strip tags like PHP's `strip_tags()` when slugging, and honor a
  numeric author anchor.
- Writing a per-breakpoint value on a block with no base `style` no longer
  overwrites the desktop value (#887). Editing a non-responsive style
  (border, line-height, letter-spacing, shadow, …) while previewing
  Tablet or Mobile no longer drops the block's other base styles, and
  clearing a base value there now clears it.
- Theme stylesheets with relative `url()`s, and themes that load
  `Theme::editorStyles()`, now look the same in the editor canvas as on
  the front end (#879).
- `artisanpack/grid`, `grid-item`, `callout` and `marquee` keep their
  `wp-block-artisanpack-*` class on the front end (#880).
- Block style variation CSS is emitted once in pattern previews and on
  the front end (#889). Inner-block styles in a variation target the same
  elements as core's `selectors` (e.g. a button variation colors the
  link), and a variation with both a gradient and a background color
  keeps the color.
- Editing a theme's `styles/*.json` partial no longer leaves an orphaned
  permanent cache entry; one entry per directory is kept.
- On Octane and other long-lived workers, Global Styles edits and theme
  switches show on the next request (front end, canvas CSS, block style
  variations, keyframes) instead of after a worker restart.
- React and Vue slug preset names, navigation overlay colors and tab ids
  the way PHP does (ASCII-only lowercasing, byte-wise `var:preset|…`
  references), as does the editor's state CSS, and the JS effects
  compilers treat only ASCII whitespace as blank, so non-ASCII values
  match Blade.
- The Glow color picker is now named for assistive technology and linked
  to its help text (WCAG 1.3.1, 4.1.2).

### Security

- Link and image URLs are checked against the scheme a browser actually
  reads, in the Blade `UrlSanitizer` and the React / Vue `safeUrl()`.
  `javascript:///…`, `javascript:1/…` and `java<TAB>script:` URLs were
  previously kept by the Blade renderer, and the tab and control-character
  forms by React and Vue, so a stored link or image URL could run script.
  This affects every earlier version; upgrade to 1.15.0.
- Block bindings can be restricted to allowlisted related-model
  attributes (#869, opt-in). With enforcement on, `custom_field` and
  `post_core` bindings (e.g. `featured_image`) also refuse values that
  contain a model anywhere in an array or collection. See Upgrade notes.
- The bindings resolve and block preview endpoints check the context
  record's `view` policy before resolving bindings, so an editor can't
  read bound fields off records they can't open. See Upgrade notes.
- Hover and focus state values are now checked against the same CSS
  value whitelist as responsive values. A stored value like
  `red}</style><script>` could previously break out of
  `<style data-ve-states>` (#875).
- Group background image URLs pass `UrlSanitizer` and are percent-encoded
  so they can't close `url('…')`. Icon custom gradients reject `url(`
  layers on every render path, and the rejected gradient no longer comes
  back through the supports merge (#873, #874).
- Block spacing (`style.spacing.blockGap`) values are validated in all
  three renderers. A value that could add declarations
  (`1rem;position:fixed`), break out of the rule, or carry a comment is
  dropped.
- Theme preset values, every `styles.*` value, block style variation
  values, hover colors and easings, and responsive values are validated
  by a new string-aware check (`Support\CssValue`). It rejects unbalanced
  or interleaved quotes, unbalanced or crossed `( )` / `[ ]` outside
  strings, comments, escapes and `; { } < >`. Before, inputs such as
  `rgb(")"` or `x [` could swallow every rule after them in the same
  `<style>` block (#872, #885, #890).
- Custom CSS can't leave its block rule or `<style>` element: braces,
  brackets and parentheses must nest correctly and URLs must be quoted.
  It is never a sandbox, so writing it needs the deny-by-default
  `visual-editor.edit-block-css` gate. The guard runs on every model save,
  strips both the `attributes` and the parse-shape `attrs` bags, and a
  permission backend that throws for an unknown permission now denies
  instead of erroring (#881). `effects.custom_css_allow_urls` can close
  the remaining attribute-selector exfiltration channel.
- Custom CSS previews in the site editor's in-tree canvases are scoped to
  the canvas with `@scope`, so they can't restyle editor chrome.
- Theme files read for the canvas are contained to the theme's `assets/`
  directory with `realpath`, and editor-style `media` values are
  validated (#879).

## [1.14.1] - 2026-10-14

### Fixed

- A theme palette with a `text`, `link` or `border` slug no longer
  overrides the colors authors pick. Those slugs' `.has-{slug}-color`
  utilities share their class name with the `has-text-color`,
  `has-link-color` and `has-border-color` markers that blocks carry for
  any custom color, so the slug's color was forced onto every such block:
  a button with a palette text color rendered in the theme's `text` color
  on the front end, a custom hex color lost to the utility's
  `!important`, and blocks with only a link or border color had their
  text recolored. A block that picks one of these slugs as its text color
  now also carries an exact `has-{slug}-color-preset` class (Blade
  renderer, and the React / Vue navigation overlay) with its own rule.
  The `.has-{slug}-color` rule stays as a zero-specificity fallback for
  markup the renderer doesn't build (editor canvas, saved Gutenberg
  HTML): it skips elements with an inline `color:`, requires
  `has-text-color` for `link` / `border`, and is emitted last with `text`
  after `link` / `border`. In that markup a `link` or `border` text pick
  can't be told apart from a `text` pick plus a link or border color, so
  it resolves to `text`. See `PresetRegistry::colorUtilityRules()`;
  `ThemeJsonTokensCompiler`, the React / Vue default-token CSS and
  cms-framework 2.14.1's global-styles emitter use the same rules.
- An `artisanpack/icon` block bound to a custom field now renders the
  bound icon when the field stores its `{set, name}` iconRef as JSON
  text, or casts it to `object` / `AsArrayObject`. The block previously
  rendered a placeholder for any value that wasn't a plain array. The
  editor preview accepts the JSON form too and applies the same `set` /
  `name` checks as the server, including rejecting `..` in the name.

## [1.14.0] - 2026-10-14

### Upgrade notes

- **Bindings only call relation methods with a return type.** A
  `custom_field` key or `relation` path segment that names a model
  method now resolves only when the method declares an Eloquent
  `Relation` return type (e.g. `public function author(): BelongsTo`).
  An untyped relation resolves to empty, so the binding's `onEmpty`
  policy applies, and eager loading skips it. Relations declared by
  third-party binding sources through `eagerLoadRelations()` go through
  the same check. cms-framework's models already declare their return
  types. See **Security** below.
- **Bindings honour `$hidden` / `$visible`.** A binding to a hidden
  column, or to a column missing from a non-empty `$visible`, now
  renders as empty. See **Security** below for workarounds.
- **Global element styles carry more specificity.** `styles.elements.*`
  rules moved from bare selectors such as `a` (0,0,1) to
  `:root :where(…)` (0,1,0), and the link selector is now
  `a:where(:not(.wp-element-button))`. Host CSS that overrode theme link
  or heading colors with a bare element selector may now lose.
- **Per-block element colors print in one `<style data-ve-elements>`
  block.** `<x-ve-blocks>` and `<x-ve-template>` emit it, and Navigation
  no longer prints its own inline `<style>`. Hosts that call
  `BlockRenderer::render()` or `renderMarkup()` directly must print the
  collected CSS themselves with the new
  `BlockRenderer::flushSupportAssets()`, or blocks keep their
  `wp-elements-{hash}` classes with no rules behind them.
- **The package default palette and font sizes are always declared on
  the front end**, underneath a theme's own lists (theme entries win on
  a shared slug). Pages gain `--wp--preset--color--*` variables and
  `.has-*-color` utility classes for the default slugs even when the
  theme ships its own palette. A host `replace` preset list still drops
  the defaults.

### Added

- `BlockRenderer::flushSupportAssets()` returns the support CSS a
  direct `render()` / `renderMarkup()` call collected (responsive,
  states, animations, gradient borders, box shadows, position and
  Elements API colors) plus the animation runtime script, for hosts
  that don't render through `<x-ve-blocks>` / `<x-ve-template>`.
- Hosts can add their own content types to the Query Loop block's
  **Post type** picker (#847). Register them server-side through the
  `ap.visualEditor.query.postTypes` PHP filter (stamped onto both the
  post-editor and site-editor mounts as `data-query-post-types`) or
  client-side through the JS filter of the same name. The picker still
  defaults to Posts and Pages. A saved `postType` that isn't in the
  list now shows as "(unavailable)" instead of silently switching to
  `post` when the block is opened.

### Changed

- Query loops check each binding relation chain once per model class
  instead of once per listed post.
- The editor translation template (`languages/artisanpack-visual-editor.pot`)
  is regenerated and now lists all 924 editor strings.

### Fixed

- Theme templates that use WordPress's `core/query-pagination`,
  `core/query-pagination-previous`, `core/query-pagination-numbers`,
  `core/query-pagination-next`, `core/query-no-results` and
  `core/query-title` blocks now render on the front end without first
  being saved in the site editor (#856). The Blade, React and Vue
  renderers use the `artisanpack/*` fork output for these names instead
  of the unknown-block wrapper.
- Each row of an `artisanpack/dynamic-loop` now shows its own record's
  values when rendered through `<x-ve-blocks>` (#854). Rows were
  resolved a second time against the host binding context, which could
  show the wrong record, blank a row under the `hide` / `placeholder`
  policies, or show the host `:post`'s values in model-backed bindings.
  A row whose bound field is empty now shows the block's authored
  fallback instead of another record's value.
- An `artisanpack/dynamic-loop` nested inside another dynamic loop's
  row now renders each of its rows with its own record (#861). Before,
  every inner row showed the inner collection's first record. Tokens
  for the outer collection inside the inner loop show the current outer
  record, and an inner row whose bound field is empty shows the
  authored fallback instead of the first record's value.
- Front-end colors now match the editor canvas (#852):
  - The package default palette and font sizes stay declared when a
    theme ships its own `settings.color.palette` / `fontSizes` (theme
    entries win on a shared slug). A button whose text color is a
    default swatch such as **Base content** no longer falls back to
    white.
  - Per-block link colors (Color → Link) render on every block that
    supports them, not only Navigation, including their `:hover`,
    `:focus`, `:focus-visible` and `:active` colors. The rules ship in a
    single `<style data-ve-elements>` block. Navigation no longer emits
    its own inline `<style>`.
  - Global `styles.elements.*` and `styles.blocks[*].elements.*` now
    compile their `:hover`, `:focus`, `:focus-visible` and `:active`
    sub-nodes.
  - Link colors no longer leak into buttons. The link selector is now
    `a:where(:not(.wp-element-button))`. Global element and block
    rules are wrapped in `:root :where(…)` like WordPress, so they sit
    at a flat (0,1,0) specificity. Host CSS that relied on beating, or
    losing to, the old bare `a` rule may need adjusting.
  - A button's States panel keeps both its palette text color and its
    custom text color when both are set for different states. Before,
    one silently replaced the other.
- Block bindings (`custom_field`, `post_core`, `relation`) now resolve on
  the front end (#848). `<x-ve-blocks :tree :post>` resolved every binding with
  no record in scope, so blocks fell back to their static attributes
  (or were blanked under the `hide` / `placeholder` policies) even
  though the editor showed the right values. When `:post` is an
  Eloquent model, bindings now resolve against it, including inside
  nested renders such as tabs, accordions, and the navigation overlay.
  `BlockRenderer::render()` takes an optional `BindingContext` for
  hosts that call the renderer directly.
- Block bindings inside a `core/query` loop now resolve against each
  iteration's post instead of the host `:post` (#850), so a paragraph bound to
  `title` in a post template shows each listed post's title. This also
  covers related posts, a single-content block with a selected entry,
  and next / previous post containers. Iteration posts that aren't
  Eloquent models fall back to the binding's `onEmpty` policy, and
  `relation` bindings load once across the result set instead of once
  per post.
- Headings whose `content` is bound now get their auto-generated anchor
  and table-of-contents entry from the resolved value instead of the
  static authored text (#851). A heading bound to the post title renders
  `id="core-package"` rather than `id="static-title"`, and the
  `artisanpack/toc` block links to it under the same label. Author-set
  anchors are unchanged, and a heading hidden by an empty binding no
  longer leaves an orphan TOC entry. `TocResolver::resolveTree()` takes
  an optional binding-resolved copy of the tree to read heading text from.
- Headings with inline `{{token}}`s, including headings inside post
  content, now get their auto-generated anchor and table-of-contents
  entry from the token-resolved text (#858). A heading authored as
  `About {{site.name}}` on a site named "Acme" renders `id="about-acme"`
  rather than `id="about-site-name"`, and the TOC lists it as
  "About Acme". Author-set anchors are unchanged, and nothing changes
  when cms-framework's `DynamicContentResolver` isn't available. The
  extra token pass only runs when a heading or post-content block holds
  a token, and post-content HTML still resolves its tokens only once.
- Block trees in WordPress's `parse_blocks()` shape (`attrs` instead of
  `attributes`) now render through `<x-ve-blocks>` with their attributes
  intact (#863). Before, every block in such a tree rendered empty, e.g.
  a heading came out as `<h2 class="wp-block-heading"></h2>`. Headings
  in these trees also get TOC anchors and entries again, and breadcrumbs
  and business-info blocks resolve in both shapes.
- Headings and other anchored blocks inside an `artisanpack/dynamic-loop`
  no longer repeat the same `id` on every row. Rows after the first add
  the collection and row number to each anchor (`member`,
  `member-team-2`, …), and the first row keeps the template's anchor so
  table-of-contents links still land.
- The File block's download button carries `wp-element-button` in the
  Blade, React and Vue renderers, matching the editor save. A parent
  block's link color no longer repaints it, and global button styles
  reach it. The button also points at the file name through
  `aria-describedby`, like the saved markup.
- Theme.json `styles.blocks["core/…"]` rules now apply on the front end.
  The Blade compiler targeted `.wp-block-core-group` and the like, which
  no markup carries. Core blocks now use their rendered class
  (`.wp-block-group`), as in WordPress and the editor canvas.
- The table of contents landmark's default `aria-label` ("Table of
  contents") and the File block's default "Download" button text are
  now translatable in the Blade renderer.

### Security

- The `post_core`, `custom_field`, and `relation` binding sources now
  only read what a model would serialize (#848): nothing in `$hidden`, and
  only the keys in `$visible` when that list is non-empty. A binding
  such as `author.password` can no longer publish a hidden column on
  the front end. If you bind to a column your model hides on purpose,
  remove it from `$hidden` (or add it to `$visible`), or expose it under
  another name with an accessor.
- Binding keys and relation paths can no longer run model methods (#850).
  Eloquent calls any method named like an attribute or relation, so a
  `custom_field` key such as `forceDelete`, or a `relation` path such as
  `truncate.name`, could write to or empty a table while a page
  rendered. The `custom_field` and `relation` sources, and relation
  eager loading, now only call methods that declare an Eloquent
  `Relation` return type. Stored attributes, accessors, and already-loaded
  relations read as before. If you bind through a relation method with
  no return type, add one (e.g. `: BelongsTo`).

## [1.13.0] - 2026-10-10

### Upgrade notes

- **Icon endpoints now check the `visual-editor.edit-content` ability.**
  `icons/sets`, `icons/search`, `icons/svg`, and `icons/svg/sanitize`
  answer a JSON 403 to users who fail it. By default any authenticated
  user passes, so nothing changes unless you opt in: set
  `content_access.capability` (e.g. `edit_content`) to require that
  capability, or define your own `visual-editor.edit-content` gate.
  `icons/svg/sanitize` is also throttled per user
  (`content_access.sanitize_throttle`, default `60,1`).
- **Pattern previews require the same ability.** The new
  `POST /visual-editor/api/patterns/preview` endpoint is gated on
  `visual-editor.edit-content` too, so if you set
  `content_access.capability` or define the gate, users who fail it get
  a JSON 403 and the pattern cards fall back to the block-name outline.
- **Rate limits are now counted per route.** Laravel's plain
  `throttle:max,decay` keys its counter on the user alone, so the
  package's throttled routes (AI, fonts, dynamic content, SVG sanitize,
  pattern previews) shared one counter per user, and busy use of one
  could 429 another. Each now has its own rate-limit bucket. The
  configured limits are unchanged, but they now apply to each route on
  its own, so a user can make more requests in total than before.
  Setting `content_access.sanitize_throttle` or `ai.throttle` to
  `false` now turns that throttle off; `null` or `''` falls back to the
  default. Re-run `php artisan route:cache` after changing either value.
- **React/Vue navigation markup changed.** The React and Vue renderers
  now render the full navigation overlay for `core/navigation` with the
  default `overlayMenu: 'mobile'`: a menu button plus a responsive
  container, so the menu `<ul>` is nested three levels deeper than the
  plain `<nav><ul>` of 1.12, and `BlockTree` injects a global
  `<style data-ve-navigation-overlay>`. Update host CSS that targets
  `.wp-block-navigation > ul`. To use an overlay template part, pass it
  in `templateParts` with `area: 'navigation-overlay'`.
- **The React/Vue overlay CSS is in a cascade layer.** The injected
  overlay styles sit in `@layer ve-navigation`, so any unlayered host or
  theme rule now wins over them regardless of selector specificity. If
  your site relied on the overlay defaults beating a broad host rule
  (for example a global `ul { display: flex }`), scope that host rule
  or restate the overlay style you need.

### Security

- Gated the icon picker and custom SVG sanitize endpoints on a new
  post-editor-level `visual-editor.edit-content` ability, and throttled
  the sanitizer (#834). This is defense in depth recommended by
  [GHSA-r4qj-7p7h-gjxr](https://github.com/ArtisanPack-UI/visual-editor/security/advisories/GHSA-r4qj-7p7h-gjxr).
- The navigation block's custom colors are now checked against the
  CSS-value whitelist. Before, a stored value such as
  `#000; position: fixed` could add declarations to the inline `style`.
  This covers the overlay colors in all three renderers (#804) and the
  block's own `customTextColor` / `customBackgroundColor` in the Blade
  renderer.
- Hardened the navigation overlay color presets: the
  `overlayBackgroundColor` / `overlayTextColor` slugs are reduced to
  `[a-z0-9-]` before they become class names in all three renderers, so
  a stored value can no longer add classes such as `is-menu-open` (which
  left the drawer stuck open).
- A navigation overlay template part that contains a navigation pointing
  back at itself (or an A → B → A cycle) no longer recurses until PHP
  fails in the Blade renderer, which turned every page using that header
  into a 500. Overlay nesting is capped and cycles are skipped, as they
  already were in React and Vue.

### Added

- **Rendered pattern previews** (#836). Pattern cards in the inserter's
  Patterns panel, the Site Editor pattern grid and the page-pattern
  modal show a scaled, non-interactive front-end render of each pattern
  instead of a block-name outline. Previews come from the new
  `POST /visual-editor/api/patterns/preview` endpoint (up to 24 patterns
  per batch, throttled at 120 requests a minute) and are cached per
  pattern, theme, viewer, locale and host. New config:
  `pattern_previews.cache_ttl` (default 3600). See
  `docs/site-editor/Patterns.md`.
- **Hide blocks by previewed viewport in the editor canvas** (#842).
  Screen-size visibility rules now hide blocks in the canvas at the
  previewed viewport, as the front end would. A selected hidden block
  is shown dimmed so it stays editable. The master Hide setting and
  server-evaluated rules dim the block at every width. List View labels
  show "Hidden", "Hidden at some screen sizes" or "Conditionally
  visible".
- The React and Vue renderers now render the navigation block's mobile
  overlay: the menu button, the responsive dialog (Escape, backdrop
  click, focus and scroll lock), and the overlay colors. A navigation
  overlay template part (the block's `overlay` setting) replaces the menu
  inside the open drawer, as it does in Blade, so you can build a
  separate overlay layout (CTAs, contact links, social icons) without
  per-item visibility rules. Pass the part in `templateParts` with
  `area: 'navigation-overlay'`. `BlockTree` emits the overlay CSS once in
  a `<style data-ve-navigation-overlay>` tag (#804).
- The React and Vue renderers export `NAVIGATION_OVERLAY_SLOT`
  (`'overlay'`) and `NAVIGATION_OVERLAY_AREA` (`'navigation-overlay'`),
  so hosts that override the `core/navigation` renderer don't have to
  hard-code them. React block renderers receive the overlay content in
  the new `slots` prop; Vue renderers get it as the `overlay` slot.
- The page-pattern modal can now be used with custom content types. Pass
  `pattern-post-type` to `<x-visual-editor>` (or set
  `data-pattern-post-type` on a custom mount) and the modal lists the
  full-page patterns scoped to that post type. Before, it only appeared
  for `pages` and `posts`. See `docs/site-editor/Patterns.md`.
- New `ap.visualEditor.patternPreviewCacheVary` filter. Return an array
  of scalars (a role, a tenant id) to keep separate cached pattern
  previews for each value.

### Changed

- A newly inserted navigation block now binds to the menu assigned to
  the active theme's `primary` location, or, when there is none, to the
  active theme's most recently updated menu. Before, it took the first
  menu in the editor's cache. New endpoint
  `GET /visual-editor/api/menus/fallback`, which returns 204 when there
  is no menu (#841).
- If `content_access.capability` is set but the user model has no
  `hasCapability()`, `hasPermissionTo()` or `hasPermission()` method,
  the check now falls back to `$user->can( $capability )` and logs a
  warning once per request, instead of silently denying everyone. A
  non-string `capability` (such as an array) is ignored rather than
  causing a 500.
- Canvas-only block styles (visibility, position, box shadow and
  gradient border) now go into one shared `<style>` per feature in the
  canvas document's `<head>`, instead of a `<style>` next to each block.
  The extra sibling broke `:first-child`, `* + *` and block-gap
  selectors around styled blocks. Saved markup is unchanged.
- Pattern previews load more smoothly: iframes mount a few per frame,
  previews scrolled far out of view are unmounted (keeping their
  height), prefetching works inside scrolling panels, batches are sent
  in parallel with a timeout, and failures that can't succeed on retry
  (403, renderer unavailable, 429) are not refetched on every remount.
- Updated vulnerable transitive npm dependencies (`@tiptap/core`,
  `prosemirror-view`, `postcss`, `nanoid`, `source-map-js` and others),
  which clears every high-severity `npm audit` advisory in the shipped
  dependency tree.

### Fixed

- A navigation block with Overlay Visibility set to **Always** now keeps
  its menu behind the menu button at desktop widths too. Before, the
  setting acted like **Mobile** in the Blade renderer (#804).
- The inspector sidebar now mounts only one `list` InspectorControls
  slot at a time. The block inspector is unmounted while the List View
  tab is active, so navigation menu items no longer risk rendering in
  the wrong place, or nowhere, after switching tabs (#813).
- The bindings Field picker now lists custom fields for content types
  registered through the `ap.visualEditor.resources` filter (#837).
- Duplicate `clientId`s in `content.blocks` now get fresh ids, so
  selection, List View and move/remove no longer act on the wrong block
  (#840). The same now applies across entities, for example one
  navigation menu used in both the header and the footer, or a template
  and one of its template parts.
- **Navigation accessibility in all three renderers.** At desktop widths
  the menu is no longer hidden from screen readers: the responsive
  container never carries `aria-hidden`, and the dialog role and
  `aria-modal` are only set while the drawer is open. The open drawer
  now traps Tab focus, the menu button reports `aria-expanded`, and
  clicking a link inside the drawer closes it (so same-page anchors and
  client-side routing don't leave it open with scrolling locked).
- The Blade renderer falls back to the menu when visibility rules hide
  every block of a navigation overlay template part, instead of opening
  a blank drawer.
- Nested Blade renders (an overlay template part, tabs, accordions) no
  longer reset the outer render's state, which loosened the nesting
  depth cap and could repeat search-field and taxonomy-filter ids.
- The React and Vue renderers now resolve a navigation's overlay
  template part when the navigation only appears inside a synced
  pattern or a query loop. A stored
  `artisanpack/navigation-overlay-content` block is no longer rendered;
  only the renderer creates that block.
- Synced pattern references (`core/block`) now resolve in Blade renders
  of patterns stored by cms-framework, which keeps the tree in
  `block_content`. The legacy `content.blocks` envelope is still read
  as a fallback.
- Pattern preview caching: the cache is now cleared when a template
  part, menu or menu item is created, updated or deleted, and on any
  pattern update or delete, so previews that embed those by reference
  don't show stale content until the TTL expires. Each pattern in a
  batch renders as its own document, so a second header with a
  navigation overlay no longer loses its overlay CSS in the cache. A
  failure building the shared styles no longer turns the whole batch
  into a 500.
- Relative `url()` references in the theme's `style.css` now resolve in
  pattern previews (they used to point at the editor page), so theme
  backgrounds and fonts show up.
- Pattern previews measure their height again after fonts and images
  load, reset when the pattern changes, and no longer collapse to zero
  height before the first measurement.
- The editor canvas preview width is reset when the editor unmounts, so
  a remount no longer briefly hides or shows blocks against the old
  width.

### CI

- After each release, the docs site imports the package's docs and
  changelog (#835). The import job now has curl timeouts, reports
  transport failures, and runs with no `GITHUB_TOKEN` permissions.
- Added php-cs-fixer and PHP_CodeSniffer with the ArtisanPack UI
  standard (`composer lint`, `composer fix`) and applied the formatter
  across the PHP sources.

## [1.12.2] - 2026-10-08

### Upgrade notes

- **SVGs with DTD or entity declarations are now refused.** The SVG
  sanitizer (custom SVGs in the icon block, the `icons/svg/sanitize`
  endpoint, and icon-set zip uploads) now rejects any SVG that contains a
  `<!DOCTYPE>` with an internal subset or an `<!ENTITY>` declaration,
  instead of trying to strip them. The bare W3C SVG 1.0 / 1.1 public
  DOCTYPE that older authoring tools emit is still accepted. Icon SVGs
  never need DTDs, so normal icons are unaffected.

### Security

- Fixed an XML external entity (XXE) vulnerability in `SvgSanitizer` that
  let an authenticated user read local files readable by the PHP process
  through the SVG sanitize endpoint, icon block custom SVGs, or icon-set
  uploads. See
  [GHSA-r4qj-7p7h-gjxr](https://github.com/ArtisanPack-UI/visual-editor/security/advisories/GHSA-r4qj-7p7h-gjxr).
  Affects 1.1.0 through 1.12.1.

## [1.12.1] - 2026-10-04

### Upgrade notes

- **AI features now require the `visual-editor.use-ai` ability.** The
  `/ai/*` endpoints and the `AiTools` Livewire listeners are denied by
  default. The default gate allows users with the `use_ai_features`
  capability (`ai.capability` in the config). Grant it, or define your
  own `visual-editor.use-ai` gate. The endpoints are also throttled per
  user (`ai.throttle`, default `20,1`). See `docs/ai-features.md`.
- **Responsive controls are now desktop-first.** The viewport switcher
  offers All sizes / Tablet (1023px and below) / Mobile (767px and
  below). Content saved with the old `sm`–`2xl` overrides renders
  exactly as before, because those keys stay registered as legacy
  min-width breakpoints. Columns and Media & Text now stack at 767px
  instead of 781px / 600px.
- **Sites with no theme get default styles.** When no theme is active,
  `<x-ve-blocks-styles>` now declares the editor's font-size and
  palette presets and a zero-specificity baseline stylesheet. Set
  `default_styles` to `false` to turn the baseline off.

### Added

- **Desktop-first `tablet` and `mobile` breakpoints** (#820).
  Device overrides apply at their `maxWidthPx` and below and inherit
  from the next larger device. Every emitter (position, box shadow,
  gradient border, animation, responsive spacing, column widths and
  counts, flex values) and the React and Vue renderers build their
  media queries through `BreakpointRegistry::mediaQuery()`. The
  switcher tooltip and the inspector chip now say "and below".
- **Theme-less front-end defaults** (#821). The font-size and palette
  presets the editor's pickers offer, with their `.has-*` classes,
  plus content / wide size, block gap, and root padding defaults. A
  theme overrides all of them. Package CSS gives every
  `var(--wp--*)` / `var(--ap-*)` a fallback. The new `default_styles`
  option (`auto` | `true` | `false`) controls an optional baseline
  stylesheet, scoped to block output so it can't restyle a Tailwind
  host's chrome.
- **Heading-level picker** (#825). The upstream h1–h6 heading
  variations are ported to `artisanpack/heading`, so the editor offers
  a heading-level picker again.
- **AI payload limits** (#828). `ai.payload_limits` (`max_blocks`,
  `max_depth`, `max_bytes`, `max_text_chars`) bound the heading check,
  next-block and layout suggestions, and content rewrites. A request
  over a limit is rejected, not truncated. The heading check sends only
  block ids, names, levels, and plain text.

### Fixed

- **AI endpoints were never registered.** The route guard called
  `class_exists()` on the `FeatureRegistry` interface, which always
  returns false, so every `/ai/*` route was missing. It now uses
  `interface_exists()`.
- **AI alt text could read server files.** Alt text now accepts only
  base64 / data URIs and image URLs on the site's own host (plus
  `ai.alt_text.allowed_hosts`) on the default port, so a server file
  path can no longer be read and sent to the AI provider.
- **Heading-hierarchy check never ran on editor content** (#825). The
  agent only read the block type from `type` / `blockName` and missed
  `artisanpack/heading`, so the editor's block tree always came back
  "no issues". `BlockShape::readName()` now reads `name`,
  `blockName`, or `type`.
- **Synced patterns in theme templates were saved as
  `artisanpack/block`** (#824), a block name the editor never
  registers. Theme `wp:block` references now stay `core/block`, refs
  already saved as `artisanpack/block` are translated back on read, and
  the editor registers upstream `core/block` again.
- **Aligned images and blocks didn't float** (#819). `alignleft` /
  `alignright` blocks now float under any flow or constrained layout
  in all three renderers and the editor canvas, line up with the
  content column inside constrained post content and groups, sit level
  with the text beside them, and stack at the mobile breakpoint.
  Tailwind's `size-full` utility no longer stretches image figures.
- **Rich-text edits at a breakpoint or state were dropped.** The
  responsive attribute-path helpers treated `RichTextData` as an empty
  object. They now only walk plain objects.
- **Template parts rendered empty after saving a template in the site
  editor** (#822). Saved templates store template-part references as
  `artisanpack/template-part`, but the front-end inliners only expanded
  `core/template-part`, so every header, footer, and other part in a
  saved template rendered as an empty wrapper. The Blade, React, and Vue
  inliners now expand both names, keep the original name on the output,
  and always replace any `innerBlocks` snapshot saved on the reference
  with the live part. Synced-pattern references saved as
  `artisanpack/block` had the same gap and now resolve too. The shared
  name lists live on `BlockShape::TEMPLATE_PART_NAMES` and
  `BlockShape::PATTERN_REF_NAMES`.
- **Release sourcemap archive failed to attach** — the repository uses
  GitHub's immutable releases, which block adding assets once a release
  is published, so the v1.12.0 `dist-sourcemaps-*.tar.gz` upload was
  rejected. The release workflow now creates the release as a draft,
  attaches the archive, then publishes it (and still publishes if the
  upload fails).

## [1.12.0] - 2026-09-29

### Upgrade notes

- **Requires `artisanpack-ui/cms-framework` 2.11 or later, and a
  migration.** cms-framework 2.11 adds the `menu_items.block_attributes`
  column that stores navigation-item settings such as block visibility.
  Run `php artisan migrate` after updating.
- **`artisanpack/navigation` is retired in favor of `core/navigation`**
  (#808). Existing `artisanpack/navigation` blocks keep rendering and
  are converted to `core/navigation` the first time they're edited.
- **Site-editor API writes now require the site-editor access gate.**
  Creating, updating, or deleting templates, template parts, global
  styles, patterns, menus, and menu items goes through the bound
  `SiteEditorAccessGate` and returns `403` when it denies. Before, any
  logged-in user could make these changes. This includes the post
  editor's **Convert to pattern** action. Read routes are unchanged. See
  `docs/site-editor/Access-Gate.md`.

### Added

- **Navigation overlay template parts in the site editor** (#809) —
  `navigation-overlay` joins the site editor's template-part areas,
  so overlay parts get their own "Navigation Overlay" filter chip,
  row badge, and create/inspector area option. That's the area
  `core/navigation`'s Overlay panel scopes its template picker to.
- **Per-viewport visibility on navigation items** (#798, #806) — the
  Visibility panel now appears on navigation links and submenus, and
  their settings (along with other block settings that have no
  dedicated menu column, such as animations and bindings) are saved
  with the menu item. `docs/site-editor/Navigation.md` documents the
  mobile-overlay-only CTA recipe.

### Changed

- **The navigation block is upstream `core/navigation` again** (#808)
  — the `artisanpack/navigation` fork is retired. Upstream looks up
  its parent block by the literal name `core/navigation`, so the fork
  lost the selection after every menu-item insert, which caused the
  editor chrome to flicker, focus to drop, and a blank List View.
  `core/navigation` and its inner-block family are registered
  directly, and `artisanpack/navigation` remains as a hidden stub that
  converts itself on first edit.

### Fixed

- **Navigation menu picker didn't list or create menus** (#797) —
  "Create menu" in the `core/navigation` placeholder sends no slug, so
  `POST /visual-editor/api/menus` now derives one from the title
  (adding `-2`, `-3`, … on a collision), accepts the block's
  `content`, and ignores `status`. The block canvas shows a menu
  picker and Create button while no menu is selected.
- **Icon picker showed no Font Awesome icons in Composer installs**
  (#800) — the Font Awesome Free payload (2,860 icons) was generated at
  build time into a git-ignored directory, so it never reached the
  Composer distribution. It's now committed and shipped.
- **Navigation editing flickered and lost focus** (#808) — every
  menu save, including plain updates, wiped the navigation-menu query
  results, so the block briefly unmounted its editor. Only creating a
  menu invalidates those queries now, and the core-data shim keeps
  block references stable across a save so focus and List View
  survive.
- **Spacing presets overrode Global Styles and mismatched some slugs**
  (#814) — the always-on spacing preset declarations came from the raw
  theme.json and were emitted after the resolved global styles, so they
  overrode style-variation and user spacing sizes. They're now built
  from the resolved settings and emitted first, so resolved values win.
  All renderers and the editor now share one preset slug rule
  (`big_gap` → `big-gap`); Blade previously kept the underscore and
  referenced an undeclared property. The editor canvas also declares
  the `@wordpress/style-engine` form of slugs like `2xl` (`2-xl`), and
  preset values with unbalanced parentheses, quotes, or control
  characters are rejected so they can't break the surrounding CSS.
- **React and Vue hosts lacked default spacing presets and the
  navigation gap rule** — `<LayoutBaseline />` now includes both, at
  zero specificity so `<GlobalStyles>` still overrides them.
- **Vue server rendering escaped the layout baseline CSS** —
  `<LayoutBaseline />` rendered its CSS as text, so SSR turned every
  `>` into `&gt;` and broke child-combinator rules. It now renders the
  CSS as raw markup, like `<GlobalStyles>`.
- **Converting legacy `artisanpack/navigation` blocks lost content**
  (#808) — the retired block's stub declared none of the 1.11 block
  supports, so alignment, anchor, typography, spacing, layout, and
  animation settings were stripped on load and lost on the next save.
  If the one-time conversion couldn't run (for example inside a locked
  pattern), saving also dropped the block's inline menu links. The stub
  now keeps every 1.11 setting and its inner blocks, `core/navigation`
  supports animations, and a block that can't convert shows a notice
  with an **Update block** action when the block is nested and
  editable. Registering the navigation block family twice no longer
  logs errors.
- **Template parts showed stale content, and inline template-part
  edits were lost** — the core-data shim reused its previous block
  tree whenever the block names and counts matched, hiding text-only
  changes, so a template kept showing an inline template part's old
  content after the part was saved. It also autosaved every inline
  entity, sending template parts to a URL the API can't route, and
  the silent 404 left the edits stranded. Changed attributes now
  always surface (with stable block IDs), only navigation menus
  autosave, and staged edits give way when the owning editor saves a
  newer version.
- **Menu and template-part requests could return 500 or bypass limits**
  — menu `content.blocks` is now validated (tree depth and node count,
  types of the column-mapped attributes, and size and nesting of other
  block settings), so malformed input returns `422`. Template-part
  string `content` now gets the same tree limits as block-array
  content. Menu saves skip `block_attributes` when that column's
  migration hasn't run yet, and two menus created at the same moment
  no longer collide on a derived slug.
- **Every build dirtied the Font Awesome index** — `sync-fa-icons`
  no longer writes a `generatedAt` timestamp into the committed
  `resources/icons/font-awesome/index.json`.
- **Renderer parity manifest was out of date** — `artisanpack/howto`,
  `artisanpack/toc`, and `artisanpack/toc-list` are now listed in
  `packages/renderer-parity.json`, and CI runs `npm run verify:parity`.
- **Failed navigation autosaves were silent** — a failed menu autosave
  now shows an error notice with a Retry action, and pending autosaves
  are sent when the page is closed instead of being dropped.
- **Invalid `<div>` inside `<ul>` for per-viewport visibility on list
  items** (#806) — a screen-size visibility rule wrapped list-item
  blocks (`core/navigation-link`, `core/navigation-submenu`,
  `core/list-item`, `artisanpack/list-item`) in a `<div>`. The scope
  class now goes on the `<li>` itself in the Blade, React, and Vue
  renderers.
- **Navigation ignored the Block spacing setting** (#814) — the
  navigation block's `style.spacing.blockGap` now sets the gap between
  items in the editor canvas and in the Blade, React, and Vue
  renderers. Spacing preset custom properties
  (`--wp--preset--spacing--*`) are now always declared, so preset
  picks resolve on themes that don't define `spacingSizes`.
- **Navigation Overlay panel showing inside overlay templates** (#809)
  — the package now registers a minimal `core/editor` store
  (`vendor/editor-context-store.ts`) that tracks which template or
  template part the site editor has open. Upstream
  `isWithinNavigationOverlay()` reads it, so a `core/navigation`
  block placed inside a navigation-overlay template part no longer
  offers its own Overlay panel, and a newly inserted nav there
  defaults to an always-expanded layout. The entity editor also
  primes the core-data shim with the loaded record before its blocks
  reach the canvas, because upstream checks this context only once,
  when the block mounts.
- **Navigation Create overlay did nothing (and could hang Safari)** (#809)
  — with no `core/navigation-overlay` pattern registered, upstream
  seeded the new part with `createBlock('core/paragraph')`. Neither
  that block nor its `core/missing` fallback is registered here, so
  `createBlock` recursed until the stack overflowed and the error was
  swallowed into a snackbar the editor doesn't render. The shared
  editor settings now ship a `core/navigation-overlay` pattern (a
  vertical `core/navigation`), so new overlays start with a menu.
- **New overlay template parts saved empty** (#809) —
  `POST /visual-editor/api/template-parts` accepted Gutenberg's
  serialized-string `content` but only persisted `content.blocks`.
  String content is now parsed into the stored block tree, and the
  site editor mints `clientId`s for server-parsed blocks so they render
  in the canvas.
- **Create overlay dropped the nav block's new `overlay` setting** (#809)
  — jumping to the new part discarded the current template part's
  unsaved edits. The site editor now saves the open entity before
  following a block-library navigation, and stays put if that save
  fails.
- **Empty menu selector in the navigation block's List View** (#809) —
  the core-data shim's `useResourcePermissions()` denied everything,
  hiding "Create new Menu" and "Import Classic Menus". It now grants
  create / update / delete on `wp_navigation` (backed by
  `MenuController`; server-side authorization still applies), and
  `wp_navigation` edits made through `useEntityProp` (the "Menu name"
  control) save on a debounce like menu-item edits already do.

- **Site editor inspector missing default sections** (#799) — the
  site editor's `ensureEditorBoot()` now mirrors the post editor's
  registrations for Motion preferences, Entrance/Hover/Continuous
  animation, Visibility, Block bindings, Dynamic Content bindings
  source, and the Dynamic-Content-aware `core/link` format, so a
  block selected in a template or template part shows the same
  inspector panels as the same block in a post. The
  `BlockColorContrastChecker` suppression + warning fill are now
  shared between both boots so the color-picker-drag crash sidestep
  applies in the site editor too. `disableContrastCheckerOnBlocks()`
  moved from `editor/editor-app.tsx` to `editor/contrast-warning.tsx`
  as an exported companion of `registerContrastWarning()`; both
  registrations are idempotent across boot paths. A new Vitest
  (`site-editor-boot-parity.test.tsx`) asserts every registrar fires
  once from `ensureEditorBoot()` so a future feature module cannot
  be silently omitted from one boot path.

## [1.11.0] - 2026-09-17

### Added

- **Image block width control** (#790) — the first-party
  `artisanpack/image` block gains a width control in the inspector
  with S/M/L/Full preset buttons (25/50/75/100%), a custom-width
  numeric input with px/% unit toggle, and drag-to-resize on the
  canvas.
- **`SupportsServerReadableFormats` optional contract** for
  `FontProvider` implementations (#794). Providers that opt in
  declare which server-readable formats (TTF/OTF) they can supply
  alongside their default web-optimized output (usually WOFF2), and
  the installer fetches each one and persists a sibling `FontFace`
  row per format. Server-side renderers — the SEO package's OG image
  generator, PDF libraries, image tools — query the DB for a
  server-readable face and get a real path they can feed to
  GD/FreeType or a PDF renderer.
- **`GoogleFontsProvider` now serves TTF alongside WOFF2** (#794).
  Google's CSS2 API returns `format('truetype')` URLs when queried
  with a pre-WOFF2 User-Agent; the provider now sends that UA when
  asked for a `ttf`-format face, downloads the resulting `.ttf` file
  from `fonts.gstatic.com/s/…`, and gates the bytes with the
  TrueType magic-byte signature.
- Widened the `ve_font_faces` unique index to
  `(font_id, weight, style, format)` so multi-format faces coexist.

### Changed

- **`FontsCssGenerator` dedupes multi-format faces per
  `(weight, style)`** and prefers WOFF2 > WOFF > OTF > TTF for the
  browser bundle (#794). A family with both a WOFF2 and TTF row emits
  a single `@font-face` block pointing at WOFF2 — the TTF row exists
  for server-side consumers, not for the browser.
- **`FontInstaller::persistFace` now keys on
  `(weight, style, format)`** rather than `(weight, style)` (#794);
  a re-install that drops a previously stored format still cleans up
  its orphan row + file via `reapObsoleteFormats()`. A supplementary
  format fetch that fails is logged and skipped rather than failing
  the install.

### Fixed

- **Block toolbar escapes editor bounds when the canvas scrolls**
  (#791) — Gutenberg's `useBlockToolbarPopoverProps` never rebinds
  on scroll (its resize hook even tries to call a non-existent
  `addEventHandler`), so a block scrolled to the top of the canvas
  had its floating toolbar float above the editor chrome. Docking
  the toolbar into the editor chrome keeps it inside the canvas
  scroll boundary.

## [1.9.0] - 2026-09-05

### Added

- **Location-page starter pattern** (#764) — a new `page/location`
  seed pattern ships alongside `page/blank` in the page-pattern
  inserter. It composes the business-info blocks (`business-address`
  with map, `business-hours`, `business-phone`, `business-email`)
  side-by-side with a `reviews` block underneath, giving multi-location
  sites a ready-made starting layout for location landing pages.
  Scoped to the `page` post type so it only surfaces where a landing
  page is being authored; contributor-registered patterns of the same
  slug still win. The pattern is pure block markup with no shortcodes
  or PHP, so it round-trips SSR-safely through every renderer.
- **Table-of-contents block with heading anchor generation** (#760) — a
  new `artisanpack/toc` block auto-derives a nested list of anchor
  links from the page's headings. A new `TocResolver` pre-render pass
  walks the block tree, stamps a stable, unique slug into the `anchor`
  attribute of every `core/heading` / `artisanpack/heading` that lacks
  one (existing author-set anchors are preserved and duplicate slugs
  receive a `-1`, `-2`, … suffix), and stamps `_resolvedItems` onto
  every TOC block filtered by its `minLevel` / `maxLevel` range.
  Renders as a `<nav>` landmark with a nested `<ul>` (or `<ol>` when
  the ordered toggle is on) inside; smooth in-page scrolling activates
  automatically via `html:has(.ap-toc) { scroll-behavior: smooth }`.
  React and Vue renderers ship the same DOM when the resolved payload
  reaches them.
- **Dedicated HowTo block with HowTo JSON-LD** (#759) — a new
  `artisanpack/howto` block persists an ordered list of steps (name,
  text, optional image + alt) plus a block-level name and description,
  and the Blade renderer emits a `HowTo` JSON-LD script at request time.
  Emission is toggle-controlled so pages that already surface HowTo
  schema elsewhere (a page-level schema block, a head manager) can turn
  it off to avoid double-emission. React and Vue renderers ship the
  same DOM and skip the JSON-LD script so downstream head managers stay
  in charge of schema in those environments.
- **Dedicated FAQ block with FAQPage JSON-LD** (#758) — a new
  `artisanpack/faq` block persists question / answer pairs and the
  Blade renderer emits a `FAQPage` JSON-LD script at request time.
  Emission is toggle-controlled via the block's `emitSchema` attribute
  so pages that already surface FAQ schema elsewhere can turn it off.
  React and Vue renderers ship the same DOM and skip the JSON-LD
  script.
- **Accordions FAQ schema toggle** (#757) — the parent
  `artisanpack/accordions` block gains a `faqSchema` inspector toggle.
  When on, the Blade renderer walks the child accordion panels and
  emits `FAQPage` JSON-LD alongside the accordion markup; question /
  answer text is stripped to plain text with `wp-block-*` wrappers
  removed so Google Search Console does not flag it as noise.
- **Business-info blocks** (#761) — four new dynamic blocks
  (`artisanpack/business-hours`, `artisanpack/business-address`,
  `artisanpack/business-phone`, `artisanpack/business-email`) that
  render a host-supplied business envelope resolved through the
  `ap.visualEditor.businessInfo` filter. The address block composes
  its map embed URL (host-supplied, then Google Maps if a
  `artisanpack.visual-editor.business.google_maps_api_key` config is
  set, otherwise OSM around the address lat/lng) and drops the iframe
  when no URL can be composed. A new
  `GET /visual-editor/api/business-info` endpoint (guarded by the
  standard `api` + `auth` middleware group) serves the envelope to the
  editor previews.
- **Reviews block** (#763) — `artisanpack/reviews` renders review cards
  from payloads supplied by any host / package / plugin / theme through
  the `ap.visualEditor.reviews.collectReviews` filter. Deliberately
  emits no `Review` / `AggregateRating` JSON-LD (self-serving review
  markup is ineligible for Google's rich result). URL fields are
  scheme-allowlisted to `http`, `https`, `mailto`. The `collectReviews`
  filter is memoised per render (keyed on the source + limit attrs) so
  a repeated Reviews-block reference on the same page invokes each
  host resolver only once.
- **Dynamic Content: host-registerable token sources** (#762) —
  `VisualEditor::registerDynamicContentSource($definition)` lets a
  host app / another package / a cms-framework plugin or theme
  contribute a singleton or collection token source resolved at render
  time. Host sources merge with cms-framework's DB-authored types in
  the editor inserter and the binding resolver; a slug collision
  resolves to the host registration.
- **Host-provided palette / font-size / font-family / spacing-size
  presets** (#773) — `config('artisanpack.visual-editor.presets')` lets
  an application that ships no `theme.json` and no cms-framework
  contribute editor presets. Layered on top of `theme.json` in
  `useThemedEditorSettings` so host entries override same-slug entries
  in the theme or the package defaults. Per-list
  `mode: 'replace' | 'append'`; an empty `replace` clears the base
  list. Follow-up review (#773) canonicalises theme slugs, makes the
  reject-color pattern whitespace-safe, and clarifies empty-replace
  semantics.
- **Post-terms taxonomy UI** (#771) — `artisanpack/post-terms` now
  exposes a Settings-sidebar taxonomy picker and one inserter variation
  per registered taxonomy. Registered taxonomies come from
  `config('artisanpack.visual-editor.taxonomies')` via the new
  `TaxonomyRegistry`; the editor mount stamps them as
  `data-taxonomies` for both the post and site editors.

### Changed

- **`AiController` + `AiTools` migrate onto the shared
  `HandlesAiFeatureResponses` trait** (#769) — the exception-ladder
  translation for `FeatureDisabledException`,
  `MissingCredentialsException`, and `FeatureError` now lives in
  `artisanpack-ui/ai` 1.2.0's shared trait, so the visual-editor's AI
  surfaces produce identical envelopes to every other AI-consumer
  package.
- **Consume the shared
  `ArtisanPackUI\Ai\Testing\FakeAgentPrompter` (drop the local copy)**
  (#770) — the local `tests/Support/FakeAgentPrompter.php` is removed
  and `AiAgentTestSetup` now imports from the canonical namespace
  `artisanpack-ui/ai` 1.2.0 ships. No composer bump required (the
  constraint was already `^1.2`).

### Fixed

- **Every `<button>` gets a cursor, a disabled cursor, and a
  focus-visible ring** (#765) — classless React + Blade fallthrough
  buttons in the editor no longer render with the default I-beam and
  no focus outline. A new package-level base rule set in
  `resources/js/visual-editor/a11y.css` (imported by the editor
  bootstrap) applies the treatment without touching individual block
  styles.
- **All three renderers emit CSS for `style.dimensions.minHeight` and
  `style.dimensions.aspectRatio`** (#776) — the block-supports
  compiler never read `style.dimensions.*`, so `group` / `quote` /
  `verse` / `pullquote` / `post-content` blocks that opted into
  `supports.dimensions.minHeight` silently dropped the editor-authored
  value at render time. `applyDimensions()` in `BlockSupports` (Blade)
  and mirror helpers in the React (`support/dimensions.ts`) and Vue
  (`support/dimensions.ts`) renderers now emit both properties (with
  preset expansion) so authored dimensions reach the wrapper across
  Blade SSR, React SSR, and Vue SSR alike. A new
  `group-dimensions-min-height` fixture in
  `packages/renderer-markup-parity` locks the parity in.
- **HowTo block: image URLs are scheme-allowlisted before rendering**
  (release review H-05) — the HowTo Blade renderer now runs each step's
  `imageUrl` through `UrlSanitizer::safe()` before it reaches both the
  `<img src>` attribute and the `HowToStep.image` entry in the JSON-LD
  payload. Values outside the `http` / `https` / `mailto` allowlist
  are dropped, matching the Reviews block's URL handling on this same
  release cycle.

## [1.8.0] - 2026-09-01

### Added

- **Runtime block registration for third parties** (#766) — a downstream
  package (a host app, a module, or a WordPress-style plugin/theme) can now
  contribute a block that appears in the editor inserter, exposes editable
  attributes, and renders on the public page **without rebuilding this
  package's editor bundle**. Two paths:
  - **Server-driven (PHP only).** `VisualEditor::registerServerBlock($name,
    $metadata, $render)` registers a block type's metadata and its server
    render callback in one call. The editor fetches the server-registered
    types at boot (each server-rendered type is flagged `apServerRender` by
    the blocks endpoint) and synthesizes a generic edit component — a
    `ServerSideRender` canvas preview plus inspector controls inferred from
    the block.json `attributes` schema (with a per-attribute `apControl`
    override). No client build required.
  - **Client escape hatch.** `window.ApVisualEditor.registerBlockType(name,
    settings)` / `registerBlocks(modules)` register a block with a bespoke
    client `edit`. Calls made before the editor boots are queued and flushed
    on boot; later calls register immediately. Host `edit`/`save` components
    build against the editor's own singletons, exposed on
    `window.ApVisualEditor.libs`. The same surface is mirrored on
    `window.ApSiteEditor` for the site editor.

## [1.7.1] - 2026-08-28

### Fixed

- **Inner-block container wrappers now round-trip through `save()`** (#747) —
  dynamic container blocks shipped `save() => null` while hosting inner blocks
  inside a `useBlockProps` wrapper, so Gutenberg serialized a wrapper the null
  save could not reproduce and flagged persisted markup as "unexpected or
  invalid content" — breaking the single-post template and many patterns. Each
  block (`previous-post`, `next-post`, `related-posts`, `single-content`,
  `accordion`/`accordions`/`accordion-body`/`accordion-title`, `tabs`/
  `tab-section`, `search-filters`, and the post-type search-results blocks) now
  mirrors its edit wrapper in `save()`. `related-posts` disables the `className`
  support, and `grid`/`grid-item` emit their layout classes with a v1
  deprecation so pre-existing content auto-migrates.
- **Columns and Grid blocks now emit their `blockGap` gap CSS** (#748) — both
  blocks declare `spacing.blockGap` support, but the value was never
  materialized as an actual `gap`, so they rendered flush regardless of the
  author's gap value or `theme.json`'s `styles.spacing.blockGap`. A
  `gap: var(--wp--style--block-gap, <default>)` rule now applies to
  `.wp-block-columns` and `.ap-grid` in both the editor and Blade frontend
  stylesheets. Markup and Blade/React/Vue renderer parity are unaffected.
- **Icon block no longer downgrades to a paragraph inside a group** (#749) —
  `artisanpack/icon` is a dynamic block but its `save` serialized a real
  `<div><span>` wrapper into post content, so any drift surfaced as "unexpected
  or invalid content" and "Attempt Block Recovery" downgraded the icon to a
  paragraph. `save` now returns `null` following the package's dynamic-block
  convention, and a new deprecation re-validates and migrates icons saved by
  earlier builds.
- **Dark theme canvas background restored in both editors** (#754) — a theme
  with a dark `styles.color.background` left the editing canvas white while
  block-level backgrounds still rendered. In the post/page editor the canvas
  color tokens are now emitted on `.editor-styles-wrapper` (co-located with the
  scoped presets they reference) instead of `:root`; in the site editor the
  entity-canvas surface uses `flex: 1 0 auto` so its dark background grows to
  full content height instead of clamping to the viewport.

## [1.7.0] - 2026-08-27

### Added

- **Font Library** (#627) — a site-editor front door to font management,
  reachable from the **"Manage fonts…"** entry point on every Typography
  control. The modal browses **Google Fonts** and **Bunny Fonts** through
  keyless catalog endpoints, previews families same-origin, and installs the
  weights and styles you choose. Admins can also upload their own `.woff2`,
  `.woff`, `.ttf`, and `.otf` faces on the Custom Upload tab. Every installed
  family becomes a font-family preset available in each picker, resolved
  through a generated `fonts.css` on both the canvas and the public site.
  Installs are **self-hosted by design**: face files are fetched server-side
  once and served from your own domain, so visitors' browsers never connect to a
  provider CDN — removing the third-party font requests that are a common GDPR
  concern (self-hosting alone does not by itself make a site GDPR-compliant).
  Third parties can add their own catalog by
  implementing the `FontProvider` contract and registering it through the
  `ap.visualEditor.registerFontSources` filter (#629); the REST surface (#634),
  modal (#635), font-family picker (#636), and same-origin previews (#741)
  round out the feature. New documentation: [Fonts](docs/fonts.md) (user guide,
  including the self-hosting/GDPR notes), [Font Providers](docs/font-providers.md)
  (developer guide with a worked example), plus the
  `ap.visualEditor.registerFontSources` reference in
  [Hooks and Events](docs/Hooks-and-Events.md) and a Font Library entry point
  in [Site Editor](docs/site-editor.md).
- **Inline icons in content** (#717/#718) — a new inline-icon RichText format
  lets you drop an icon inline with text in any rich-text field, with an
  optional per-icon color. The React and Vue renderers gained a matching
  `InlineIconContentHydrator` so the icon renders identically on the public
  site.
- **Dynamic Content tab in the inline link popover** (#662/#746) — the link
  toolbar's popover now carries a Dynamic Content tab, so a link target can be
  bound to a dynamic source (post, term, author, or site field) instead of a
  hard-coded URL, resolved server-side at render time.
- **Photo Grid wrapper ported to the React and Vue renderers** (#714) — the
  photo-grid wrapper markup and its scoped grid CSS now emit identically from
  all three renderers, so a gallery laid out as a photo grid renders the same
  on the canvas and the public site regardless of renderer.
- **Column responsive-width scope ported to the React and Vue renderers**
  (#712) — per-breakpoint column widths now mint the same `ve-w-<hash>` scope
  class and CSS across Blade, React, and Vue (the `hash( 'xxh3', … )` token is
  ported byte-for-byte, including long width maps).

### Changed

- **PHP 8.3 is now the minimum supported version** (was 8.2) — `composer.json`
  requires `php: ^8.3`, the CI matrix runs 8.3 and 8.4, and `CONTRIBUTING.md`
  is updated to match. This follows cms-framework 2.x, which already requires
  PHP 8.3+. **Hosts still on PHP 8.2 must upgrade to install or update this
  release.**

- **The `post-comments-form` block fires `ap.cmsFramework.comments.form.action`**
  (cms-framework#245) — this block is the only fire site in the ecosystem for
  the comment form's action filter, but it emitted the un-prefixed legacy name
  `comments.form.action`. The CMS Framework's 2.5.0 hook-rename wave listed that
  filter as moved under `ap.cmsFramework.*` and its CHANGELOG said so, but the
  alias never shipped there, so the documented new name resolved to nothing and
  hosts had to stay on the old one. cms-framework 2.8.0 lands the missing alias
  and this release moves the fire site onto the canonical name to match. The
  filter keeps a cms-framework namespace rather than gaining a visual-editor one
  because comments are that package's domain and its `POST /api/v1/comments`
  endpoint is this filter's default value — the same emitter-is-not-owner split
  as `ap.rbac.roleRegistered`. **No action is required:** `comments.form.action`
  is registered as a deprecation alias here as well as in cms-framework, so a
  subscriber on either name still fires, in either direction. The alias is
  declared on this side deliberately — cms-framework is not a dependency of this
  package, so a host rendering this block without it would otherwise have no
  alias registered at all. Hosts should migrate to
  `ap.cmsFramework.comments.form.action`; the old name logs a deprecation notice
  the first time it resolves per request.
- **Hosts that published this package's block views must re-publish to pick the
  new hook name up** — `resources/views/vendor/visual-editor-renderer-blade/`
  shadows the package's own partials, so a published copy of
  `blocks/artisanpack/post-comments-form.blade.php` keeps firing the old name
  until it is refreshed with
  `php artisan vendor:publish --tag=visual-editor-blade-views --force`.
  Behavior is unchanged either way thanks to the alias; this only affects which
  name is emitted and therefore whether a deprecation notice is logged.

### Fixed

- **The renderer-blade suite registers `HooksServiceProvider`, so hook aliases
  actually resolve under test** — its `TestCase` listed only the visual-editor
  and renderer providers. `HookDeprecations` is bound as a singleton by the
  hooks provider, so without it every `app( HookDeprecations::class )` call
  resolved a *fresh* instance: the aliases registered in
  `VisualEditorServiceProvider::boot()` were written to an object nothing else
  ever read, and no legacy hook name resolved. The failure was silent rather
  than loud — the canonical name still fired normally, so only a test
  specifically asserting an *old* name would catch it. The root suite's
  `TestCase` has always registered the provider; the two are now consistent.
- **The post-editor canvas applies the theme's global font-family**
  (`aa73c5cc`) — the canvas painted body text in the default sans stack while
  the real front end used the theme's configured font, so type in the editor
  did not match the published page. The canvas now derives its base
  font-family from the resolved global styles.

### Security

- **A shared CSS-value whitelist now gates every renderer `<style>` emission**
  (#720/#721) — the Blade, React, and Vue renderers run all interpolated CSS
  values (column widths, photo-grid metrics, inline styles) through one shared
  grammar that drops anything outside a safe character/token set, closing
  `url()`, `expression()`, and comment-digraph injection vectors uniformly
  across renderers. The grammar is byte-identical across the three packages.

## [1.6.0] - 2026-08-07

### Added

- **Composed view: edit your content inside the template it will render in**
  (#618) — the post-editor canvas showed content on a bare ground, so the only
  way to see a heading under the real site header, or to judge the spacing where
  the content ends and the footer begins, was to open Preview in another tab and
  lose the editing context. A **View with template** switch in the top bar now
  renders the resolved template's chrome above and below the block canvas,
  inside the same iframe, so it picks up the theme's compiled CSS with no
  server-side render round-trip. The chrome is a genuine read-only preview: each
  region mounts through Gutenberg's block-preview provider in its own isolated
  block-editor store, so it is non-selectable and non-editable rather than
  merely `lock`ed — and the content editor's own block tree is never swapped, so
  selection, undo history and unsaved changes all survive a toggle, which the
  earlier composed-tree approach could not manage without freezing the editor.
  The template is cut at its `post-content` slot into header and footer chrome,
  cloning any layout wrapper the slot is nested inside onto both sides so the
  surrounding layout still reads correctly; `post-title`, `post-author`,
  `post-date` and `post-featured-image` blocks in the chrome bind against the
  post being edited rather than the template's sample data. A sticky in-canvas
  ribbon names the resolved template and carries an **Edit template ↗** CTA
  that deep-links the site editor by slug (`?entity=template&slug=…`, resolved
  to an entity id on mount and rewritten to the canonical URL) in a new tab —
  new tab on purpose, since navigating a post editor holding unsaved content
  away to edit chrome would be a data-loss trap. Backing it is a new
  `GET {resource}/{id}/applied-template` endpoint
  (`ResourceAppliedTemplateController`), gated on the same `view` authorization
  as the content endpoints, resolving the template and its referenced template
  parts. Its `?template=` parameter overrides the slug persisted on the model so
  a preview taken right after a template change does not race the debounced
  save, and a blank value is meaningful — it says the selection was *cleared*.
  A miss returns 200 with a discriminated `{status: "missing", reason}` body
  rather than a 404, so the routine "no template chosen" case does not litter
  devtools; the client turns it into a built-in default template (post title,
  featured image, content slot) plus a toast *and* a standing notice, so the
  reason is still on screen a minute later when the toast has auto-dismissed.
  Documented in `docs/post-editor/Composed-View.md`.
- **Blade-vs-JS markup parity check** (#704) — nothing guarded against the three
  renderers drifting on markup: `scripts/verify-renderer-parity.mjs` compares
  block *names* only, and `parity.test.ts` compares React against Vue with Blade
  left out of both. That is why the Blade-only #700 fix could land green while
  making Blade disagree with the JS renderers, undetected until #702. A shared
  fixture set (`packages/renderer-markup-parity/fixtures.json`) is now rendered
  through all three renderers: the Pest suite writes canonicalized golden files
  from `<x-ve-blocks>`, and the vitest suite asserts the React and Vue SSR output
  matches them, so drift in any renderer fails CI at the point it is introduced.
  Fixtures cover every layout-supporting wrapper — `group` (flow / constrained /
  flex / grid, plus the Flex Layout panel and Photo Grid cases), `row`, `stack`,
  `columns`, `buttons`, `post-content` with and without a stored layout, and
  `post-template` across all three saved layout shapes and its `columns`
  bounds. Canonicalization is narrow and documented: whitespace, attribute order
  and CSS declaration separators are normalized; element names, class tokens and
  attribute values are not. Known divergences are declared explicitly in the
  fixture manifest with a tracking issue rather than papered over with loose
  matching. The two canonicalizers are held to the same definition of
  whitespace — JavaScript's `\s` matches U+00A0 while PCRE's does not, so a
  fixture containing `&nbsp;` would otherwise have produced a golden the JS side
  could never match — and a `dropClassTokensMatching` pattern that fails to
  compile now throws rather than being silently read as "no match", which would
  have written the token into the golden while the JS side dropped it.

### Fixed

- **`core/html` blocks now render their saved markup** (#690) — the block
  shipped neither a `block.json` manifest nor a Blade partial, so a
  `<!-- wp:html -->` block in a theme template or `post_content` fell through
  to the unknown-block fallback and emitted an empty
  `data-ve-unknown-block` wrapper with its content dropped. An
  `artisanpack/html` manifest now declares `content` with `source: "raw"` —
  activating the matcher `BlockAttributeSourceResolver` already implemented
  but no bundled manifest used — and the paired `core/html` /
  `artisanpack/html` partials emit the recovered markup verbatim, with no
  wrapper element, matching Gutenberg's `<RawHTML>` save. The markup is
  deliberately not run through `kses()`: the partial sits inside the trust
  boundary documented on `BlockMarkupHydrator`, and sanitizing here would
  mangle the `<script>` / `<iframe>` / `<svg>` payloads the block exists to
  carry. Registration is server-side only — the editor bundle does not yet
  register a client-side HTML block. **Advisory for hosts:** unlike WordPress
  there is no `unfiltered_html`-style per-user capability gate, so a site that
  grants low-trust authors the block editor should sanitize `core/html` content
  on save.
- **Site-editor global styles no longer leak onto the admin chrome** (#679) —
  the theme global-styles emitter declares theme.json root spacing and the
  `--wp--preset--*` tokens on `:root`, which is right for the front end and for
  a canvas rendered inside a `<BlockCanvas>` iframe. The site editor mounts
  **inline** (#418), so `:root` resolved to the host document's `<html>` and the
  theme's root padding pushed the entire editor shell — top bar, navigator,
  inspector and canvas — in by that amount, showing up as a visible border
  around the whole editor on any host that paints `<body>`.
  `scope-global-styles-css.ts` now rewrites `:root` to `.editor-styles-wrapper`
  before the CSS is injected, including inside `@media` and `@supports` blocks
  and skipping comments, strings and `url()` contents. The rewrite is a strict
  narrowing at identical specificity (both selectors weigh 0,1,0), so the tokens
  land on the canvas surface instead of the document root, every block inside
  still inherits them, and theme root spacing applies where a theme author
  expects it. Fixed here rather than in cms-framework's `GlobalStylesEmitter`,
  whose `:root` output is correct for every other consumer.
- **`artisanpack/marquee` renders its saved content again** (#691) — it was the
  only one of the 105 bundled `block.json` manifests declaring a `source`
  definition while being absent from both registration lists in
  `VisualEditorServiceProvider` (`$forkedBlocks` and `$referenceBlocks`).
  `BlockMarkupHydrator::recoverAttributes()` looks the block up in
  `BlockTypeRegistry`, found nothing, and returned `[]`, so `marqueeContent` was
  never recovered from saved markup and `marquee.blade.php` rendered an empty
  marquee.
- **Container bindings resolve without a leading backslash** (#692) — Laravel's
  container does not normalise FQCN keys, so `'\Foo\Bar'` and `Foo\Bar::class`
  are two distinct entries. `EntitySearchController::searchCmsFrameworkEntities()`
  built the template and template-part resolver class strings *with* a leading
  backslash, so `app( $resolverClass )` missed any host- or test-registered
  binding and silently constructed a fresh resolver instead — a host that
  rebound `TemplateResolver::class` was ignored with no error. `class_exists()`
  behaves identically either way, which is exactly why the guard above it hid
  the problem. The backslash is dropped there and everywhere else in the package
  that built a container key the same way.
- **Post-editor canvas now honours the theme's `theme.json` colors** (#695) —
  the canvas body is painted through two package-owned custom properties
  (`--ap-editor-canvas-bg` / `--ap-editor-canvas-fg`) that nothing ever
  assigned, so both fell back to their light-mode defaults. Because the rule
  that reads them is an element+class compound (`body.editor-styles-wrapper`,
  specificity 0,1,1) it out-specifies a theme's bare `.editor-styles-wrapper`
  rule regardless of source order, so no theme sheet could correct it: a dark
  theme rendered a white canvas with dark body text. The canvas now derives
  both properties from the resolved theme.json's `styles.color` and injects
  them as a `:root` rule. The same defect applied one level down —
  `DEFAULT_CANVAS_STYLES` hardcoded `#111827` for `h1`..`h6` at the same
  specificity, which on a dark ground rendered headings near-invisible — so
  the heading baseline now chains through a matching
  `--ap-editor-canvas-heading-fg`, sourced from `styles.elements.heading` (or
  `h1`..`h6` when every declared level agrees) and falling back to the canvas
  foreground. Themes declaring no colors keep the previous light defaults, and
  a host rule on `body` / `.editor-styles-wrapper` still overrides the theme.
  A theme that declares a background without a paired text color gets a legible
  foreground derived for it via the package's WCAG helpers rather than half a
  pair, and WordPress's `var:preset|color|slug` shorthand is accepted alongside
  the `var(--wp--preset--color--slug)` CSS form. That derivation covers every
  opaque CSS color syntax a `theme.json` may carry — `rgb()`, `hsl()`, named
  colors, and alpha-bearing hex — not just the plain hex the WCAG helpers parse
  natively, so an unpaired dark background written as `rgb(17 24 39)` or `black`
  is no longer emitted with an unreadable foreground left on the default. Values
  with no fixed opaque color (`var()` references, translucent colors) are left
  alone rather than guessed at, and the theme's own syntax is what reaches the
  stylesheet — normalisation is used only to measure contrast. The site-editor
  canvas is unaffected.
- **Blade renderer front-end assets no longer 404 on a fresh install** (#699) —
  every stylesheet `<x-ve-blocks-styles />` links under
  `/vendor/visual-editor-renderer-blade/` returned Laravel's 404 page until the
  consumer discovered and ran
  `vendor:publish --tag=visual-editor-renderer-blade-assets`, leaving the front
  end with no block-gap between paragraphs, no content-size containment on
  constrained groups, and `core/columns` collapsed to a vertical stack.
  `visual-editor-renderer-blade` now registers a route that serves the bundled
  block-library and `frontend/*` assets straight from the package, so the
  styles resolve with no install step and package upgrades take effect
  immediately. Publishing still works and still wins — the web server serves
  the static file before the route is reached — but it is now an optional
  performance choice rather than a silent requirement.
- **Per-block layout classes in the Blade renderer** (#700) — layout-supporting
  partials emitted only the shared `is-layout-{type}` modifier, so the shipped
  block-library rules that key on the per-block compound
  (`.wp-block-group.wp-block-group-is-layout-constrained`,
  `.wp-block-post-template-is-layout-flow > li > .aligncenter`, …) never
  matched. `group`, `row`, `stack`, `columns`, `buttons`, `post-content`, and
  `post-template` now emit both classes through the new
  `Support\LayoutSupport` helper. `post-content` additionally honours its
  stored `layout.type` for the first time — without that class the
  content-size containment rules `ThemeJsonTokensCompiler` emits for
  `.wp-block-post-content.is-layout-constrained` could never apply — and
  `columns` gained the `is-layout-flex` pair it was missing entirely.
- **Constrained groups now constrain their children** (#700) —
  `ThemeJsonTokensCompiler` emitted content-size containment only for
  `.wp-block-post-content`, so a `<article class="wp-block-group
  is-layout-constrained">` let its title, meta, and featured image run
  edge-to-edge while its post body behaved. It now also emits the
  `> :where(:not(.alignwide):not(.alignfull):not(.alignleft):not(.alignright))`
  cap plus the `alignwide` / `alignfull` overrides for constrained groups,
  mirroring the per-instance rules WordPress generates. Keyed on the
  per-block `wp-block-group-is-layout-constrained` compound, so host markup
  that hand-writes the shared `is-layout-constrained` modifier and styles
  those children itself is not retroactively constrained.
- **React and Vue `core/group` honour the Flex Layout panel** (#711) — the
  Blade partial flips a group's layout class to `is-layout-flex` when the #595
  Flex Layout panel emits the unprefixed `ap-flex` class and no explicit
  `layout` is stored, so the flow baseline's
  `is-layout-flow > * + * { margin-block-start: gap }` rule stops pushing flex
  children apart along the cross axis. Both JS renderers called
  `layoutClass()` unconditionally and emitted
  `is-layout-flow wp-block-group-is-layout-flow` for the same tree — exactly
  the bug #595 fixed for Blade, still live on React and Vue hosts. They now
  mirror the Blade rule byte for byte: only the base-breakpoint `ap-flex`
  triggers it (a `md:ap-flex` means "flex from md up" and must not flip the
  base class), and only when the stored layout type is empty. Three parity
  fixtures pin all three cases.
- **`core/post-template` agrees across renderers on layout and column bounds**
  (#704) — `QueryInliner::postTemplateLayoutIsGrid()` accepts three saved
  shapes when it stamps `_resolvedGridSpan` onto each item: a plain `layout`
  string, an upstream mirror's object-form `layout.type`, and Gutenberg's
  sibling `layoutType`. The renderers did not agree with it or with each
  other — Blade read only the plain string, React and Vue read the string and
  `layoutType` — so a tree the inliner treated as a grid could render its
  wrapper as flow, leaving the stamped span classes with no grid to lay out.
  All three renderers now accept all three shapes. Blade additionally clamps
  `columns` to the `[1, 12]` range the stylesheet actually ships `columns-N`
  rules for, and falls back to the default for an unparseable value, matching
  the JS renderers; previously it could emit `columns-0` or `columns-99`.
- **Composed view announces a repeated template failure** (#618) — the
  fallback toast's latch only cleared when leaving composed mode, so broken
  template A → working B → broken A again produced no second toast. A
  successful resolution now clears it. The applied-template cache also holds a
  `Map` rather than a single slot, so it keeps the "cached per
  `(resource, id, template)` triple for the lifetime of the editor mount"
  contract its documentation states — flipping A → B → A previously refetched
  A once B had resolved.
- **Site-editor routing no longer rewrites navigation silently** (#618) — a
  host `routeBase` that fails the same-origin-absolute-path test is replaced
  with the package's own mount path. That substitution is right for the ribbon
  CTA's `<a href>`, where an absolute or `javascript:` URL would be an off-site
  link, but as silent behaviour for the SPA's own routing it made every
  in-editor navigation push URLs the host does not serve, with a 404 on
  reload and nothing in the console to explain it. It now warns once, naming
  the rejected value, and the fallback is documented on the `routeBase` prop.
  Separately, the deep-link "user has already navigated" guard compared the
  pathname only, so navigating away and back inside the SPA while a slug
  lookup was in flight defeated it and the late resolution teleported the
  author; it now compares the query string too, which every in-SPA navigation
  clears and none restores.

## [1.5.5] - 2026-08-02

### Added

- **`BlockMarkupHydrator`** (`Support`) — the supported server-side path
  from a raw WP block-markup string (a block theme's `templates/*.html`
  or `parts/*.html`, a `.php` pattern, a persisted `post_content`) to the
  editor-shape tree the renderers consume. `hydrate( string $markup )`
  parses the delimiters via cms-framework's `BlockMarkupParser` — resolved
  by name, so cms-framework stays a non-dependency and the method degrades
  to an empty tree when it is absent — while `hydrateTree( array $parsed )`
  takes an already-parsed tree and keeps working either way.
  `canParseMarkup()` lets hosts that would rather fail loudly gate on
  parser availability. Carries a `MAX_DEPTH` recursion cap (128, above the
  parser's own 64) so a hand-built or imported payload cannot blow the PHP
  stack, and falls back across the `core/` ↔ `artisanpack/` namespace pair
  so theme files written against WP core's block names resolve against the
  forks this package registers.
- **`BlockAttributeSourceResolver`** (`Support`) — server-side port of
  Gutenberg's save-shape attribute matchers over `DOMDocument` + XPath,
  covering `attribute`/`property`, `html` (including `multiline`),
  `rich-text`, `text`, `tag`, `query`, and `raw`. Recovery is driven
  entirely by the block-type registry's `block.json` definitions rather
  than a hand-maintained per-block table, so a block that ships a new
  sourced attribute is picked up with no change here.
- **`CssSelectorToXPath`** (`Support`) — hand-rolled translator for the
  narrow CSS subset `block.json` `selector` fields actually use (tag,
  class, id, attribute presence/equality, `:not()` over a single simple
  predicate, descendant and child combinators, comma groups), so the
  package gains no new Composer dependency for this. Selectors outside the
  subset throw `UnsupportedSelectorException`, which callers degrade to
  "attribute not recovered" and report once per process rather than
  silently matching the wrong node.
- **`BlockRenderer::renderMarkup( string $markup, ?string $defaultTheme )`**
  (`visual-editor-renderer-blade`) — markup in, HTML out. Hydrates, then
  inlines `template-part` and synced-pattern references before the walk,
  so a standalone theme template renders its parts as real content rather
  than empty wrappers. Blocks that need an entity in scope (`post-*`,
  `core/query`, comments, breadcrumbs) still require the full
  `<x-ve-blocks :tree="…" :post="…" />` pipeline.

### Fixed

- **Block-theme markup no longer renders textless server-side (#688).**
  There was no path from WP-serialized block markup to rendered HTML
  outside the editor, and the gap was not merely a shape mismatch between
  cms-framework's `{blockName, attrs, innerHTML}` and the editor's
  `{name, attributes}`. Gutenberg persists most block text in the **saved
  HTML**, not in the delimiter JSON — zero of the `artisanpack-ui` theme's
  130 paragraph/heading blocks serialize a `content` attribute — so a
  key-rename conversion produced a structurally-correct but completely
  textless page. Hydration now replays each registered block type's
  attribute definitions back over the saved HTML, recovering paragraph and
  heading `content`, button text and href, image `src`/`alt`/`caption`,
  list values, quote and citation, table cells, and everything else
  declared with a `source`.
- **`.html`-sourced template parts now inline with content (#688).**
  `TemplatePartInliner` resolved a block theme's `parts/header.html` to an
  entity with `blocks` empty and the raw markup in `rawContent` —
  cms-framework's resolver does not parse theme files — so the part
  inlined as an empty wrapper. The inliner now hydrates that raw markup
  when `blocks` comes back empty, accepting either `rawContent`
  (visual-editor's `ResolvedTemplatePart`) or `raw` (cms-framework's
  `ResolvedEntity`) so it works whichever value object a host's resolver
  hands back. A theme's own parts render without first being opened and
  re-saved in the site editor.
- **`TemplatePartInliner` now honors container overrides of the
  cms-framework resolver.** `RESOLVER_CLASS` carried a leading backslash,
  which does not match the key Laravel's container stores
  `TemplatePartResolver::class` under; `app()` treated it as a distinct
  binding and silently built a fresh instance, ignoring any host- or
  test-supplied override.

### Security

- `BlockMarkupHydrator` documents an explicit trust boundary. Recovered
  `rich-text` / `html` attributes are HTML fragments and block partials
  emit them unescaped (`{!! $content !!}`) — that is what makes a
  paragraph's `<strong>` survive the round trip. Hydration is therefore
  only safe over markup you already trust to render: theme files on disk,
  patterns, and editor-authored content that passed the post editor's
  authorization. It is **not** a sanitizer, and passing visitor-submitted
  markup to `hydrate()` turns it into stored XSS. This is the same
  boundary Gutenberg draws around block markup — neither widened nor
  narrowed. Hosts rendering untrusted markup should run it through their
  own sanitizer (e.g. `kses()` from `artisanpack-ui/security`) first.

## [1.5.4] - 2026-07-28

### Fixed

- **Site editor per-size and per-state style overrides now scope
  correctly (#700).** The site-editor bootstrap in
  `site-editor/site-editor-app.tsx` never called the five `register*()`
  functions that wire the responsive + state pipeline —
  `registerResponsiveAttribute`, `registerResponsiveAttributesFilter`,
  `registerStateAttribute`, `registerStateAttributesFilter`, and
  `registerStateStylesFilters` — so opted-in blocks never got the
  auto-injected `responsive` / `states` storage keys, the
  `editor.BlockEdit` HOCs never wrapped `setAttributes` to route writes
  into `responsive.<bp>.<path>` or `states.<path>.<activeState>`, and
  the per-state `<style>` scope class was never injected into the
  canvas. Every panel write landed on the base attribute, so a change
  on Hover state or Desktop viewport applied to every state / screen
  size. All five functions are now registered before
  `registerArtisanPackBlocks()`, mirroring
  `editor/editor-app.tsx:211-219`. The shared `BlockEditorProvider` in
  `site-editor/block-editor-boundary.tsx` also mounts
  `<StateWriteInterceptor />` and `<StateInspectorSync />` (missed when
  the provider was hoisted in #436) so apiVersion-3 color / border /
  shadow panels, which dispatch `updateBlockAttributes` directly and
  bypass the HOC chain, still route through the state bag.
- **Button block now opts into responsive spacing / dimensions
  (#700).** The singular `artisanpack/button` block declared
  `artisanpackStates` but not `artisanpackResponsive`, so
  per-breakpoint padding / margin writes had no `responsive` storage
  key to persist into and applied globally. Added
  `supports.artisanpackResponsive.attributes: ["spacing", "dimensions"]`
  in `resources/js/visual-editor/blocks/button/block.json`, matching the
  convention on the `buttons`, `columns`, `group`, and `column` blocks.

## [1.5.3] - 2026-07-27

### Fixed

- **Release workflow no longer races Packagist's version-immutability
  policy (#683).** The workflow shipped in #678 triggered on
  `push: tags: 'v*'` and used a `build-dist` job to force-update the
  tag mid-run so the composer tarball would carry `dist/editor/` +
  `dist/lib/`. Packagist's crawler observes new stable tags within
  seconds and freezes their SHA permanently — the workflow lost that
  race on v1.5.2, so Packagist ended up pinned at the source-only
  commit while GitHub's tag advanced to the dist-baked one. Composer
  consumers of v1.5.2 still got the source-only tarball, same broken
  state as v1.5.1. The workflow now triggers on `workflow_dispatch`
  with a `version` input. The maintainer merges the release PR into
  main, then invokes `gh workflow run release.yml -f version=X.Y.Z`.
  The workflow guards against re-shipping an existing tag, verifies
  the version input matches `composer.json` / `package.json`, builds
  `dist/`, commits it onto a fresh commit, creates the tag on that
  commit with `git tag -a` (annotated, not `-f`), and pushes it
  exactly ONCE with `git push origin refs/tags/vX.Y.Z` (no
  `--force`). Packagist observes the tag exactly once, at the
  correct SHA. `tests/Feature/Ci/ReleaseWorkflowTest.php` was
  rewritten to lock the new invariants and explicitly banned
  `git tag -f` / `git push --force` on version tags so the race
  can't recur silently.

### Notes

- **v1.5.3 is the first release that actually delivers Pattern B
  from `docs/Installation-Guide.md` end-to-end.** v1.5.2 attempted
  the same dist-baking contract described in #678 but Packagist
  froze it at the source-only tarball (see #683 above). Consumers
  who pinned to v1.5.2 for the pre-built `dist/editor/` tree should
  upgrade to v1.5.3 — pin `^1.5.3` if you need Pattern B. The
  `v1.5.2` GitHub tag still points at the dist-baked commit
  (`49e6728`) for anyone doing `git clone && git checkout v1.5.2`,
  but Composer consumers get `442ef80` (source-only) from Packagist
  regardless; this cosmetic tag/Packagist mismatch was left as-is
  because GitHub's tag-protection rule blocks a CLI restore and the
  practical impact is zero for Composer installs.

## [1.5.2] - 2026-07-27

> **This release did not fully ship its promised dist-baking on
> Packagist.** #678's release-workflow force-push raced Packagist's
> version-immutability policy and lost — Packagist froze `v1.5.2`
> at the source-only commit (`442ef80`) while GitHub's tag advanced
> to the dist-baked commit (`49e6728`). Composer consumers who
> pinned to `^1.5.2` for Pattern B still get the source-only
> tarball. Upgrade to v1.5.3 (fixed in #683) for the actual
> pre-built bundles.

### Fixed

- **Site editor now exposes the media-bridge registration surface
  (#677).** The site-editor bundle shipped without installing the
  `editor.MediaUpload` slot-fill filter and without re-exporting
  `registerMediaBridge` / `registerArtisanpackMediaBridge` from
  `site-editor/main.tsx`, so the `core/image` block placeholder in
  template parts hid the **Media Library** button and clicking
  **Upload** silently no-op'd — the fallback uploader surfaced an
  "Uploads are unavailable until a media bridge is registered"
  snackbar because no host could actually register one on the
  site-editor entry. `site-editor/main.tsx` now mirrors the
  post-editor registration surface: the bridge exports ride through
  the ESM entry, `window.ApSiteEditor` + `window.ApSiteEditorBoot`
  are installed to match `window.ApVisualEditor`, and
  `ensureMediaBridgeFilter()` is called at module load so the slot
  fill is in place even when the host registers the bridge
  asynchronously.
- **Composer tarball now ships pre-built editor bundles under
  `dist/editor/` and `dist/lib/` (#678).** Previous releases treated
  `dist/` as a purely local artefact — `.gitignore` excluded it, the
  release workflow built it in an ephemeral runner and threw it
  away, and hosts installing via Composer's GitHub-tarball resolution
  ended up with no `vendor/artisanpack-ui/visual-editor/dist/editor/`
  directory at all. Downstream consequence: Keystone CMS's
  `VisualEditorAssetController` (which serves the bundles straight
  from the vendor tree so hosts don't need Node in their asset
  pipeline) 500'd on every request. The release workflow now runs a
  `build-and-tag` job that rebuilds both bundles, verifies the
  required outputs exist, force-adds `dist/editor/` and `dist/lib/`
  onto the release commit, and re-points the version tag at that
  commit so Composer resolves the built tarball. Sourcemaps are
  stripped from the tarball (would ~triple its size) and attached to
  the GitHub Release as a separate `dist-sourcemaps-vX.Y.Z.tar.gz`
  archive.

### Changed

- **`docs/Installation-Guide.md` now documents both consumption
  patterns.** Pattern A (host runs Vite, existing) and Pattern B
  (host serves pre-built bundles from `vendor/`, new) are called
  out explicitly so CMS integrators know which they can rely on.
- **Tarball size increases by ~18 MB** (uncompressed) to carry the
  pre-built editor and library bundles. Well within Packagist norms
  but worth calling out for consumers with strict install-size
  budgets.

## [1.5.1] - 2026-07-26

### Fixed

- **`<!-- wp:template-part /-->` references inside theme templates now
  render inline in the site-editor canvas (#674).** Three-layered
  failure: `TemplatePartController::index()` ignored the `?theme=&slug=`
  filter the core-data shim sends for composite-id fallback, so the
  shim silently picked an arbitrary sibling (or the missing-record
  placeholder). `TemplateAdapter` shipped `content.blocks: []` for
  theme-file sources because cms-framework's filter contributor only
  populates `raw_content`, and the shim's raw-fallback parser is
  scoped to nav-link / nav-submenu blocks. Since the I7 cutover
  (#415) removed `registerCoreBlocks()`, `core/template-part` blocks
  land unregistered and get dropped; the `artisanpack/template-part`
  fork's edit delegated to `core/template-part` via
  `createForkedEntityEdit`, which fell back to an empty `<div>`
  because the core lookup returned undefined. Fixed at all three
  layers — controller now filters by `theme` + `slug`; adapter parses
  `raw` server-side via cms-framework 2.5+'s `BlockMarkupParser`
  (guarded with `class_exists` so older versions degrade gracefully)
  and rewrites `core/template-part` to the fork in both `raw` and
  `blocks`; the fork ships a real edit that composes the composite id
  from `slug` + `theme`, hands off to the shim's
  `useEntityBlockEditor`, and mounts the resolved tree through
  `useInnerBlocksProps` with `templateLock: 'all'`. The composite
  call is gated behind a subcomponent so a legacy reference missing
  `theme` can't leak through the shim's ambient-id fallback and mount
  the parent template recursively.
- **Site-editor canvas no longer paints a `padding: 24px` inset + a
  hardcoded `background: #fff` over the theme's own background
  (#674).** Templates and template-parts span the full viewport on
  the front-end, so the inset made full-bleed sections cap short of
  the shell edges and dark themes read as a pale-rimmed rectangle.
  Scoped to `.ap-site-editor__entity-canvas-surface`; the pattern
  editor uses `.ap-pattern-canvas__surface` and is unaffected.

## [1.5.0] - 2026-07-21

### Added

- **Six new lifecycle hooks for editor and block integrations (#665).**
  Fills in high-value extension points across block registration,
  rendering, post persistence, editor config assembly, and pattern
  rendering. All hooks live under the canonical `ap.visualEditor.*`
  namespace introduced in #664:

    - `ap.visualEditor.blockRegistered` — action, fires at the end of
      block registration with `(string $name, array $config)`. Fires
      through `BlockTypeRegistry::register()` so both `block.json`
      registration and programmatic `registerBlockType()` calls emit.
    - `ap.visualEditor.beforeRender` — filter on `(array $attributes,
      string $name)` that runs inside `BlockRenderer::renderBlock()`
      after site-meta / loginout stamping. Non-array returns are
      discarded so a misbehaving callback can't blank the block.
    - `ap.visualEditor.postSaved` — action fired from
      `WpEntityController` after every POST/PUT persistence with
      `(int|string $postId, array $blocks)`.
    - `ap.visualEditor.postPublished` — action fired from the same
      site whenever the current save transitions status into the
      WP-canonical `publish` value (create-with-publish or
      non-publish → publish on update). Same payload as `postSaved`.
    - `ap.visualEditor.editorConfig` — filter applied by
      `VisualEditorComponent` on the assembled config array with
      `(array $config, string $screen)`; `$screen` is `'post'` for
      the current component. Filtered values are re-hydrated onto
      the component's typed props, so misbehaving returns can't
      poison a `?string` prop.
    - `ap.visualEditor.patternRender` — filter applied by
      `PatternAdapter::toArray()` on the rendered raw content of a
      pattern with `(string $html, string $slug, array $context)`.
      Context carries source, synced flag, categories, block_types,
      and post_types so callbacks can gate per pattern shape.

### Changed

- **Bumped `artisanpack-ui/hooks` requirement to `^1.3` (#665).** The
  hook fire sites added in #665 assume the helper globals are always
  available, so the previous `^1.2` constraint's `function_exists()`
  guards have been dropped in favour of the newer floor.

### Changed (BREAKING)

- **Renamed every visual-editor hook to camelCase (#664).** The 13 PHP
  hooks the package fires or subscribes to, plus the
  `visual_editor.pre_publish_checks` cross-package hook, the
  `ap.icons.register-icon-sets` bridge hook, and three JS-only hooks
  (`background-controls`, `canvas-styles`, `document-panels`), now use
  camelCase — matching the ArtisanPack UI ecosystem's naming convention.
  Old names remain functional via `deprecateHook()` aliases registered by
  `ArtisanPackUI\VisualEditor\Support\HookAliases` on the PHP side and a
  bidirectional `@wordpress/hooks` shim in
  `resources/js/visual-editor/support/hook-aliases.ts` on the JS side;
  the JS shim runs at `Number.MIN_SAFE_INTEGER` priority so real
  subscribers on the paired name are surfaced before priority-sorted
  dispatch of the applied name's callbacks. A deprecation notice is
  logged the first time an alias resolves per process. Rename table:

    - `ap.visual-editor.resources` → `ap.visualEditor.resources`
    - `ap.visual-editor.templates` → `ap.visualEditor.templates`
    - `ap.visual-editor.template-parts` → `ap.visualEditor.templateParts`
    - `ap.visual-editor.patterns` → `ap.visualEditor.patterns`
    - `ap.visual-editor.global-styles` → `ap.visualEditor.globalStyles`
    - `ap.visual-editor.navigation` → `ap.visualEditor.navigation`
    - `ap.visual-editor.visibility.register-rules` → `ap.visualEditor.visibility.registerRules`
    - `ap.visual-editor.visibility.evaluated` → `ap.visualEditor.visibility.evaluated`
    - `ap.visual-editor.visibility.user-search-results` → `ap.visualEditor.visibility.userSearchResults`
    - `ap.visual-editor.rendered-block` → `ap.visualEditor.renderedBlock`
    - `ap.visual-editor.breadcrumbs.trail` → `ap.visualEditor.breadcrumbs.trail`
    - `ap.visual-editor.loginout.envelope` → `ap.visualEditor.loginout.envelope`
    - `ap.visual-editor.loginout.login-form` → `ap.visualEditor.loginout.loginForm`
    - `visual_editor.pre_publish_checks` → `ap.visualEditor.prePublishChecks`
    - `ap.icons.register-icon-sets` → `ap.icons.registerIconSets`
    - `ap.visual-editor.background-controls` → `ap.visualEditor.backgroundControls`
    - `ap.visual-editor.canvas-styles` → `ap.visualEditor.canvasStyles`
    - `ap.visual-editor.document-panels` → `ap.visualEditor.documentPanels`

  See `src/Support/HookAliases.php` for the full canonical list. Bumps
  the `artisanpack-ui/hooks` Composer requirement to `^1.2` (v1.3.x in
  practice) to pick up the `deprecateHook()` helper (v1.3.0).

- **Site-editor map resolvers key by the entry's own identifier, not the
  raw filter key.** Previously, `find( $rawKey )` succeeded when a
  contributor supplied a filter map whose keys diverged from the entries'
  own `slug` / `location`. That path is gone: `find()` now only resolves
  by the value object's identifier. Contributors that already stamp the
  matching `slug` / `location` on their entries (all first-party
  contributors, including cms-framework) are unaffected.
- **Canvas CSS endpoint delegates to cms-framework's `ThemeStylesheetReader`.**
  `GET /visual-editor/api/global-styles/css` now delegates the theme-file
  read to `ArtisanPackUI\CMSFramework\Modules\SiteEditor\Support\ThemeStylesheetReader`
  when it's available (cms-framework ≥ 2.5). The endpoint now concatenates
  emitter output + `themes/{slug}/style.css` + `themes/{slug}/editor.css`
  (the last is new — closes the canvas half of cms-framework #199, giving
  the site editor a WordPress `add_editor_style()` analog). Section banners
  switch from `/* === theme stylesheet === */` to per-file banners
  (`/* === style.css === */`, `/* === editor.css === */`) so devtools
  inspection matches cms-framework's endpoint. Falls back to the inline
  `readThemeStylesheet()` helper for cms-framework < 2.5 — the fallback
  preserves the pre-change behavior (style.css only, historical banner
  text) so themes on older cms-framework releases keep the canvas parity
  the endpoint already delivered.

### Fixed

- **Site-editor resolvers accept numeric-string map keys (cms-framework #203).**
  WordPress template hierarchy filenames like `404.html` / `500.html` double
  as slugs. PHP auto-coerces numeric-string array keys to `int` at insertion
  time and `array_merge` renumbers int-keyed entries sequentially, so
  contributors of `ap.visualEditor.{templates,templateParts,patterns,navigation}`
  cannot preserve the intended string key from upstream. `AbstractMapResolver`
  now accepts `int` keys and re-keys the normalized output map by each value
  object's own identifier via a new `identifierOf()` hook (`slug` for
  templates/parts/patterns, `location` for menus). Empty identifiers and
  identifier collisions across entries now throw
  `SiteEditorRegistrationException` at first read instead of silently
  producing an unaddressable / clobbered entry.

## [1.4.0] - 2026-07-16

### Added

- **Dynamic content editor UX (#650).** cms-framework Dynamic Content
  integration inside the visual editor: new `dynamic_content`
  block-binding source, batched `POST /dynamic-content/resolve` +
  `GET /dynamic-content/sources` REST endpoints (gated by the
  `SiteEditorAccessGate` and throttled at `60,1`), `{{`
  autocomplete + token chip decoration + a Token Inserter modal, a
  Dynamic Content tab on the link picker, and an Image block DC
  binding UI. Ships two new blocks: `artisanpack/snippet` (reusable
  block with cycle-guarded CRUD + admin surface at
  `/visual-editor/snippets`) and `artisanpack/dynamic-loop` (iterates
  a collection source and re-renders inner blocks per record). SSR
  wires binding resolution into the Blade renderer via an optional
  `BindingResolver` dependency, and a new `WantsInnerBlocks` marker
  lets dynamic blocks that need the inner tree at render time (loop)
  receive it. cms-framework is a soft dependency — everything
  degrades cleanly when it's not installed. New `docs/dynamic-content.md`
  covers tokens, block bindings, snippets, the loop block, and the
  render pipeline; `docs/block-bindings.md` reconciled to cover the
  bindings sidecar, `fallback` policy, and corrected endpoint paths.
- **Block Visibility (#491, #492, #493).** Per-block runtime
  visibility rules that evaluate **server-side** so hidden blocks
  never emit markup. A single **Visibility** inspector panel exposes
  three rule families: **contextual** (master Hide, screen size,
  query string, referrer, browser/OS/device), **user & auth**
  (login state, user role, specific user via
  `/visual-editor/users/search` with `SiteEditorAccessGate`
  authorization + LIKE escaping), and **scheduling** (single
  date/time window, recurring weekly schedule, per-rule timezone
  override, overnight window support). The editor canvas dims
  hidden blocks so authors can still see and select them while
  they're toggled off; the Blade, React, and Vue renderers each
  filter the tree through `filterVisibleBlocks()` +
  `stampVisibilityScopes()` and emit `<style data-ve-visibility>`
  media queries per hidden breakpoint. Configurable via
  `config('artisanpack.visual-editor.visibility')` — set
  `enabled => false` to bypass every rule site-wide during incident
  response. New `docs/visibility.md`.
- **Responsive preview device sizes (#617).** Extended the
  ViewportSwitcher into a unified device-preview + edit-scope
  control. Selecting a preset atomically resizes the canvas iframe
  to the breakpoint's `previewWidthPx` and scopes subsequent style
  edits to that key. `Breakpoint` gains optional `label` and
  `previewWidthPx` fields (both TS + PHP registries); PHP config
  accepts either the legacy scalar `'sm' => '640px'` shape or the
  new object shape
  `'sm' => ['minWidthPx' => 640, 'previewWidthPx' => 375, 'label' => 'iPhone']`.
  Ship defaults: Mobile (sm/375px), Tablet (md/768px), Desktop
  (lg/1440px), xl+ (1280px), 2xl+ (1536px). Both editor shells now
  stamp `data-breakpoints` with the merged registry snapshot so the
  React shell can hydrate labels and preview widths without a
  round-trip. `docs/blocks/Responsive-Design-Tools.md` updated with
  the object-form config and shipped defaults table.
- **Page pattern inserter modal (#639).** WordPress-style "Choose a
  pattern" modal that auto-opens when the editor loads a
  never-saved record with no content, and can be re-opened at any
  time from a new top-bar button next to the `+` inserter.
  Patterns are grouped by category in a responsive grid, and — for
  the `pages` resource — an optional template selector renders
  site-editor templates (`/visual-editor/api/templates`) alongside
  the pattern grid; picking one writes through the existing
  `template` field on the page. Auto-open is gated by a
  self-contained "never saved" heuristic (empty content plus
  `created_at === updated_at`), so any save — even a blank canvas
  — permanently suppresses the auto-open. Zero patterns for the
  current post-type context suppresses both auto-open and the
  toolbar button; a Blank starter (`page/blank`) ships as the
  seed so the modal always has at least one entry. The modal
  fetches with `?source=theme` so user-created snippet patterns
  (saved via "Convert to pattern" in the sidebar inserter) don't
  leak into the whole-page picker — those still surface in the
  block inserter panel where they belong. Client-side the modal
  further tightens the fetch to patterns whose `post_types` array
  explicitly includes the current context (with a carve-out that
  always keeps the built-in `page/blank` seed) — the whole-page
  picker is meant for starter layouts a developer or theme
  deliberately declared, not the general pattern library.
- **Pattern registration `post_types` scope (#639).** Every
  contributor entry to the `ap.visual-editor.patterns` filter may
  now carry an optional `post_types: string[]` array (Gutenberg
  convention). Patterns without a scope stay available in every
  post-type context; patterns with a scope surface only when the
  requested slug matches. The scope reaches the client via the
  new `post_types` field on `PatternAdapter`'s WP-shape output,
  and the `PatternController` index accepts a new
  `?post_type={slug}` query param that applies the scope filter
  server-side.
- **CSS positioning support for blocks (#640).** Per-block Position
  panel in the inspector with a `static / relative / absolute / fixed
  / sticky` dropdown, per-side offset inputs (`px / % / rem / em /
  vh / vw / auto`), z-index, and per-breakpoint overrides via the
  responsive tab pattern from #487. Opt-in per block via
  `supports.position: true` in `block.json` (Gutenberg's native
  `{ sticky: true }` object shape also counts). Emits scoped CSS
  in the editor canvas and inline styles + a `<style
  data-ve-position>` block on the frontend via the Blade renderer.
  Inspector shows a warning notice when `position: absolute` is
  applied but no ancestor is positioned. Per-breakpoint authoring
  follows the top-bar viewport switcher (via the shared
  active-breakpoint store) — no separate control lives in the panel,
  matching every sibling responsive-aware inspector panel.
- **Enabled `supports.position: true` on 82 top-level artisanpack
  blocks (#645).** Every `resources/js/visual-editor/blocks/*/block.json`
  that doesn't declare a `parent` or `ancestor` restriction now
  opts in. Child-only blocks (accordion internals, list items,
  columns, buttons children, query pagination, etc.) are
  deliberately skipped — positioning a child that a container
  relies on for layout breaks the container's contract.
  `artisanpack/group` already carried Gutenberg's
  `supports.position: { sticky: true }` object shape, which
  `positionEnabled()` also recognizes; no change needed.
- **Position support on core containers (#646).** Functionally
  subsumed by #645 given the I7 (#415) cutover — this repo no
  longer calls `registerCoreBlocks()`, so no `core/*` block enters
  the editor. Every container listed in the parent issue
  (`core/group`, `core/columns`, `core/column`, `core/cover`,
  `core/row`, `core/stack`, `core/grid`, `core/buttons`,
  `core/image`) has an `artisanpack/*` fork covered by #645.
- **Integration + e2e test coverage (#647).** Round-trip
  integration tests exercising resolver + emitter for a full
  base + per-breakpoint payload, legacy sticky no-churn, and the
  static-toggle-preserves-orphan-fields flow. Playwright e2e
  spec (`tests/E2E/positioning.spec.ts`) documents the runtime
  contract for sticky groups, absolute covers, ancestor
  warnings, per-breakpoint viewport switching, and legacy
  sticky — commented pending the Playwright runner (matches
  the `animations.spec.ts` precedent).
- **Docs (#648).** New `docs/position.md` covering opt-in,
  attribute shape, position values, offset units, per-breakpoint
  inheritance, the ancestor warning, the two frontend emission
  channels (inline + `<style data-ve-position>`), and a
  troubleshooting section. Linked from `docs/home.md`.
- **`ap.visual-editor.background-controls` filter (#649).** New
  JS filter that lets third-party packages contribute panels to
  the background/appearance area of any block that opts into a
  background support — no per-package `editor.BlockEdit` HOC
  required. The visual editor now owns the target-block decision
  via a single HOC that gates on `supports.background` and
  `supports.color.background`, then renders filter-registered
  controls sorted by priority (default 10, lower first) and
  deduped by id (last-wins, mirroring `@wordpress/hooks`). The
  HOC runs innermost so `context.attributes` is the
  breakpoint-merged / state-resolved view and
  `context.setAttributes` routes through the responsive/state
  wrappers. Wired into both the post-editor and site-editor
  bootstraps.
- **`ap.visual-editor.rendered-block` filter (PHP).** Fires in
  `BlockRenderer::renderBlock` after each block (static or
  dynamic) finishes rendering. Callbacks receive the HTML, the
  block name, and the normalized attributes, and must return an
  HTML string. Lets cross-cutting effects (frosted glass,
  contrast overlays, motion wrappers) post-process a block's
  markup without every host having to modify the renderer.
- **`ap.visual-editor.canvas-styles` filter (JS).** Filters the
  ordered `CanvasStyle[]` handed to `BlockCanvas`'s
  `__unstableEditorStyles` prop. Callbacks return a
  `CanvasStyle[]`; non-array returns fall through to the base
  list and non-object entries are dropped. Lets packages push
  their stylesheets into the Gutenberg iframe (which is
  sandboxed from the parent document's Vite-injected styles)
  without touching `canvas-styles.ts` for every new integration.

### Upgrade notes

- **Hosts that ran `php artisan vendor:publish
  --tag=visual-editor-blade-views` on a prior version must
  re-publish with `--force` when upgrading.** The published
  `blocks.blade.php` and `template.blade.php` files shadow the
  package source; the pre-1.4 published copies don't include the
  new `<style data-ve-position>` output block, so the position CSS
  accumulator will flush its rules but they'll never land in the
  response body — sticky/absolute blocks render unpositioned on the
  frontend. Command:
  `php artisan vendor:publish --tag=visual-editor-blade-views --force`.


## [1.3.0] - 2026-07-07

### Added

- **AI-powered authoring affordances (#610–#614).** Five optional
  AI-assisted authoring features built on top of
  [`artisanpack-ui/ai`](https://github.com/ArtisanPack-UI/ai). All
  default to *off* and honor per-feature toggles from the AI package's
  `FeatureRegistry`; each surfaces suggestions the author must
  explicitly accept — no automatic mutations. Auto-registered via
  `VisualEditorServiceProvider::aiFeatures()`, so hosts with the AI
  package installed pick up every affordance with zero manual wiring.

  Three agents live in this package under `src/Ai/Agents/`:

  - **`ContentBlockSuggestionAgent` (#610)** — `visual_editor.suggest_next_block`.
    Inline "+ suggest next block" affordance that ranks likely next
    blocks given the document so far. Ships as
    `<SuggestNextBlockButton />`.
  - **`LayoutSuggestionAgent` (#611)** — `visual_editor.suggest_layout`.
    Given a section's content and your available pattern library,
    ranks matching section patterns from a whitelist. Ships as
    `<SuggestLayoutPanel />`.
  - **`HeadingHierarchyAgent` (#614)** — `visual_editor.heading_hierarchy`.
    Audits the document for skipped heading levels, duplicate h1s,
    and ambiguous headings; returns suggested fixes with nested
    `innerBlocks` traversal. Ships as `<HeadingHierarchyPanel />`.

  Two agents are consumed directly from `artisanpack-ui/ai` so the
  same prompt + feature toggle powers them across every package that
  opts in:

  - **`AltTextGenerationAgent` (#612)** — `ai.alt_text`. Suggests
    accessibility-friendly alt text when an image block is added or
    its `src` changes and `alt` is empty. Ships as
    `<AltTextSuggestionCard />`.
  - **`ContentRewriteAgent` (#613)** — `ai.content_rewrite`.
    Selection-toolbar / slash-command surface for "make shorter",
    "more formal", "reading level 6", and similar rewrites. Ships as
    `<RewriteToolbar />`.

  **Transports.** Every affordance is reachable from any host stack:

  - **HTTP** — six JSON endpoints under `/visual-editor/api/ai/*`:
    `GET /features`, `POST /suggest-next-block`,
    `POST /suggest-layout`, `POST /alt-text`, `POST /rewrite`,
    `POST /heading-hierarchy`. Each endpoint has a dedicated Form
    Request class in `src/Http/Requests/Ai/` and a consistent error
    envelope. All routes are guarded on
    `class_exists(FeatureRegistry)` so hosts without the AI package
    installed don't 500.
  - **Livewire** — `artisanpack-visual-editor.ai.tools` component
    listens for `ap-ve-ai:*` browser events and dispatches shaped
    `success` / `invalid-input` / `disabled` /
    `missing-credentials` / `error` events back. Blade/Livewire
    hosts get the same behavior as the React surface without pulling
    in a client bundle.
  - **React** — `resources/js/visual-editor/ai/` ships
    `createAiApiClient`, `useAiFeatures` gate, per-feature hooks,
    and 5 UI components. See
    [`docs/ai-features.md`](docs/ai-features.md) for the full
    authoring guide.

  **Requirements:** `artisanpack-ui/ai` `^1.0` installed and
  configured, per-feature toggle enabled in the AI settings surface,
  and CSRF middleware active on `/visual-editor/api/*` so the shipped
  JS client's `X-CSRF-TOKEN` header is honored.

### Changed

- `composer.json` now requires PHP 8.2+ and `artisanpack-ui/ai` `^1.0`
  as an optional-but-recommended companion dependency. Hosts without
  the AI package installed continue to work — the AI surface simply
  stays hidden and the routes short-circuit before dispatching an
  agent.

### CI

- Test suite runs on PHP 8.2 with AI dev deps excluded so hosts still
  targeting 8.2 have a green matrix even before adopting the AI
  package.

## [1.2.0] - 2026-06-18

### Added

- **Box / drop shadow control with solid + gradient color (#607).**
  New Shadow tools panel in the inspector's Styles group, auto-enabled
  on every block with `__experimentalBorder` support (~94 blocks, no
  block.json changes required). Exposes X/Y offset, blur, spread,
  solid color, gradient color (with theme palette), inset toggle, and
  a preset chip row backed by the new `settings.shadow.presets` slot
  in `theme.json`. Writes route through the standard `artisanpackStates`
  / `artisanpackResponsive` HOCs so per-state and per-breakpoint
  shadow overrides land in the right cascade bag automatically. Three
  emission modes (preset / solid / gradient) share one scoped `<style>`
  code path; gradient and inset-gradient shadows render through a
  `::before` / `::after` pseudo-element with `filter: blur()` and a
  `mask-composite: exclude` ring mask for the inset variant. PHP
  `BoxShadowResolver` + `BoxShadowEmitter` mirror the TS pair
  byte-for-byte so editor canvas, saved markup, and Blade-rendered
  output stay in lockstep. New scope class `ve-bs-<id>` persisted on
  `attributes.style.shadow._shadowScopeId`. Front-end Blade rendering
  goes through a new `BoxShadowCssAccumulator` +
  `BlockSupports::pushBoxShadow()` + auto-stamping in
  `BlockSupports::compile()`, so every block already routed through
  the supports compiler picks up shadow rendering with zero
  per-template changes. The supports-extension filter also strips the
  native WordPress `supports.shadow` on opted-in blocks to keep the
  two systems from fighting over the `style.shadow` attribute slot.
  Mirrors the architecture established by gradient borders (#490).
  **Known limitation:** outer gradient shadows on blocks with
  `overflow: hidden` (e.g. Cover) are visually clipped at the wrapper
  edge — gradient shadows need a `::before` pseudo-element because
  the native `box-shadow` property doesn't accept gradient fills, and
  pseudo-elements (unlike box-shadow) are clipped by their host's
  overflow. Solid shadows and preset shadows are unaffected. See
  [`docs/box-shadows.md`](docs/box-shadows.md) for the full authoring
  guide and workarounds.

- **Per-post layout overrides on the Query Loop via post-variants
  (#591).** New `artisanpack/post-variant` block, child of
  `artisanpack/post-template`, declares an override template that
  swaps in for posts matching its `matcher` attribute. Four matcher
  kinds ship in V1: `position` (`first` / `last` / `nth:<n>` /
  `range:<from>-<to>`), `pattern` (`odd` / `even` /
  `every-nth:<step>[:start:<offset>]`), `meta` (`sticky`, `featured`,
  `has-featured-image`, `author:<id>`, `taxonomy:<tax>:<slug>`), and
  `custom` (`callback:<name>` → `apve_query_variant_match_<name>`
  filter hook). A new "Post Variants" panel in the query inspector
  lists, adds, reorders, and deletes variants. Static rules
  (position / pattern) precompile to a 0-based `position →
  variantOrder` map stored on the parent post-template as
  `_compiledVariantMap` for O(1) lookup; dynamic rules (`meta`,
  `custom`) resolve at render time via the new
  `ArtisanPackUI\VisualEditor\Resources\VariantResolver`. Precedence
  is fixed: instance > position > pattern > meta > custom > base,
  with `priority` ascending as the tie-breaker. All three renderers
  (Blade, React, Vue) consume the same inlined tree — variants are
  stripped server-side by `QueryInliner`, so existing query loops
  with no variants render identically to before. Items rendered via
  a variant carry an extra `is-variant` class on their
  `core/post-template-item` wrapper for downstream styling.
- **Native flex layout panel on group / column / columns / grid-item
  (#595).** New `Flex Layout` + `Flex Item` inspector panels expose
  every CSS flexbox property — direction, wrap, justify, align-items,
  align-content, place-content, row/column gap, plus per-item
  align-self, grow, shrink, basis, order — each per-breakpoint via the
  existing `<ViewportSwitcher />`. Replaces WordPress core's narrow
  Flex layout variation on `artisanpack/group` (suppressed via filter)
  and layers on top of the default `artisanpack/columns` distribution.
  Class output mirrors Tailwind's utility convention (`ap-flex`,
  `md:ap-justify-between`, `ap-gap-x-[16px]`, …) and is asserted
  byte-identical across the Blade, React, and Vue renderers via a
  shared fixture set. Legacy `layout.type === 'flex'` content on
  `artisanpack/group` migrates automatically on first edit. See
  [[blocks/Flex Layout]] for the full surface.

## [1.1.1] — 2026-06-15

### Fixed

- **Icon registration no longer collides with `owenvoke/blade-fontawesome`
  (#587).** In consumer apps that pull both `artisanpack-ui/visual-editor`
  and `owenvoke/blade-fontawesome` (the default path via
  `livewire-ui-components`), the visual-editor's `fas` / `far` / `fab`
  icon sets collided with the prefixes blade-fontawesome registers,
  causing `BladeUI\Icons\Exceptions\CannotRegisterIconSet` on every
  `<x-...>` render and breaking Blade-rendered routes and Livewire tests.
  `FontAwesomeFreeIconSets::register()` now detects the blade-fontawesome
  service provider and stops publishing the FA Free sets through
  `ap.icons.register-icon-sets`, so the icons-package no longer forwards
  the conflicting prefixes to `BladeUI\Icons\Factory::add()`. The
  visual-editor's own `IconSvgResolver` seeds its FA Free path map
  directly from `FontAwesomeFreeIconSets::discover()`, so the icon
  picker preview and the rendered Icon Block still resolve the bundled
  SVGs.

## [1.1.0] — 2026-06-14

The 1.1.0 release ships the full `artisanpack/icon` block (Phases 1–7),
a wave of new first-party `artisanpack/*` blocks, block bindings for
parent post/page/CPT data, block animations, border-gradient borders,
an auto-injected custom-block CSS pipeline for the editor canvas
iframe, a `BreadcrumbsResolver`, and a set of Cover block fixes. See
the new [[blocks/Icon Block]] page for the icon-block surface and the
per-block docs under `docs/blocks/` for the new block families.

### Added

- **Block bindings — connect block attrs to parent post/page/CPT
  data (#504).** Block attributes can now bind to fields on the
  surrounding post/page/CPT record, so editor placeholders render the
  live value and front-end output stays in sync without hand-rolled
  `render_callback` wiring.
- **Block animations — entrance / hover / continuous + custom
  keyframes (#489).** New animation panel on every supported block
  with entrance, hover, and continuous animation types plus a custom
  keyframe escape hatch. Animations are emitted as standard CSS on
  the wrapper so they survive both the editor canvas and the
  rendered front end.
- **Border gradients — linear / radial / conic borders + tabbed
  color/gradient picker (#490).** Border controls now accept a
  gradient as well as a solid color. Linear, radial, and conic
  gradient types are supported and exposed through a tabbed picker
  that shares its color/gradient surface with the existing palette
  controls.
- **Auto-inject custom block CSS into the editor canvas iframe
  (#566).** Custom CSS registered against a block via the block API
  is now mirrored into the editor canvas iframe so the canvas
  matches the rendered front end without the host app having to
  enqueue editor styles by hand.
- **`BreadcrumbsResolver` for `artisanpack/breadcrumbs` (#565).**
  The breadcrumbs block now resolves its trail through a dedicated
  resolver, decoupling the trail computation from the block's
  server renderer so host apps can override how a trail is built
  for custom post types and routes.
- **New first-party `artisanpack/*` block families (#495).** The
  block library grows with:
  - **`artisanpack/breadcrumbs`** (#496).
  - **`artisanpack/accordion` + `artisanpack/tabs`** families
    (#497).
  - **`artisanpack/grid` + `artisanpack/grid-item`** families
    (#498).
  - **`artisanpack/next-post` + `artisanpack/previous-post`**
    container blocks (#499).
  - **Site-chrome blocks — `artisanpack/copyright`,
    `artisanpack/marquee`, `artisanpack/comments-number`** (#500).
  - **Single-post content cluster — `artisanpack/single-content`,
    `artisanpack/related-posts`, `artisanpack/author-social-icons`,
    `artisanpack/social-share-content`** (#501).
  - **Search cluster** (#502).
  - **`artisanpack/skills-slider`** (#503).
  All new blocks ship under the `artisanpack/*` namespace as
  first-party blocks; the inserter icons and categories were
  restyled and recategorised in the same wave (#495).
- **Icon block — full Phase 1–7 surface (#552, #554, #555, #556,
  #557, #558).** The `artisanpack/icon` block lands across seven
  phases:
  - **Phase 1 — block scaffold** with server render + SVG
    sanitizer (#552).
  - **Phase 2 — bundled FA Free SVGs** (Solid, Regular, Brands)
    auto-registered against the icons package via the
    `ap.icons.register-icon-sets` filter, with inline-rendered
    SVGs on the front end (#554).
  - **Phase 4 — picker UI** with search, set chips, a recent
    tray, and a paginated grid (#555).
  - **Phase 5 — custom SVG paste / upload** for one-off icons
    sanitized through the same SVG sanitizer (#556).
  - **Phase 6 — admin icon-sets settings** + zip-upload pipeline
    for registering whole icon families through the admin UI
    (#557).
  - **Phase 7 — end-to-end coverage and docs (#558).** Cross-
  phase Pest tests now stitch the registration filter, catalog, picker
  endpoints, admin uploader, sanitizer, and block renderer together so
  regressions that only surface end-to-end are caught. New Vitest
  coverage exercises the WP-style envelope plumbing and the
  width/height override path. Docs add a dedicated [[blocks/Icon Block]]
  page covering the block usage walkthrough, the developer recipe for
  `ap.icons.register-icon-sets`, the admin upload walkthrough, and FA
  Pro guidance (BYO SVGs, no token storage).
- **Icon block — independent width/height overrides.** The
  `artisanpack/icon` block now supports per-axis `width` and `height`
  attributes that override the uniform `size`. The inspector ships a
  `Dimensions` panel with a `NumberControl`-backed size and width/
  height `UnitControl`s (`px` / `em` / `rem` / `%` / `vw` / `vh`). All
  three controls emit changes on every keystroke so the canvas
  updates live.
- **Icon block — dedicated Icon color field.** The standard WordPress
  text-color control is replaced by a `Sidebar → Color → Icon` picker
  that writes to a new `iconColor` attribute and is applied directly
  to the body span as `color`. The bundled SVGs ship with
  `fill: currentcolor`, so the picked color flows through to the
  icon's fill. Mirrors the ndiego reference Icon Block split.

### Fixed

- **Icon block — WP style controls now apply on the canvas and front
  end.** Background, border, padding, and margin set via the
  inspector's standard controls now reach the rendered block. The
  block previously declared `__experimentalSkipSerialization: true`
  for every support and then never read the styles back, so author
  selections silently no-op'd. The block now lets WordPress serialize
  background/border/spacing onto the wrapper via `useBlockProps()`,
  and the server renderer applies the same envelope to the wrapper
  div (with the legacy top-level `backgroundColor` attribute kept as
  a fallback for posts saved before the fix). Palette-color slugs
  resolve through the standard `has-{slug}-background-color` /
  `has-{slug}-border-color` classes.
- **Icon block — decorative + linked icons now produce labeled
  anchors.** When `isDecorative`, `link`, and `ariaLabel` are all set,
  the supplied `ariaLabel` is now promoted onto the `<a>` itself
  rather than dropped. The body span remains `aria-hidden="true"`
  (the SVG is the decorative element), but the anchor finally has an
  accessible name. The editor-side `hasDecorativeLinkConflict()`
  warning still fires when no `ariaLabel` is supplied, which is the
  scenario the warning was always meant to flag.
- **Cover block — background classes now route to the overlay span
  (#583).** Palette-color background classes on the Cover block now
  reach the overlay span where the legacy markup expects them,
  instead of being applied to the wrapper and producing a flat,
  unblended fill.
- **Cover block — unfreeze editor on overlay color pick + media
  select (#578).** Picking an overlay color or a media item in the
  Cover block no longer hangs the editor. Stale refinement results
  are guarded against and the block's effect chain no longer
  reschedules itself in a tight loop.

## [1.0.0] — 2026-06-08

First stable release of the V1 surface. Promotes `1.0.0-beta1` to GA
with the additions and fixes listed below. The post editor, site
editor, `artisanpack/*` block fork, and first-class
`artisanpack-ui/cms-framework` pairing — all introduced across
`1.0.0-alpha.1` and `1.0.0-beta1` — are now considered stable. See the
[README](README.md) and the [`docs/`](docs/) directory for the full V1
surface.

### Added

- **Laravel 13 support.** `illuminate/support` constraint updated to
  `^11.0|^12.0|^13.0` (Laravel 5.3–10 are no longer supported — the
  previous `>=5.3` floor was effectively dead code, since `orchestra/
  testbench` already pinned us to Laravel 11+). Laravel 13 requires
  PHP 8.3+, which is enforced transitively through L13's own `php`
  constraint; the package PHP floor (`^8.2`) is unchanged for users
  staying on Laravel 11/12.

### Fixed

- **Paragraph block-gap spacing.** Paragraph blocks now correctly
  inherit `is-layout-flow` block-gap spacing in the rendered output
  (#540).
- **`artisanpack/post-title` editable inline.** The post-title block
  now edits the live entity directly instead of getting stuck on its
  initial value (#546).
- **FontSizePicker duplicate-key warnings.** Font-size presets are
  now deduplicated before being handed to `FontSizePicker`, silencing
  the React duplicate-key warning that surfaced under certain
  theme.json configurations (#547).
- **`tsc --noEmit` errors in the core-data shim.** Resolved the
  TypeScript errors surfaced by `tsc --noEmit` in the core-data shim
  and its tests (#542).

## [1.0.0-beta1] — V1 beta release

First public beta of the V1 surface. Ships the post editor, the site
editor, the block fork to the `artisanpack/*` namespace, and first-class
pairing with `artisanpack-ui/cms-framework`. See the [README](README.md)
and the [`docs/`](docs/) directory for the full V1 surface.

### Added

- **Site editor (Phase H).** Mounted at `/visual-editor/site`. Templates,
  template parts, theme.json-backed global styles, navigation menus, and
  patterns — all editable through a custom shell built on
  `@wordpress/block-editor`. Backed by cms-framework's
  `Template`/`TemplatePart`/`GlobalStyles`/`Menu`/`Pattern` models when
  cms-framework is installed; fail-closed `SiteEditorAccessGate`
  defaults to deny until the host binds a permissive gate (or installs
  cms-framework, which auto-binds `CmsFrameworkInstallGate`).
- **Documentation set.** Fifteen new / refreshed docs covering install,
  content model, Blade component reference, post-editor surface, custom
  blocks, renderers, site-editor surface, templates, global styles,
  navigation, patterns, Livewire and Inertia embedding recipes,
  theming, troubleshooting, and migration. Entry point:
  [`docs/getting-started.md`](docs/getting-started.md).
- **V1 expansion plan retained as historical record:**
  [`docs/plans/11-v1-expansion.md`](docs/plans/11-v1-expansion.md).

### Changed

- README rewritten to reflect final V1 scope: post editor + site editor
  + patterns + Livewire/Inertia embedding.

## [1.0.0-alpha.1] — Gutenberg adoption marker

### Added

- **Block fork (Phase I) — `core/*` → `artisanpack/*`.** All 42 forked
  blocks plus the pre-existing `artisanpack/callout` and `artisanpack/form`
  now register under the `artisanpack/*` namespace. Clusters landed in
  order: I0 paragraph pilot (#408), I1 content (#409), I2 media (#410),
  I3 layout incl. grid/grid-item split (#411), I4 widgets (#412), I5
  entity (#413), I6 loop/feed (#414), I7 cutover (#415). The editor
  bootstraps in `editor-app.tsx` and `site-editor-app.tsx` call
  `registerArtisanPackBlocks()` instead of `registerCoreBlocks()`;
  `@wordpress/block-library` is demoted to `devDependencies` and consumed
  only by `scripts/upstream-diff.mjs`. Per-block `upstream-state.json`
  files keep the drift trail. `from:core/*` transforms ship on every fork
  so pasted upstream markup still converts. Full plan:
  [`docs/plans/13-block-fork.md`](docs/plans/13-block-fork.md).
- **Block fork completion gate (Phase I8, #416).** Confirms the cutover
  is complete and hands release-notes inputs to #325. Adds
  [`docs/release-notes-inputs-1.0.0.md`](docs/release-notes-inputs-1.0.0.md)
  with the pinned `@wordpress/*` table and the visual-editor ↔
  cms-framework version pair (`v1.0.x` ↔ `^1.1`). Wires the per-block
  upstream-diff CLI into CI so the first post-fork Renovate cycle
  triages drift before merging.
- **cms-framework integration (Phase G).** First-class pairing with [`artisanpack-ui/cms-framework`](https://github.com/ArtisanPack-UI/cms-framework). When both packages are installed, the visual editor can edit cms-framework's `Post` and `Page` content end-to-end; `core/site-*` blocks read from cms-framework's settings store via `apGetSetting('site.*')`; `core/post-*`, `core/archives`, `core/categories`, `core/tag-cloud`, `core/query`, and `core/query-loop` come off the V1 deny-list and resolve against cms-framework's models and term endpoints. Loose coupling preserved — both packages remain usable standalone; cms-framework's editor wiring is guarded by `class_exists(\ArtisanPackUI\VisualEditor\VisualEditor::class)`. Pair-versioning matrix lives in the README; the [`docs/g6-smoke-flow.md`](docs/g6-smoke-flow.md) flow runs against the version pair before every release tag. Full integration contract: [`docs/plans/12-cms-framework-integration.md`](docs/plans/12-cms-framework-integration.md).
- "Using with cms-framework" README section. Covers install, migrations, the merged resource map under `ap.visual-editor.resources`, and the version-pair contract.
- `visual_editor.*` permissions schema seeded into cms-framework's RBAC when both packages are installed (G5). Policies still use the "any authenticated user" baseline in V1.0; delegation lands in V1.1 behind `artisanpack.visual-editor.authorization.delegate_to_cms_framework`.
- `artisanpack/form` block. Dynamic block that lets authors pick a form from the artisanpack-ui/forms package via the InspectorControls sidebar, and renders a `<div data-keystone-form="…" data-form-id="…">` mount-point on the public site. The host application supplies a JS island that hydrates the mount-point with the forms package's React `FormRenderer`. Registration is gated on `class_exists(ArtisanPackUI\Forms\Models\Form::class)` so visual-editor still boots when forms is absent.
- Stale-selection guard in the form block's editor preview. When the persisted `formId` no longer matches any form returned by `/api/v1/forms`, the canvas renders a distinct `<Placeholder>` with a "Reset selection" button that clears the attribute back to `0`. Replaces the previous silent fall-through to the 404 "inactive" message, which mis-coded deleted forms as merely deactivated.

### Changed

- `FormBlock::validateAttrs()` now parses `formId` with `FILTER_VALIDATE_INT` via a `normalizeFormId()` helper. Rejects float strings (`"12.9"`) and scientific notation (`"1e2"`) that `is_numeric` + `(int)` previously truncated into unrelated form ids; non-positive values fall through to the "select a form" placeholder.
- Form block editor preview's 404 error message updated from "This form is inactive…" to "This form is unavailable — it may have been deleted or deactivated." — neutral wording that fits both branches of `FormBlock::render`'s server-side check.
- All `block.json` `textdomain` values aligned with the editor runtime's `TEXT_DOMAIN` constant (`artisanpack-visual-editor`). Translations for block titles/descriptions/keywords now resolve under the same domain as the rest of the editor strings.
