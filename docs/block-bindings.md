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

`post_core`, `custom_field`, and `relation` only read what the model
would serialize: never a key in `$hidden`, and only keys in `$visible`
when that list is non-empty. A path such as `author.password` resolves
to empty and the binding's empty-value policy applies.

With the opt-in [attribute allowlist](#attribute-allowlist) enforced,
`relation` may only read allowlisted attributes of the records it walks
through, and none of the three model sources will publish a whole
record. That covers a `custom_field` or `post_core` value (such as a
`featured_image` relation) that holds a model anywhere inside an array
or collection.

Bindings never call arbitrary model methods. A `custom_field` key or a
`relation` path segment that names a method only resolves when that
method declares an Eloquent `Relation` return type, such as
`public function author(): BelongsTo`. Stored attributes, accessors, and
relations that are already loaded aren't affected. A relation method
without a return type resolves to empty, so add one if you bind through
it.

### Which custom fields a content type offers

*Since v1.13.0 (#837).* The `custom_field` source's field catalog
matches cms-framework's `custom_fields.content_types` against the
**resource slug** (the key in the resource map, such as `pages` or
`events`), with the model's table name as a fallback. Resources are
looked up through the container-bound `ResourceResolver`, so content
types registered through the `ap.visualEditor.resources` filter (as
cms-framework and Keystone do) list their fields too, not only those in
static config. Fields registered through cms-framework's
`CustomFieldManager` are included. A slug that isn't a registered
resource gets no fields, so the endpoint can't list the fields of
content types the editor doesn't expose.

### Binding an icon to a custom field

*Since v1.14.1.* `artisanpack/icon`'s `iconRef` attribute accepts the
`{"set": "fas", "name": "cube"}` shape as an array, a JSON string, a
`stdClass` or an `ArrayObject`. A custom field can therefore store the
iconRef in a `text` column without a cast, or cast it with `object` /
`AsArrayObject`. The `set` and `name` are checked against the icon
registry's character sets on both the server and in the editor preview;
anything else renders the placeholder.

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

## Attribute allowlist

*Since v1.15.0 (#869). Opt-in.* By default a binding may read any
attribute a model doesn't keep in `$hidden` (or leave out of a
non-empty `$visible`). That includes related records: a `relation`
binding to `author.email` renders the author's email on the public
front end unless the `User` model hides it.

Turn on enforcement to deny by default:

```dotenv
VE_BINDINGS_ENFORCE_ALLOWLIST=true
```

or set `bindings.enforce_allowlist` to `true` in the config (see
[Configuration](./Configuration.md#bindings)). With enforcement on:

- A `relation` binding may read an attribute of a related model only
  when that model's allowlist names it. The parent record's own
  attributes (its custom fields and post core fields) aren't gated.
- A binding value can't be a whole record. `relation`, `custom_field`
  and `post_core` resolve to empty (so the binding's
  [empty-value policy](#empty-value-policy) applies) when the value is a
  model, or an array or collection that holds one at any depth. A path
  such as `author` that ends on the related record itself is refused
  the same way.
- The field picker hides the `post_core` **Author name** field
  (`author_name`) unless the model's `author` relation points at a model
  that allows `name`, so authors aren't offered a field that would
  always render empty.

An attribute is allowlisted when any of these lists it. The lists are
merged in this order, and the filter sees the merged result:

1. **The model itself**, by implementing `DefinesBindableAttributes`:

   ```php
   use ArtisanPackUI\VisualEditor\Services\Bindings\DefinesBindableAttributes;

   class User extends Authenticatable implements DefinesBindableAttributes
   {
       public function bindableAttributes(): array
       {
           return [ 'name', 'website' ];
       }
   }
   ```

2. **Config**, keyed by model class:

   ```php
   'bindings' => [
       'enforce_allowlist'  => env( 'VE_BINDINGS_ENFORCE_ALLOWLIST', false ),
       'allowed_attributes' => [
           App\Models\User::class => [ 'name', 'website' ],
       ],
   ],
   ```

3. **The `ap.visualEditor.bindings.allowedAttributes` filter**, which
   receives the merged list, the related model and the full binding
   path. See [Hooks and Events](./Hooks-and-Events.md#apvisualeditorbindingsallowedattributes).

An entry of `'*'` allows every attribute on that model. A relation
segment counts as an attribute, so to bind `author.team.name` the
author's model must allow `team` and the team's model must allow `name`.

Enforcement is off by default in 1.x so existing relation bindings keep
working, and is planned to become the default in 2.0. Until you turn it
on, keep sensitive columns such as `email` in your `User` model's
`$hidden`, which every binding source honors.

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
- `GET /visual-editor/api/bindings/sources/{source}/fields?resource=…` — field catalog for a source scoped to a resource (`resource` is the resource slug; see [above](#which-custom-fields-a-content-type-offers)).
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

### Bound attributes are locked

*Since v1.15.3 (#995).* The editor overlays a bound attribute's resolved
value on the block, so typing into its control would be overwritten.
Controls for a bound attribute are disabled instead:

- In server-rendered blocks' inspectors, the control renders disabled
  with help text naming the source: "Connected to {source} → {field}.
  Disconnect it in the Block bindings panel to edit."
- A Heading whose `content` is bound renders it read-only
  (`aria-readonly="true"`).

Remove the binding in the Bindings panel to edit the attribute again.
Other hand-written blocks (Paragraph, Button, Image, Quote and others)
don't lock their bound fields yet.

Custom server-rendered blocks get this automatically: their inspector
controls are generated from the block's attributes, so a bound attribute's
control is disabled the same way.

### Record access check

*Since v1.15.0.* `POST /visual-editor/api/bindings/resolve` and
`POST /visual-editor/api/blocks/preview` resolve the record named by the
request's `context.resource` and `context.id`. When the host registers a
policy for that record's model, the current user now needs its `view`
ability on the record, or the endpoint answers `403`. Models without a
policy behave as before: any user who passes the API middleware can
preview them.

So editors who can open the editor but lack `view` on a record get a
`403` from the bindings preview and block previews for that record. The
bindings overlay then falls back to the block's static values. The front-end render
isn't affected: it resolves against the record the host passes in.

## SSR

The Blade renderer (`packages/visual-editor-renderer-blade`) resolves
bindings once at the top of `BlockRenderer::render()` via
`resolveBindings()`. Trees without a `bindings` sidecar round-trip
byte-identically. When the bindings layer is not bound in the
container (renderer installed standalone), the pass silently no-ops.

`<x-ve-blocks :tree="$tree" :post="$post" />` resolves bindings against
`$post` when it is an Eloquent model. Without a `:post`, or with a
non-model one, model-dependent bindings (`post_core`, `custom_field`,
`relation`) take their empty-value policy; `dynamic_content` still
resolves, since it doesn't need a parent record. Nested
renders inside the same tree (tabs, accordions, the navigation
overlay) reuse the same record. Hosts calling the renderer directly
pass the record themselves:

```php
use ArtisanPackUI\VisualEditor\Services\Bindings\BindingContext;
use ArtisanPackUI\VisualEditorRendererBlade\BlockRenderer;

$renderer = app( BlockRenderer::class );
$html     = $renderer->render( $tree, new BindingContext( $post ) );
$assets   = $renderer->flushSupportAssets(); // print $assets['css'] before $html, $assets['scripts'] after
```

See [Support CSS for direct renders](./renderers.md#support-css-for-direct-renders)
for what `flushSupportAssets()` returns.

Inside a `core/query` loop, each `post-template` iteration resolves its
bindings against that iteration's post, not the host record. The same
goes for `artisanpack/related-posts`, `artisanpack/single-content` with a
selected entry, and the `artisanpack/next-post` / `artisanpack/previous-post`
containers, which resolve against the post they show. When the query
runtime returns plain objects instead of Eloquent models, model-dependent
bindings in those iterations take their empty-value policy. Relations
that `relation` bindings need are loaded once across the whole result
set.

## Naming note

Historically these docs described bindings as stored under
`metadata.bindings`, mirroring Gutenberg's convention. The active
implementation (both the React HOC and the PHP resolver) stores them
as a top-level `bindings` sidecar, which is what the JSON above
shows. If you're migrating from a pre-1.4 draft that used
`metadata.bindings`, rewrite once — the resolver only reads the top-level
key.
