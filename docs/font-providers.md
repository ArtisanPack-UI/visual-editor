# Font Providers

Status: **v1.7.0** — Issue [#629](https://github.com/ArtisanPack-UI/visual-editor/issues/629)

A **font provider** is a source the Font Library can browse and install fonts from — Google Fonts, Bunny Fonts, custom uploads, or any third-party catalog. Providers implement the [`FontProvider`](../src/Fonts/Contracts/FontProvider.php) contract and are collected in the [`FontSourceRegistry`](../src/Fonts/Registries/FontSourceRegistry.php). A package registers its own source through the `ap.visualEditor.registerFontSources` filter with no core changes.

> This page documents the extensibility layer only (the contract, the registry, and the filter). Installation, storage, `fonts.css` generation, and the Font Library modal are covered in [[Fonts]] once those pieces land.

---

## The `FontProvider` contract

`ArtisanPackUI\VisualEditor\Fonts\Contracts\FontProvider`

| Method | Returns | Purpose |
|--------|---------|---------|
| `key()` | `string` | Stable registry slug — persisted as a font's `provider` column. Must match `/^[a-z][a-z0-9_-]*$/` (e.g. `google`, `bunny`, `custom`). |
| `label()` | `string` | Human-readable name shown in the modal. Wrap with `__()` so it localizes. |
| `isSelfHostable()` | `bool` | Whether faces can be fetched and served locally. Remote-only sources return `false` and the installer refuses them in v1. |
| `searchCatalog(string $query, int $page = 1)` | `array` | A page of catalog results. An empty `$query` browses the default listing. |
| `getFamily(string $slug)` | `?array` | One family and its installable faces, or `null` when the slug is unknown. |
| `fetchFace(string $slug, string $weight, string $style)` | `string` | Raw font-file bytes (typically WOFF2) for a single face, written to the configured disk by the installer. |

### Payload shapes

`searchCatalog()` returns a page of family summaries:

```php
[
    'families' => [
        ['slug' => 'inter', 'family' => 'Inter', 'category' => 'sans-serif'],
        // …
    ],
    'page'     => 1,
    'has_more' => true,
]
```

`getFamily()` returns a single family with its faces:

```php
[
    'slug'        => 'inter',
    'family'      => 'Inter',
    'is_variable' => false,
    'faces'       => [
        ['weight' => '400', 'style' => 'normal'],
        ['weight' => '700', 'style' => 'normal'],
    ],
    // Variable fonts may add an 'axes' map.
]
```

---

## The `FontSourceRegistry`

`ArtisanPackUI\VisualEditor\Fonts\Registries\FontSourceRegistry`

An in-memory store of providers keyed by `FontProvider::key()`, following the same conventions as [`BlockTypeRegistry`](../src/Registries/BlockTypeRegistry.php).

| Method | Signature | Notes |
|--------|-----------|-------|
| `register` | `register(FontProvider $provider): void` | Keys by `$provider->key()`. Re-registering a key replaces the previous provider (last write wins). Throws `InvalidArgumentException` on an empty or malformed key. |
| `get` | `get(string $key): ?FontProvider` | `null` when nothing is registered. |
| `has` | `has(string $key): bool` | |
| `unregister` | `unregister(string $key): void` | No-op when the key is absent. |
| `all` | `all(): array<string, FontProvider>` | Every provider, keyed by key. |

The registry is bound as a singleton in the package service provider.

---

## Registering a custom source

Implement `FontProvider`, then register it from your service provider's `boot()` by hooking the filter. The filter receives the registry and must return it:

```php
use ArtisanPackUI\VisualEditor\Fonts\Registries\FontSourceRegistry;

addFilter(
    'ap.visualEditor.registerFontSources',
    function ( FontSourceRegistry $registry ): FontSourceRegistry {
        $registry->register( new FontshareProvider() );

        return $registry;
    }
);
```

The filter is layered over the registry with `extend()`, so it fires the first time the registry is resolved — after every provider has booted. Provider order does not matter: a source registered from any package's `boot()` is visible.

A non-`FontSourceRegistry` return value is ignored, so a misbehaving hook cannot break font sources.

> **Hook naming.** The canonical hook name is camelCase (`ap.visualEditor.registerFontSources`), matching the ecosystem convention adopted in [v1.5.0 (#664)](Hooks-and-Events.md). See [[Hooks and Events]] for the full hook reference.
