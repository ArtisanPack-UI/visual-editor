# Embed Blocks - Work In Progress Notes

## Issue
GitHub Issue #148 - Build embed blocks: Generic Embed, Map, Social, Custom HTML
Branch: `feature/148-embed-blocks` (based off `add/phase-five`)
PR: https://github.com/ArtisanPack-UI/visual-editor/pull/154

## What's Been Built

### Server-Side (Complete)
- **OEmbedService** (`src/Services/OEmbedService.php`): oEmbed resolution with 11+ providers, OpenGraph fallback, caching, SSRF protection, platform detection
- **EmbedController** (`src/Http/Controllers/EmbedController.php`): POST `/api/visual-editor/embed/resolve` endpoint (api middleware, no CSRF)
- **4 Block Classes**: EmbedBlock, MapEmbedBlock, SocialEmbedBlock, CustomHtmlBlock in `src/Blocks/Embed/`
- **Block views**: edit.blade.php and save.blade.php for all 4 blocks (though JS renderers are used for editor display)
- **CustomHtml toolbar**: Preview toggle button (`src/Blocks/Embed/CustomHtml/views/toolbar.blade.php`)
- **Tests**: 975 tests passing (69 new for embed blocks + OEmbedService)

### Client-Side (Mostly Complete)
- **JS Renderers** registered in `editor.blade.php` for all 4 block types (embed, social-embed, map-embed, custom-html)
- **Event delegation** in `_editor-canvas-content.blade.php` init() method for embed resolve, map search, and map coordinate buttons
- **Helper functions** in `editor.blade.php`: `veExtractIframeSrc()` (extracts iframe src from oEmbed HTML, handles Bluesky AT URIs), `veResolveEmbed()` (shared fetch helper)

## Current Status

### Working
- **Generic Embed block**: YouTube, Vimeo, Spotify, and most oEmbed-compatible URLs resolve and display correctly using direct iframe src extraction
- **Social Embed block**: Twitter/X, Facebook work. Most platforms work via oEmbed
- **Custom HTML block**: Code editor textarea, preview toggle (toolbar button), sanitization toggle, warning banner all work
- **Bluesky**: AT URI extraction from oEmbed HTML constructs the embed.bsky.app iframe URL directly
- **API endpoint**: Verified working via curl and browser

### NOT Working - Map Embed Block
The map search button does not respond to clicks. The click handler IS registered in `init()` via `el.addEventListener('click', ...)` matching the same pattern as variation picker, columns layout, etc. — but clicking the Search button produces no visible result (no loading spinner, no geocoding request).

**What's been tried:**
1. Alpine x-data with x-on:click directives in JS renderer output → Alpine doesn't initialize x-data within x-html
2. Inline x-on:click in the canvas x-on:click expression → `veResolveEmbed` scoping error, and map handler may not have been reached due to earlier error
3. Canvas x-on:click with inlined fetch (no external function) → embeds work, maps still don't
4. el.addEventListener('click', ...) in init() → current approach, embeds work, maps still don't

**Debugging suggestions:**
- Add `console.log('MAP CLICK', e.target, mapBtn)` at the top of the map click listener in init() to verify if the handler fires
- Check if the map block's HTML rendered by the JS renderer actually contains `data-ve-map-search` attribute on the button
- Check if clicking the map block triggers a re-render (via x-html) that replaces the input element before the click handler reads its value — this could happen if block selection triggers a store update that causes the reactive x-for/x-html to re-evaluate
- The input `data-ve-map-address` has no value attribute — verify `querySelector('[data-ve-map-address]')` actually finds the element and that `input.value` is populated after typing
- Try using `onclick` attribute directly in the JS renderer's button HTML as a test to bypass event delegation entirely

**Key files for map debugging:**
- `resources/views/components/editor.blade.php` — map-embed JS renderer (search for `br.register( 'map-embed'`)
- `resources/views/components/_editor-canvas-content.blade.php` — map click handler in init() (search for `data-ve-map-search`)

## Architecture Notes

### How Block Rendering Works
1. Blocks with `hasJsRenderer: true` in block.json are rendered by JS renderers registered via `Alpine.store('blockRenderers').register(type, { render(block) { ... } })`
2. The canvas uses `x-html="getBlockHtml(block)"` which calls the renderer
3. When `Alpine.store('editor').updateBlock(id, attrs)` is called, the store updates the block's attributes, which triggers Alpine's reactivity to re-evaluate `x-html`, calling `getBlockHtml(block)` again with the new attributes
4. For interactive elements in JS-rendered HTML, event delegation is required because x-html doesn't initialize Alpine directives

### Event Delegation Pattern
Interactive buttons in JS-rendered blocks use data attributes (`data-ve-*`) and are handled by `el.addEventListener('click', ...)` in the canvas component's `init()` method. This is the same pattern used by:
- Group variation picker (`data-ve-set-variation`)
- Columns layout picker (`data-ve-set-columns-layout`)
- Grid layout picker (`data-ve-set-grid-layout`)
- Add column/grid-item buttons (`data-ve-add-column`, `data-ve-add-grid-item`)
- Table layout picker (`data-ve-set-table-layout`)

### File Locations
- Block PHP classes: `src/Blocks/Embed/{Embed,MapEmbed,SocialEmbed,CustomHtml}/`
- Block metadata: `block.json` in each block directory
- Server-rendered views: `views/edit.blade.php` and `views/save.blade.php` in each block directory
- JS renderers: `resources/views/components/editor.blade.php` (search for `br.register( 'embed'`)
- Canvas click handlers: `resources/views/components/_editor-canvas-content.blade.php` (in init() method)
- API route: `routes/api.php`
- API controller: `src/Http/Controllers/EmbedController.php`
- oEmbed service: `src/Services/OEmbedService.php`
- Translations: `resources/lang/en/ve.php` (embed section near bottom)
- Tests: `tests/Unit/Blocks/Embed/` and `tests/Unit/Services/OEmbedServiceTest.php`
- Dev app test blocks: `resources/views/packages/visual-editor/editor-shell.blade.php` (blocks 30-34)

## Uncommitted Changes
All changes are staged but not committed. The last commit on the branch is `c104db5` (CodeRabbit review fixes). Everything since then needs to be committed as one or more commits covering:
- API route + controller + service provider registration
- JS renderers in editor.blade.php
- Canvas event delegation in _editor-canvas-content.blade.php
- hasJsRenderer: true in all 4 block.json files
- CustomHtml toolbar + preview schema changes
- Bluesky iframe src extraction
- Test blocks in editor-shell.blade.php
- Translation strings
- Test updates
