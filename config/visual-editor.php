<?php

/**
 * Visual editor package configuration.
 *
 * Merged into the host application's `artisanpack.visual-editor` key by
 * VisualEditorServiceProvider. Applications override any of these values by
 * publishing this file to `config/artisanpack/visual-editor.php`.
 *
 * @package    ArtisanPack_UI
 * @subpackage VisualEditor
 *
 * @author     Jacob Martella <me@jacobmartella.com>
 *
 * @since      1.0.0
 */

declare( strict_types=1 );

return [

	/*
	|--------------------------------------------------------------------------
	| Resources
	|--------------------------------------------------------------------------
	|
	| Maps a URL-friendly slug to the Eloquent model class that backs it. The
	| editor's REST routes resolve `/visual-editor/api/{resource}/{id}/content`
	| through this map, so adding a new editable content type is a config
	| change — no per-model controllers required. Every listed model must use
	| the `HasBlockContent` trait.
	|
	*/

	'resources' => [
		// 'posts' => App\Models\Post::class,
		// 'pages' => App\Models\Page::class,
	],

	/*
	|--------------------------------------------------------------------------
	| Site meta
	|--------------------------------------------------------------------------
	|
	| Fallback values for the `core/site-title`, `core/site-tagline`, and
	| `core/site-logo` block resolvers. The Blade renderer reads these only
	| when `apGetSetting()` (cms-framework's settings helper) is unavailable;
	| the React/Vue renderers consume them via the `siteMeta` prop or the
	| bootstrap-time `setDefaultSiteMeta()` API. See plan 12 §4.3 for the
	| full G2 site-meta bridge contract.
	|
	| `logo_id` and `icon_id` are media-library media ids; the resolver
	| converts them to URLs via `apGetMediaUrl()` when present.
	|
	*/

	'site_meta' => [
		'title'       => null,
		'description' => null,
		'url'         => null,
		'logo_id'     => null,
		'icon_id'     => null,
	],

	/*
	|--------------------------------------------------------------------------
	| Resolver
	|--------------------------------------------------------------------------
	|
	| Behaviour knobs for the server-side `_resolved*` stamping pipeline.
	|
	| `adjacency.auto_query` controls the generic query-fallback used by
	| `PostResolver::adjacentPost()` when stamping `post-navigation-link`
	| blocks (#527). The resolver first tries common accessors
	| (`previous_post`, `next_post`, etc.) on the host's Post model. When
	| no accessor is present and this flag is `true`, the resolver issues
	| an Eloquent query against `published_at` to find the adjacent
	| published row. Costs one extra query per `post-navigation-link`
	| render per direction, so hosts whose Post model already exposes
	| explicit adjacency accessors should leave this off.
	|
	| Requirements for the fallback to run:
	|
	|   - the Post model exposes `newQuery()` (Eloquent default), AND
	|   - the post being rendered has a non-empty `published_at` value.
	|
	*/

	'resolver' => [
		'adjacency' => [
			'auto_query' => false,
		],
	],

	/*
	|--------------------------------------------------------------------------
	| Block registry filters
	|--------------------------------------------------------------------------
	|
	| `enabled_blocks` acts as an allow-list: when non-empty, only the listed
	| block names are exposed to the inserter. `disabled_blocks` is an
	| always-applied deny-list. The deny-list wins when both are set. Use
	| fully-qualified block names (e.g. `artisanpack/paragraph`).
	|
	| I7 (#415) cutover: all blocks now use the `artisanpack/*` namespace.
	| `@wordpress/block-library`'s `registerCoreBlocks()` is no longer
	| called — the editor registers only the forked blocks discovered
	| under `resources/js/visual-editor/blocks/`. The deny-list is empty
	| because core/* blocks are never registered; blocks deferred to
	| future releases simply stay off the allow-list.
	|
	*/

	'enabled_blocks' => [
		// Content cluster — forked to artisanpack/* (I0 #408, I1 #409).
		// I7 (#415): core/* counterparts are no longer registered; only the
		// artisanpack/* forks surface in the inserter. The `from:core/*`
		// transforms still migrate existing core/* content on deserialize.
		'artisanpack/paragraph',
		'artisanpack/heading',
		'artisanpack/list',
		'artisanpack/quote',
		'artisanpack/code',
		'artisanpack/preformatted',
		'artisanpack/pullquote',
		'artisanpack/verse',
		'artisanpack/table',
		// Media cluster — forked to artisanpack/* (I2 #410).
		'artisanpack/image',
		'artisanpack/gallery',
		'artisanpack/video',
		'artisanpack/audio',
		'artisanpack/file',
		'artisanpack/embed',
		'artisanpack/cover',
		'artisanpack/media-text',
		// Entity cluster — forked to artisanpack/* (I5 #413). I7 (#415):
		// core/* counterparts are no longer registered; only the
		// artisanpack/* forks surface in the inserter. The forks read
		// entity data through the same core-data shim selectors the core
		// blocks used (#395 G0, #399 G3) and render server-side from
		// stamped _resolved* attributes. The `from:core/*` transforms
		// still migrate existing core/* content on deserialize.
		'artisanpack/template-part',
		'artisanpack/post-title',
		'artisanpack/post-content',
		'artisanpack/post-excerpt',
		'artisanpack/post-date',
		'artisanpack/post-author',
		'artisanpack/post-featured-image',
		'artisanpack/site-title',
		'artisanpack/site-tagline',
		'artisanpack/site-logo',
		'artisanpack/navigation',
		// G4b (#401) / I6 (#414) — taxonomy/feed widgets forked to
		// artisanpack/*, backed by cms-framework's term + post APIs
		// through the dynamic-block registry.
		'artisanpack/categories',
		'artisanpack/tag-cloud',
		'artisanpack/archives',
		// G4c-2 (#402) / I6 (#414) — query + post-template forked to
		// artisanpack/*. Pre-resolved server-side by `QueryInliner`
		// against cms-framework's `QueryRuntime`.
		'artisanpack/query',
		'artisanpack/post-template',
		'artisanpack/callout',
		// Layout cluster — forked to artisanpack/* (I3 #411). `row` and
		// `stack` ship as variations of artisanpack/group (registered name
		// stays `artisanpack/group`), so they are not listed here.
		'artisanpack/group',
		'artisanpack/columns',
		'artisanpack/column',
		'artisanpack/buttons',
		'artisanpack/button',
		'artisanpack/separator',
		'artisanpack/spacer',
		'artisanpack/details',
		// Widgets cluster — forked to artisanpack/* (I4 #412).
		'artisanpack/search',
		'artisanpack/latest-posts',
		// Comments family — Pass 1 forks (#519): wrapper + template +
		// per-comment display blocks.
		'artisanpack/comments',
		'artisanpack/comment-template',
		'artisanpack/comment-author-avatar',
		'artisanpack/comment-author-name',
		'artisanpack/comment-content',
		'artisanpack/comment-date',
		'artisanpack/comment-edit-link',
		'artisanpack/comment-reply-link',
		// Comments family — Pass 2 forks (#519): post-level comments
		// metadata + pagination cluster. Post-level display blocks
		// resolve through PostResolver; pagination blocks render
		// against request-time state.
		'artisanpack/post-comments-form',
		'artisanpack/post-comments-count',
		'artisanpack/post-comments-link',
		'artisanpack/post-comments-title',
		'artisanpack/comments-pagination',
		'artisanpack/comments-pagination-next',
		'artisanpack/comments-pagination-numbers',
		'artisanpack/comments-pagination-previous',
		// First-party blocks added under umbrella #495. Child blocks are
		// listed alongside their parents so the inserter allow-list does
		// not filter them out of the parent's template.
		'artisanpack/breadcrumbs',
		'artisanpack/accordions',
		'artisanpack/accordion',
		'artisanpack/accordion-title',
		'artisanpack/accordion-body',
		'artisanpack/tabs',
		'artisanpack/tab-section',
		'artisanpack/grid',
		'artisanpack/grid-item',
		'artisanpack/next-post',
		'artisanpack/previous-post',
		'artisanpack/copyright',
		'artisanpack/marquee',
		'artisanpack/comments-number',
		'artisanpack/single-content',
		'artisanpack/related-posts',
		'artisanpack/author-social-icons',
		'artisanpack/social-share-content',
		'artisanpack/search-field',
		'artisanpack/search-filters',
		'artisanpack/search-filters-buttons',
		'artisanpack/search-filters-taxonomy',
		'artisanpack/post-types-search-results',
		'artisanpack/single-post-types-search-results',
		'artisanpack/skills-slider',
	],

	'disabled_blocks' => [
		// I7 (#415): with the cutover to artisanpack/*, core/* blocks
		// are no longer registered. The deny-list is empty — all
		// artisanpack/* blocks surface through the `enabled_blocks`
		// allow-list above. Blocks deferred to future releases
		// (e.g. comments, V1.1+) simply stay off the allow-list.
	],

	/*
	|--------------------------------------------------------------------------
	| Media bridge
	|--------------------------------------------------------------------------
	|
	| The editor's media picker and upload plumbing route to whatever media
	| library the host application provides. The default integration is
	| `artisanpack-ui/media-library`: the host calls `registerMediaBridge`
	| with `MediaModal` and `uploadMedia` before `bootVisualEditor`. Any
	| library that exposes an equivalent picker component (props:
	| `open`, `onClose`, `onSelect`, `multiSelect`, `allowedTypes`,
	| `context`, `title`) and upload function (`(file, metadata?) =>
	| Promise<{ data: Media } | Media>`) can be swapped in — the
	| `media.bridge` key below records the active choice so server-side
	| code (for example the Featured Image hydration path) can pick the
	| matching PHP adapter from the container.
	|
	| Server-side record conversion is delegated to
	| `ArtisanPackUI\VisualEditor\MediaBridge\GutenbergAttachmentAdapter`.
	| Rebind that class in the container to override the Gutenberg shape
	| emitted by `toGutenberg()`; the default implementation duck-types
	| the `artisanpack-ui/media-library` Media model.
	|
	*/

	'media' => [
		'bridge'  => 'artisanpack-ui/media-library',
		'adapter' => \ArtisanPackUI\VisualEditor\MediaBridge\GutenbergAttachmentAdapter::class,
	],

	/*
	|--------------------------------------------------------------------------
	| API routes
	|--------------------------------------------------------------------------
	|
	| Middleware stack applied to the auto-registered `/visual-editor/api/*`
	| routes. The defaults cover a session-authenticated web app; API-only or
	| stateless apps can swap in `auth:sanctum`, `auth:api`, etc.
	|
	*/

	'api' => [
		'middleware' => [ 'api', 'auth' ],
	],

	/*
	|--------------------------------------------------------------------------
	| Authorization
	|--------------------------------------------------------------------------
	|
	| Controls how the default policy for the legacy VisualEditorPost model
	| gates access. Resource models (via `HasBlockContent`) delegate to their
	| own Laravel policies and ignore this flag.
	|
	*/

	'authorization' => [
		'restrict_by_owner' => false,
	],

	/*
	|--------------------------------------------------------------------------
	| Loginout block (#522)
	|--------------------------------------------------------------------------
	|
	| Configures the `artisanpack/loginout` block's server-side renderer.
	| The package does not ship login / logout routes of its own — the
	| resolver looks up the configured named routes first, falling back
	| to the literal paths when the names are not registered. Hosts on
	| a non-default guard (e.g. `sanctum`, `api`) set `guard` here.
	|
	| Set `redirect_param` to whatever query key your auth stack reads
	| when redirecting after login / logout (Laravel Breeze /
	| Jetstream / Fortify all use `redirect_to`, the upstream WP
	| `wp_loginout()` default).
	|
	| ⚠️  POST-vs-GET caveat for `logout_route`: the block always emits a
	| plain `<a>`, but Breeze / Jetstream / Fortify register `logout` as
	| POST + CSRF — clicking the rendered link will hit a 405 unless the
	| host either (a) registers a GET-side logout endpoint and points
	| `logout_route` / `logout_path` at it, or (b) rewrites the resolved
	| envelope through the `ap.visual-editor.loginout.envelope` filter
	| (e.g. swap in a `URL::signedRoute()` link to a GET handler that
	| invalidates the session). The default of `logout` is kept because
	| dropping it would mean `logout_path` shows through as a relative
	| URL even when the host *has* wired a GET endpoint under that name;
	| the right knob to flip on a Breeze install is the envelope filter.
	|
	| For fully custom URL resolution (per-tenant routes, SSO, etc.)
	| override the resolved envelope through the
	| `ap.visual-editor.loginout.envelope` filter hook instead.
	|
	*/

	'loginout' => [
		'guard'          => '',
		'login_route'    => 'login',
		'login_path'     => '/login',
		'logout_route'   => 'logout',
		'logout_path'    => '/logout',
		'redirect_param' => 'redirect_to',
	],

	/*
	|--------------------------------------------------------------------------
	| Breadcrumbs block (#565)
	|--------------------------------------------------------------------------
	|
	| Configures the `artisanpack/breadcrumbs` block's server-side trail
	| resolver. The resolver builds a default trail
	| (`Home → …ancestors → current`) for every breadcrumbs block on
	| render; hosts customize the trail through the
	| `ap.visual-editor.breadcrumbs.trail` filter (e.g. to insert a
	| "Category" hop between Home and a blog post).
	|
	| `home_url` overrides the root link target — leave null to fall back
	| to `url('/')`. `home_label` overrides the human-readable label — leave
	| null to fall back to the translated "Home" string.
	|
	*/

	'breadcrumbs' => [
		'home_url'   => null,
		'home_label' => null,
	],

	/*
	|--------------------------------------------------------------------------
	| Global styles
	|--------------------------------------------------------------------------
	|
	| Configures the `globalStyles` entity the site editor customizes.
	| `theme` scopes the singleton lookup — each installed theme gets its
	| own global-styles record. `schema_version` pins the theme.json
	| schema the package accepts on `PUT` requests; see
	| `docs/global-styles.md` for the contract and how we handle future
	| upgrades. `base_path` is an absolute path to the PHP file returning
	| the default `base` payload (the theme.json defaults the site-editor
	| compares user overrides against); leave null to use the package's
	| bundled defaults.
	|
	*/

	'global_styles' => [
		'theme'          => 'artisanpack-base',
		'schema_version' => 3,
		'base_path'      => null,
	],

	/*
	|--------------------------------------------------------------------------
	| Breakpoints (#487)
	|--------------------------------------------------------------------------
	|
	| Named breakpoints the editor's viewport switcher and the responsive
	| value resolver use. Resolved in priority order:
	|
	|   1. Active theme's `theme.json` → `settings.custom.artisanpack.breakpoints`
	|   2. This config array (host-app overrides)
	|   3. `BreakpointRegistry::DEFAULTS` (Tailwind v4 mins)
	|
	| Merging is by key, so an entry here for `sm` resizes the default
	| `sm` breakpoint without affecting the others; a new key like `3xl`
	| adds a breakpoint. Values may be integer pixels (`640`) or CSS
	| length strings (`'640px'`). Validation runs at registry-build time
	| and throws on bad input — see `BreakpointRegistry::validate()`.
	|
	| The implicit `base` slot (no min-width, applies everywhere) is
	| reserved and cannot be redefined here.
	|
	*/

	'breakpoints' => [
		// 'sm'  => '640px',
		// 'md'  => '768px',
		// 'lg'  => '1024px',
		// 'xl'  => '1280px',
		// '2xl' => '1536px',
	],

	/*
	|--------------------------------------------------------------------------
	| Interactive states (#488)
	|--------------------------------------------------------------------------
	|
	| Interactive states the InspectorControls state switcher and the
	| state value resolver use. Resolved in priority order:
	|
	|   1. Active theme's `theme.json` → `settings.custom.artisanpack.states`
	|   2. This config array (host-app overrides)
	|   3. `StateRegistry::DEFAULTS` (idle, hover, focus, focus-visible,
	|      active, disabled)
	|
	| Each state is an associative array with these keys:
	|
	|   - label          (string)   Human-readable label shown in the inspector.
	|   - selector       (string)   CSS pseudo or attribute selector. The token
	|                               `&` is replaced with the block's unique
	|                               class scope. Reserved `idle` must use `''`.
	|   - icon           (string)   Optional icon slug for the inspector chip.
	|   - inheritsFrom   (string)   Parent state key for null-fallback. The
	|                               `idle` slot is the implicit root.
	|   - hoverMediaWrap (bool)     When true, the renderer wraps the rule in
	|                               `@media (hover: hover)`. Default `false`.
	|
	| Merging is by key, so an entry here for `hover` extends the default
	| hover state without disturbing the others. A new key like
	| `aria-current` adds a state. To remove a built-in state, set its
	| key to `null` — the registry will skip it.
	|
	| The reserved `idle` state is the implicit base of every inheritance
	| chain and cannot be removed or aliased.
	|
	*/

	'states' => [
		// 'aria-current' => [
		//     'label'        => 'Current',
		//     'selector'     => '&[aria-current="page"]',
		//     'icon'         => 'flag',
		//     'inheritsFrom' => 'idle',
		// ],
	],

	/*
	|--------------------------------------------------------------------------
	| Block animations (#489)
	|--------------------------------------------------------------------------
	|
	| Animations the InspectorControls "Animations" panel offers and the
	| renderer emits CSS for. Three families:
	|
	|   - entrance  : plays once on viewport entry (IntersectionObserver)
	|   - hover     : transition curve + preset on `:hover`
	|   - continuous: loop animation
	|
	| Resolved in priority order:
	|
	|   1. Active theme's `theme.json` → `settings.custom.artisanpack.animations`
	|   2. This config array (host-app overrides)
	|   3. `AnimationRegistry::DEFAULTS`
	|
	| Each entry is `[family => [key => definition]]`. To remove a built-in,
	| set its key to `null`. See `AnimationRegistry::validate()` for the
	| required shape per family.
	|
	*/

	'animations' => [
		// 'entrance' => [
		//     'fade-in-blur' => [
		//         'label'    => 'Fade in (blur)',
		//         'keyframe' => 'apFadeInBlur',
		//         'duration' => 700,
		//         'easing'   => 'ease-out',
		//     ],
		// ],
	],

	/*
	|--------------------------------------------------------------------------
	| Custom keyframes (#489)
	|--------------------------------------------------------------------------
	|
	| Named `@keyframes` blocks the host wants available in the editor's
	| animation dropdowns. These are merged with whatever the active
	| theme.json declares under `settings.custom.artisanpack.keyframes`
	| and with editor-authored keyframes persisted in the Global Styles
	| JSON. Built-in names are reserved.
	|
	*/

	'keyframes' => [
		// [
		//     'name'  => 'confetti',
		//     'stops' => [
		//         [ 'at' => '0%',   'transform' => 'translateY(0)' ],
		//         [ 'at' => '50%',  'transform' => 'translateY(-12px) rotate(10deg)' ],
		//         [ 'at' => '100%', 'transform' => 'translateY(0)' ],
		//     ],
		// ],
	],

	/*
	|--------------------------------------------------------------------------
	| Site editor (H5)
	|--------------------------------------------------------------------------
	|
	| Static-config entry points for the five site-editor entity types. Each
	| key is also a filter slug — packages like cms-framework register their
	| entities at runtime through `addFilter('ap.visual-editor.{type}', ...)`.
	|
	| Static config wins on key collision: host-app entries listed here take
	| precedence over filter-supplied entries with the same key.
	|
	| Standalone visual-editor installs (no cms-framework, no host
	| registrations) leave these empty and the editor's site-editor surface
	| boots cleanly with no entities. See plan 14 §4.4 for the full filter
	| contract and the ResolvedX value-object shapes consumed by H6.
	|
	*/

	'site-editor' => [
		// array<string, array> keyed by template slug.
		// Each entry: { slug, theme, title, status, source, content: { raw, blocks }, has_theme_file, is_custom, wp_id?, ... }
		'templates' => [],

		// array<string, array> keyed by template-part slug.
		// Each entry adds: { area: 'header'|'footer'|'sidebar'|'general' }
		'template-parts' => [],

		// array<string, array> keyed by pattern slug.
		// Each entry: { slug, title, source: 'theme'|'user', synced, content: { raw, blocks }, categories?, block_types? }
		'patterns' => [],

		// array<string, mixed>|null — singleton, not a map.
		// { theme, settings, styles, variations? }
		'global-styles' => null,

		// array<string, array> keyed by theme-declared menu location.
		// Each entry: { location, name, items: [...] }
		'navigation' => [],
	],

];
