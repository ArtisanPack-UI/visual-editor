# Hooks and Events

The visual editor exposes extension points through three mechanisms:

- **PHP filters** — `applyFilters('ap.visual-editor.*', ...)` for value transformation (resource map, site-editor entities, etc.)
- **PHP actions** — `doAction('ap.visual-editor.*', ...)` for side effects
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
