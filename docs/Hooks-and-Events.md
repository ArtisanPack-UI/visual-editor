# Hooks and Events

The visual editor exposes extension points through four mechanisms:

- **PHP filters** — `applyFilters('ap.visual-editor.*', ...)` for value transformation (resource map, site-editor entities, etc.)
- **PHP actions** — `doAction('ap.visual-editor.*', ...)` for side effects
- **JavaScript filters** — `addFilter('ap.visual-editor.*', ...)` via `@wordpress/hooks` for editor-side extension (document panels, background controls, canvas styles, rendered blocks)
- **Browser events** — `CustomEvent` dispatched on `window` for client-side integration (autosave, change, save)

All PHP hooks use the global helpers from [`artisanpack-ui/hooks`](https://github.com/ArtisanPack-UI/hooks). All browser events are plain `CustomEvent` instances dispatched on `window`.

---

## PHP filters

### `ap.visual-editor.resources`

Register slug → Eloquent model class mappings used by `/visual-editor/api/{resource}/{id}/content`.

**Signature:** `array $resources -> array`

```php
addFilter('ap.visual-editor.resources', function (array $resources): array {
    return array_merge([
        'posts' => App\Models\Post::class,
    ], $resources);
});
```

Filter contributions are merged with `config('artisanpack.visual-editor.resources')`. **Static config wins on key collision** — host-app overrides always take precedence over package contributions.

Models must use `ArtisanPackUI\VisualEditor\Concerns\HasBlockContent`. Invalid entries surface as `InvalidArgumentException` on first request, not at boot — contributor packages standalone never trip host boot.

See [[Content Model#2-the-resource-map]] for the full contract.

---

### `ap.visual-editor.templates` / `template-parts` / `patterns` / `navigation`

Register site-editor entities at runtime. Each filter slug merges into the matching `config('artisanpack.visual-editor.site-editor.*')` array.

**Signature:** `array $entries -> array`

```php
addFilter('ap.visual-editor.templates', function (array $templates): array {
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

### `ap.visual-editor.loginout.envelope`

Rewrite the resolved envelope emitted by the `artisanpack/loginout` block before render. Useful for swapping in `URL::signedRoute()`, per-tenant routes, or SSO redirects.

**Signature:** `array $envelope, array $context -> array`

```php
addFilter('ap.visual-editor.loginout.envelope', function (array $envelope, array $context): array {
    if ($context['action'] === 'logout') {
        $envelope['url'] = URL::signedRoute('logout.get');
    }
    return $envelope;
}, 10, 2);
```

---

### `ap.icons.register-icon-sets`

The editor chrome resolves icons through `artisanpack-ui/icons`. Register additional icon sets in a service provider.

```php
addFilter('ap.icons.register-icon-sets', function (IconSetRegistration $registry) {
    $registry->addSet(__DIR__ . '/../../resources/icons', 'mypackage');
    return $registry;
});
```

See the [`artisanpack-ui/icons`](https://github.com/ArtisanPack-UI/icons) docs for the full contract.

---

## JavaScript filters

Editor-side filters run through `@wordpress/hooks` inside the browser. Register callbacks with `addFilter(name, namespace, callback)` at editor bootstrap (the moment the app imports your package's entry module is enough — the editor evaluates each filter on every relevant render).

### `ap.visual-editor.background-controls`

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
    attributes: Record<string, unknown>
    setAttributes: (attrs: Record<string, unknown>) => void
    clientId: string
    blockName: string
    blockSupports: Record<string, unknown>
}
```

**Example:**

```ts
import { addFilter } from '@wordpress/hooks'

addFilter(
    'ap.visual-editor.background-controls',
    'artisanpack-ui/liquid-glass',
    (controls, { attributes, setAttributes, blockSupports }) => {
        // Only contribute when the block actually has a background support.
        if (! blockSupports.background) {
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

Controls are sorted by `priority` (default `10`, lower first) with ties falling back to registration order. Duplicate ids are deduped last-wins, mirroring how `@wordpress/hooks` composes filters at the same priority.

Attributes are declared the standard Gutenberg way (`blocks.registerBlockType` filter). For static blocks, save-side rendering continues to go through `blocks.getSaveContent.extraProps`; for dynamic blocks, use the block's PHP render or the `ap.visual-editor.rendered-block` filter.

---

### `ap.visual-editor.document-panels`

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

### `ap.visual-editor.canvas-styles`

Contribute additional stylesheets injected into the block canvas iframe. Applied once at module-load time inside the editor bundle and frozen for the session — register your filter before importing the editor entry module.

**Signature:** `CanvasStyle[] -> CanvasStyle[]` where `CanvasStyle = { css: string }`.

Entries whose `css` isn't a string are dropped. Non-array return values fall back to the built-in list.

---

### `ap.visual-editor.rendered-block`

Transform the HTML the editor emits for each block at save time. Runs after the block's own save function; the return value is the string written to the post content.

**Signature:** `string html, { block, blockName, attributes } -> string`

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
