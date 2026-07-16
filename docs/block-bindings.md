# Block Bindings

Status: **v1.1.0** — Issue [#504](https://github.com/ArtisanPack-UI/visual-editor/issues/504). Extended in **v1.4** (Issue [#650](https://github.com/ArtisanPack-UI/visual-editor/issues/650)) with the `dynamic_content` source.

Block bindings let any block attribute pull its value from the
surrounding post, page, or CPT record — or from cms-framework's
Dynamic Content module — at render time. Authors keep editing the
block once; the binding resolver substitutes the live value on every
render and the editor shows the resolved preview inline.

The system covers four first-party sources out of the box and exposes
a small contract so host applications and packages can plug in their
own.

## Built-in sources

| Source          | Identifier         | What it binds to                                    |
|-----------------|--------------------|-----------------------------------------------------|
| Custom fields   | `custom_field`     | A cms-framework `CustomField` on the record.         |
| Post core       | `post_core`        | A column on the post/page/CPT (title, excerpt, …).   |
| Relation        | `relation`         | A dotted path through a related record.              |
| Dynamic Content | `dynamic_content`  | A cms-framework Dynamic Content token. See [dynamic-content.md](./dynamic-content.md). |

`post_core` and `custom_field` resolve against whichever record the
current render is scoped to — typically a `Post`, `Page`, or CPT
instance bound through cms-framework. `relation` walks
`relation.field` paths. `dynamic_content` resolves tokens like
`business_info.phone` through cms-framework's `DynamicContentAccessor`
and is independent of the render's parent record.

## Binding shape

A binding is stored on the block as a top-level `bindings` sidecar
(not under `metadata` — see [naming note](#naming-note)). Each entry
maps an attribute name to a source + args pair:

```json
{
    "name": "artisanpack/paragraph",
    "attrs": { "content": "" },
    "bindings": {
        "content": {
            "source": "post_core",
            "args": { "field": "title" }
        },
        "url": {
            "source": "relation",
            "args": { "path": "author.website" }
        }
    }
}
```

For Dynamic Content:

```json
{
    "bindings": {
        "url": {
            "source": "dynamic_content",
            "args": { "token": "business_info.logo" }
        }
    }
}
```

The resolver replaces the bound attribute value on the server, and the
editor inspector surfaces a picker so authors can pick a source +
field per attribute without hand-editing JSON.

## Empty-value policy

When a binding resolves to `null`, `''`, or `[]`, the renderer honors
the binding's `onEmpty` policy:

- `fallback` — leave the authored attribute value in place (default).
- `hide` — drop the block from the rendered output.
- `placeholder` — render the binding's `placeholder` string.

This is the same policy the editor uses to preview a binding when the
record is missing the underlying field.

`0`, `0.0`, and `false` are **not** empty — they're legitimate values
and pass through unchanged.

## Registering a custom source

A binding source is any class that implements
`\ArtisanPackUI\VisualEditor\Services\Bindings\BlockBindingSource`.
Register it from a service provider's `boot()` against
`BlockBindingSourceRegistry`:

```php
use ArtisanPackUI\VisualEditor\Registries\BlockBindingSourceRegistry;
use App\Bindings\SiteSettingSource;

public function boot(): void
{
    $this->app->resolving(
        BlockBindingSourceRegistry::class,
        function ( BlockBindingSourceRegistry $registry ): void {
            $registry->register( new SiteSettingSource() );
        }
    );
}
```

The source's `name()` is the identifier that appears in the block's
`bindings` map and in the editor's source picker. Names must match
`/^[a-z][a-z0-9_]*$/` so they survive JSON round-trips and JS object
keys. Re-registering a name overwrites the previous driver, which is
the intentional escape hatch for replacing a built-in source.

## Editor surface

The inspector adds a **Bindings** panel to every supported block.
Authors pick an attribute, a source, and (depending on the source) a
field or path. The picker calls:

- `GET /visual-editor/api/bindings/sources` — list registered sources.
- `GET /visual-editor/api/bindings/sources/{source}/fields?resource=…` — field catalog for a source scoped to a resource.
- `POST /visual-editor/api/bindings/resolve` — preview the resolved
  value against the currently-edited record.

Dynamic Content is an exception because tokens are host-level, not
record-scoped — it uses:

- `GET /visual-editor/api/dynamic-content/sources` — merged code + DB DC universe.
- `POST /visual-editor/api/dynamic-content/resolve` — batched token → value map.

Both endpoints are first-party controllers (`BindingSourcesController`,
`BindingResolveController`, `DynamicContentSourcesController`,
`DynamicContentResolveController`) and respect the package's standard
authorization gates.

## SSR

The Blade renderer (`packages/visual-editor-renderer-blade`) resolves
bindings once at the top of `BlockRenderer::render()` via
`resolveBindings()`. Trees without a `bindings` sidecar round-trip
byte-identically. When the bindings layer is not bound in the
container (renderer installed standalone), the pass silently no-ops.

## Naming note

Historically these docs described bindings as stored under
`metadata.bindings`, mirroring Gutenberg's convention. The active
implementation (both the React HOC and the PHP resolver) stores them
as a top-level `bindings` sidecar, which is what the JSON above
shows. If you're migrating from a pre-1.4 draft that used
`metadata.bindings`, rewrite once — the resolver only reads the top-level
key.
