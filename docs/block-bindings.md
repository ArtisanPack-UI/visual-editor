# Block Bindings

Status: **v1.1.0** — Issue [#504](https://github.com/ArtisanPack-UI/visual-editor/issues/504).

Block bindings let any block attribute pull its value from the
surrounding post, page, or CPT record at render time. Authors keep
editing the block once; the binding resolver substitutes the live
value on every render and the editor shows the resolved preview
inline.

The system covers three first-party sources out of the box and exposes
a small contract so host applications and packages can plug in their
own.

## Built-in sources

| Source        | Identifier      | What it binds to                                    |
|---------------|-----------------|-----------------------------------------------------|
| Custom fields | `custom_field`  | A cms-framework `CustomField` on the record.         |
| Post core     | `post_core`     | A column on the post/page/CPT (title, excerpt, …).   |
| Relation      | `relation`      | A dotted path through a related record.              |

`post_core` and `custom_field` resolve against whichever record the
current render is scoped to — typically a `Post`, `Page`, or CPT
instance bound through cms-framework. `relation` walks
`relation.field` paths so a block can bind to, for example, the
author's display name (`author.name`).

## Binding shape

A binding is stored on the block under the standard `metadata.bindings`
map. Each entry maps an attribute name to a source + args pair:

```json
{
    "metadata": {
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
}
```

The resolver replaces the bound attribute value on the server, and the
editor inspector surfaces a picker so authors can pick a source +
field per attribute without hand-editing JSON.

## Empty-value policy

When a binding resolves to `null` or an empty string, the renderer
honors the binding's `onEmpty` policy:

- `keep` — leave the authored attribute value in place (default).
- `hide` — drop the block from the rendered output.
- `placeholder` — render the authored placeholder text.

This is the same policy the editor uses to preview a binding when the
record is missing the underlying field.

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
field or path. The picker calls
`GET /visual-editor/binding-sources` for the available sources, and
`POST /visual-editor/binding-resolve` to preview the resolved value
against the currently-edited record.

Both endpoints are first-party controllers (`BindingSourcesController`
and `BindingResolveController`) and respect the package's standard
authorization gates.
