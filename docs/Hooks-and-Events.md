# Hooks and Events

The visual editor exposes extension points through four mechanisms:

- **PHP filters** — `applyFilters('ap.visualEditor.*', ...)` for value transformation (resource map, site-editor entities, server-rendered block HTML, etc.)
- **PHP actions** — `doAction('ap.visualEditor.*', ...)` for side effects
- **JavaScript filters** — `addFilter('ap.visualEditor.*', ...)` via `@wordpress/hooks` for editor-side extension (document panels, background controls, canvas styles)
- **Browser events** — `CustomEvent` dispatched on `window` for client-side integration (autosave, change, save)

All PHP hooks use the global helpers from [`artisanpack-ui/hooks`](https://github.com/ArtisanPack-UI/hooks). All browser events are plain `CustomEvent` instances dispatched on `window`.

> **Hook rename (v1.5.0, #664).** Every visual-editor hook now uses
> camelCase. The old kebab-case names (`ap.visual-editor.*`,
> `ap.icons.register-icon-sets`, `visual_editor.pre_publish_checks`)
> remain functional through `deprecateHook()` aliases that route
> callbacks to the canonical name, but log a deprecation notice the
> first time each alias resolves per process. New code should use the
> camelCase names documented below. See
> [`src/Support/HookAliases.php`](../src/Support/HookAliases.php) for
> the full rename table.

---

## PHP filters

### `ap.visualEditor.resources`

Register slug → Eloquent model class mappings used by `/visual-editor/api/{resource}/{id}/content`.

**Signature:** `array $resources -> array`

```php
addFilter('ap.visualEditor.resources', function (array $resources): array {
    return array_merge([
        'posts' => App\Models\Post::class,
    ], $resources);
});
```

Filter contributions are merged with `config('artisanpack.visual-editor.resources')`. **Static config wins on key collision** — host-app overrides always take precedence over package contributions.

Models must use `ArtisanPackUI\VisualEditor\Concerns\HasBlockContent`. Invalid entries surface as `InvalidArgumentException` on first request, not at boot — contributor packages standalone never trip host boot.

See [[Content Model#2-the-resource-map]] for the full contract.

---

### `ap.visualEditor.templates` / `templateParts` / `patterns` / `navigation`

Register site-editor entities at runtime. Each filter slug merges into the matching `config('artisanpack.visual-editor.site-editor.*')` array.

**Signature:** `array $entries -> array`

```php
addFilter('ap.visualEditor.templates', function (array $templates): array {
    return array_merge([
        'single' => [
            'slug'    => 'single',
            'title'   => 'Single Post',
            'content' => ['raw' => '...', 'blocks' => [...]],
            'source'  => 'theme',
        ],
    ], $templates);
});
```

Static config wins on key collision. cms-framework uses these filters to expose its `Template`, `TemplatePart`, `Pattern`, and `Menu` models to the editor.

Entity shape contracts are documented in `config/visual-editor.php` and [[Configuration#site-editor]].

---

### `ap.visualEditor.loginout.envelope`

Rewrite the resolved envelope emitted by the `artisanpack/loginout` block before render. Useful for swapping in `URL::signedRoute()`, per-tenant routes, or SSO redirects.

**Signature:** `array $envelope, array $context -> array`

```php
addFilter('ap.visualEditor.loginout.envelope', function (array $envelope, array $context): array {
    if ($context['action'] === 'logout') {
        $envelope['url'] = URL::signedRoute('logout.get');
    }
    return $envelope;
}, 10, 2);
```

---

### `ap.icons.registerIconSets`

The editor chrome resolves icons through `artisanpack-ui/icons`. Register additional icon sets in a service provider.

```php
addFilter('ap.icons.registerIconSets', function (IconSetRegistration $registry) {
    $registry->addSet(__DIR__ . '/../../resources/icons', 'mypackage');
    return $registry;
});
```

See the [`artisanpack-ui/icons`](https://github.com/ArtisanPack-UI/icons) docs for the full contract.

---

### `ap.visualEditor.renderedBlock`

Last-mile PHP filter applied to the rendered HTML of every block (static or dynamic) inside `packages/visual-editor-renderer-blade`'s `BlockRenderer::renderBlock()`. Runs on the server, at render-time, on every request that emits post content — not at save-time and not in JavaScript. Callbacks decorate output without each host having to fork the renderer.

**Signature:** `string $html, string $blockName, array $attributes -> string`

```php
addFilter('ap.visualEditor.renderedBlock', function (string $html, string $name, array $attributes): string {
    if ('artisanpack/group' !== $name) {
        return $html;
    }
    // …wrap, sanitize, inject data-* attributes, etc.
    return $html;
}, 10, 3);
```

**Recursion:** the renderer walks inner blocks through the same code path, so the filter fires once per block **at every level** of the tree. A callback that wraps a container block will also wrap every descendant unless it gates on `$name` / `$attributes`.

**Attributes shape:** `$attributes` is post-normalization and may already carry package-internal `_resolved*` side-channel keys (site-meta, loginout). Treat those as read-only; they're not stable public attributes.

---

### `ap.visualEditor.beforeRender`

PHP filter applied inside `BlockRenderer::renderBlock()` **before** a block renders, giving hosts a chance to mutate the block's attributes. Fires after site-meta / loginout stamping so the resolved `_resolved*` keys are already present. Sibling of `rendered-block` (which runs after render).

**Signature:** `array $attributes, string $blockName -> array`

```php
addFilter('ap.visualEditor.beforeRender', function (array $attributes, string $name): array {
    if ('core/paragraph' !== $name) {
        return $attributes;
    }
    $attributes['content'] = strtoupper($attributes['content'] ?? '');
    return $attributes;
}, 10, 2);
```

Non-array returns are ignored so a misbehaving callback can't blank the block.

---

### `ap.visualEditor.blockRegistered`

PHP action fired at the end of block-type registration, from `BlockTypeRegistry::register()`. Covers both `block.json`-driven registration and programmatic `VisualEditor::registerBlockType()` calls.

**Signature:** `string $name, array $config -> void`

```php
addAction('ap.visualEditor.blockRegistered', function (string $name, array $config): void {
    // e.g. register block variations, per-block binding sources,
    // per-block category assignment, etc.
}, 10, 2);
```

---

### `ap.visualEditor.postSaved` / `ap.visualEditor.postPublished`

PHP actions fired by `WpEntityController` after every POST/PUT persistence (`postSaved`) and additionally when the current save transitions the record's `status` into the WP-canonical `publish` value (`postPublished`). Fire on both create-with-publish and non-publish → publish updates; re-saves of an already-published record fire `postSaved` only.

**Signature:** `int|string $postId, array $blocks -> void`

```php
addAction('ap.visualEditor.postPublished', function ($postId, array $blocks): void {
    // e.g. queue a search-index rebuild, send a webhook, purge a CDN, …
}, 10, 2);
```

`$blocks` reflects the tree the client submitted in the current request under `content.blocks`; missing / malformed payloads collapse to `[]`.

---

### `ap.visualEditor.editorConfig`

PHP filter applied by `VisualEditorComponent` on the assembled editor config before the Blade template emits its `data-*` attributes. `$screen` identifies the surface — `'post'` for the current component; a future site-editor surface would pass `'site'`.

**Signature:** `array $config, string $screen -> array`

```php
addFilter('ap.visualEditor.editorConfig', function (array $config, string $screen): array {
    if ('post' !== $screen) {
        return $config;
    }
    $config['apiBase'] = '/custom/api';
    return $config;
}, 10, 2);
```

Only known keys are re-hydrated back onto the component's typed props — extra keys are ignored and non-array returns leave the assembled config intact.

---

### `ap.visualEditor.patternRender`

PHP filter applied by `PatternAdapter::toArray()` on the rendered raw content of a pattern before it ships to the editor / consumer. Runs on every read.

**Signature:** `string $html, string $slug, array $context -> string`

```php
addFilter('ap.visualEditor.patternRender', function (string $html, string $slug, array $context): string {
    // $context carries: source, synced, categories, block_types, post_types
    if ('user' !== $context['source']) {
        return $html;
    }
    return str_replace('{{year}}', (string) date('Y'), $html);
}, 10, 3);
```

Non-string returns are ignored so the underlying `rawContent` survives.

---

## JavaScript filters

Editor-side filters run through `@wordpress/hooks` inside the browser. Register callbacks with `addFilter(name, namespace, callback)` at editor bootstrap (the moment the app imports your package's entry module is enough — the editor evaluates each filter on every relevant render).

### `ap.visualEditor.backgroundControls`

Contribute panels to the shared background / appearance area of any block that opts into a background support (`supports.background` for image / gradient backgrounds, or `supports.color.background` for the color background). Applied by the built-in `editor.BlockEdit` HOC so external packages don't have to enumerate target blocks or wrap `BlockEdit` themselves — the target-block decision lives in the editor.

**Signature:** `BackgroundControl[] -> BackgroundControl[]`

```ts
type BackgroundControl = {
    id: string           // stable, namespaced (e.g. 'liquid-glass')
    label: string        // panel heading (translate before returning)
    priority?: number    // sort key, default 10, lower first
    render: () => ReactNode
}

type BackgroundControlContext = {
    // Frozen — call setAttributes to modify.
    attributes: Readonly<Record<string, unknown>>
    setAttributes: (attrs: Record<string, unknown>) => void
    clientId: string
    blockName: string
    // Deep-cloned from the block-type registry — treat as read-only.
    blockSupports: Readonly<Record<string, unknown>>
}
```

**Example:**

```ts
import { addFilter } from '@wordpress/hooks'

addFilter(
    'ap.visualEditor.backgroundControls',
    'artisanpack-ui/liquid-glass',
    (controls, { attributes, setAttributes, blockSupports }) => {
        // The HOC gates on either `supports.background` (image / gradient
        // background) or `supports.color.background` (color background),
        // so a block with only text-color support won't fire this filter.
        // Narrow further to the shape your control cares about:
        const hasBackground =
            Boolean(blockSupports.background) ||
            (typeof blockSupports.color === 'object' &&
                blockSupports.color !== null &&
                (blockSupports.color as Record<string, unknown>).background !==
                    false)

        if (! hasBackground) {
            return controls
        }

        return [
            ...controls,
            {
                id: 'liquid-glass',
                label: 'Liquid Glass',
                priority: 20,
                render: () => (
                    <LiquidGlassPanel
                        value={attributes.liquidGlass}
                        onChange={(liquidGlass) => setAttributes({ liquidGlass })}
                    />
                ),
            },
        ]
    },
)
```

Controls are deduped by `id` **before** sorting — a later `addFilter` at the same id overrides an earlier one regardless of priority, mirroring how `@wordpress/hooks` composes filters. Surviving controls are then sorted by `priority` (default `10`, lower first) with ties falling back to registration order.

If a filter callback throws, the exception is caught, logged to `console.error`, and the block renders without any contributed panels for that render — one buggy third-party filter can't trip Gutenberg's per-block crash boundary. Callbacks with a non-numeric `priority` are silently rejected.

Attributes are declared the standard Gutenberg way (`blocks.registerBlockType` filter). For static blocks, save-side rendering continues to go through `blocks.getSaveContent.extraProps`; for dynamic blocks, use the block's PHP render or the `ap.visualEditor.renderedBlock` PHP filter (see the PHP filters section above).

---

### `ap.visualEditor.documentPanels`

Contribute panels to the Document tab of the inspector sidebar. Runs against an empty descriptor list at inspector render time; the return value replaces it.

**Signature:** `DocumentPanelSpec[] -> DocumentPanelSpec[]`

```ts
type DocumentPanelSpec = {
    id: string
    title: string
    initialOpen?: boolean
    order?: number       // sort key, default 100, lower first
    render: () => ReactNode
}
```

Descriptors are sorted by `order` (default `100`, lower first) with ties falling back to registration order and deduped by `id` (last-wins). The slot-fill companion `<PluginDocumentSettingPanel>` mirrors the WordPress component of the same name for panels rendered as part of the editor tree instead of registered at bootstrap.

---

### `ap.visualEditor.canvasStyles`

Contribute additional stylesheets injected into the block canvas iframe. Applied once at module-load time inside the editor bundle and frozen for the session — register your filter before importing the editor entry module.

**Signature:** `CanvasStyle[] -> CanvasStyle[]` where `CanvasStyle = { css: string }`.

Entries whose `css` isn't a string are dropped. Non-array return values fall back to the built-in list.

---

## Browser events

The editor dispatches three `CustomEvent`s on `window` whenever content state changes.

| Event | When | `detail` shape |
|-------|------|----------------|
| `ve:editor:change` | Debounce window closes, right before autosave fires. | `{ resource, id, blocks }` |
| `ve:editor:autosave` | Debounce-triggered save returns 200. | `{ resource, id, blocks, updatedAt }` |
| `ve:editor:save` | Explicit save (⌘S or topbar) returns 200. | `{ resource, id, blocks, updatedAt }` |

`resource` and `id` match the Blade component's `data-resource` / `data-id` attributes, so a single listener can disambiguate multiple editors on the same page.

### Listening from JavaScript

```js
window.addEventListener('ve:editor:autosave', (event) => {
    const { resource, id, blocks, updatedAt } = event.detail;
    console.log(`Autosaved ${resource}/${id} at ${updatedAt}`);
});
```

### Listening from Alpine / Livewire

```blade
<div
    wire:ignore
    @ve:editor:change.window="$wire.set('dirty', true)"
    @ve:editor:autosave.window="$wire.handleAutosaved($event.detail)"
    @ve:editor:save.window="$wire.handleSaved($event.detail)"
>
    <x-visual-editor :model="$post" />
</div>
```

See [[post-editor/Livewire Integration]] for the full Livewire recipe and [[post-editor/Inertia Integration]] for the Inertia equivalent.

---

### `apve_query_variant_match_<name>`

Resolve a `custom`-kind matcher on an `artisanpack/post-variant` block (see [[blocks/Post Variants]]). The filter name is composed from the variant's `matcher.value` of the form `callback:<name>`.

**Signature:** `bool $matches, object $post, array $context -> bool`

```php
use function ArtisanPackUI\Hooks\addFilter;

addFilter('apve_query_variant_match_premium', function (bool $matches, object $post, array $context): bool {
    // $context => ['index' => int (0-based loop position), 'total' => int]
    return true === ($post->is_premium ?? false);
}, 10, 3);
```

Return `true` to make the variant match the current post. Variant precedence is fixed (instance > position > pattern > meta > custom > base) so a callback can be overridden by a higher-tier rule.

---

## Authorization gates

The site editor's access surface is controlled by a single boot-time contract:

```php
namespace ArtisanPackUI\VisualEditor\SiteEditor\Gates;

interface SiteEditorAccessGate
{
    public function check(Request $request): ?Response;
}
```

Return `null` to allow; return a `Response` to short-circuit. Bind a custom implementation in `AppServiceProvider::register()`:

```php
$this->app->bind(SiteEditorAccessGate::class, App\SiteEditor\MyGate::class);
```

See [[site-editor/Access Gate]] for the contract and bundled implementations.

---

## See also

- [[Configuration]] — All configuration keys, including the filter-merging behaviour
- [[Content Model]] — How the resource filter integrates with the resource map
- [[post-editor/Blade Component]] — Browser event contract
- [`artisanpack-ui/hooks`](https://github.com/ArtisanPack-UI/hooks) — The underlying actions / filters helper library
