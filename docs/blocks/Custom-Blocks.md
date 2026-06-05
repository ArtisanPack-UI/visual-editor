# Custom blocks

**Status:** V1 · shipped via issue #346 (A4)
**Reference block:** [`artisanpack/callout`](../../resources/js/visual-editor/blocks/callout)

Host apps and packages register their own blocks under the `artisanpack/*`
namespace (or any other custom namespace) by dropping files into a
conventional directory and calling one of three PHP registration methods.
The package does the rest: auto-discovery on the JS side, allow-list
filtering on the PHP side, and rendering on the public frontend through
the three renderer packages (Blade, React, Vue).

This document is the authoring pattern. Forking core blocks is *not*
covered here — that's tracked separately under issue #331 and lands in V2.

---

## 1. Directory layout

Custom blocks live under
`resources/js/visual-editor/blocks/{block-name}/` with this layout:

```text
resources/js/visual-editor/blocks/
└── callout/
    ├── block.json          ← metadata (Gutenberg schema)
    ├── edit.tsx            ← editor-side React component
    ├── save.tsx            ← (static blocks) persisted markup
    ├── index.ts            ← re-exports { metadata, edit, save }
    └── callout.css         ← optional editor styles
```

The auto-discovery glob keys off `index.ts`, so every block folder
**must** expose that entrypoint. Vite picks up any `import` statements in
`index.ts` — CSS, asset references, or additional helpers — as normal.

Dynamic blocks (server-rendered) may omit `save.tsx`; the PHP render
callback produces the public markup instead. See §4.

---

## 2. `block.json` schema

Every block is described by a Gutenberg-compatible `block.json`. The
editor calls `@wordpress/blocks.registerBlockType(name, settings)`
under the hood, so anything valid in the [WordPress block.json reference](https://developer.wordpress.org/block-editor/reference-guides/block-api/block-metadata/)
is valid here.

Minimum fields for the ArtisanPack pipeline:

| Field        | Purpose                                                                   |
|--------------|---------------------------------------------------------------------------|
| `name`       | `namespace/name` — lowercase, hyphens only (e.g. `artisanpack/callout`)   |
| `category`   | `artisanpack` for bundled/reference blocks; free choice for host apps     |
| `title`      | User-facing label shown in the inserter                                   |
| `attributes` | Block attributes (optionally typed/enumerated with `default` values)      |
| `supports`   | Which Gutenberg block supports to enable (alignment, color, spacing, …)   |

Example (abridged) from `artisanpack/callout`:

```json
{
    "$schema": "https://schemas.wp.org/trunk/block.json",
    "apiVersion": 3,
    "name": "artisanpack/callout",
    "title": "Callout",
    "category": "artisanpack",
    "attributes": {
        "severity": {
            "type": "string",
            "enum": ["info", "success", "warning", "error"],
            "default": "info"
        },
        "content": {
            "type": "rich-text",
            "source": "rich-text",
            "selector": "div.ap-callout__body",
            "default": ""
        }
    },
    "supports": {
        "anchor": true,
        "className": true,
        "spacing": { "margin": true, "padding": true }
    }
}
```

The `artisanpack` category is registered automatically by the editor; any
block whose `category` matches that slug shows up in the block library
sidebar under the ArtisanPack heading.

---

## 3. PHP registration

The PHP `VisualEditor::registerBlock()` method accepts any of three input
shapes. Pick whichever one matches where your metadata lives.

### 3.1 From a `block.json` path

```php
use ArtisanPackUI\VisualEditor\Facades\VisualEditor;

public function boot(): void
{
    VisualEditor::registerBlock(
        resource_path('js/visual-editor/blocks/callout/block.json')
    );
}
```

This is the canonical form — the same file powers both the JS auto-
registration and the PHP allow-list.

### 3.2 From a class

Implement `ProvidesBlockMetadata` to expose a static `blockMetadata()`
method returning the metadata array. Useful when the metadata is built
from config, enum definitions, or other PHP-side state:

```php
use ArtisanPackUI\VisualEditor\Blocks\ProvidesBlockMetadata;
use ArtisanPackUI\VisualEditor\Facades\VisualEditor;

class CalloutBlock implements ProvidesBlockMetadata
{
    public static function blockMetadata(): array
    {
        return [
            'name'       => 'artisanpack/callout',
            'title'      => __('Callout'),
            'category'   => 'artisanpack',
            'attributes' => config('callouts.attributes'),
        ];
    }
}

VisualEditor::registerBlock(CalloutBlock::class);
```

### 3.3 From a closure

The most flexible form — handy for quick iteration in a service provider
or for blocks assembled at boot time:

```php
VisualEditor::registerBlock(fn (): array => [
    'name'     => 'acme/notice',
    'title'    => __('Notice'),
    'category' => 'artisanpack',
]);
```

The closure must return an array; a non-array return throws
`InvalidArgumentException`.

### 3.4 Enabling the block

Registering a block makes it known to the PHP registry; whether the
editor exposes it in the inserter depends on the allow-list. Add the
fully-qualified block name to `config/artisanpack/visual-editor.php`:

```php
'enabled_blocks' => [
    // … core blocks …
    'artisanpack/callout',
    'acme/notice',
],
```

The bundled `artisanpack/callout` is already in the default allow-list.

---

## 4. Static vs dynamic — which do I need?

### Static blocks

The `edit.tsx` + `save.tsx` pair persists serialized HTML inside the
post content. Every renderer (Blade, React, Vue) either outputs that
HTML verbatim (when passing through raw) or rebuilds the same markup from
attributes. Static blocks are the right default:

- The output is deterministic from the attributes alone.
- No request-time data is required to render.
- The markup does not change based on the viewing user.

The callout is a static block. Its `save.tsx` returns the final HTML;
`save`/`edit` parity is validated by Gutenberg on load.

### Dynamic blocks

Dynamic blocks render server-side. Register the implementation via
`VisualEditor::registerDynamicBlock()` and omit `save.tsx` entirely — the
editor uses the preview endpoint while authoring, and the Blade/React/Vue
renderers call the PHP render callback at request time.

```php
use ArtisanPackUI\VisualEditor\Blocks\DynamicBlock;

class LatestPostsBlock extends DynamicBlock
{
    public function name(): string
    {
        return 'artisanpack/latest-posts';
    }

    public function render(array $attrs)
    {
        return view('blocks.latest-posts', [
            'posts' => Post::latest()->take($attrs['limit'] ?? 5)->get(),
        ]);
    }
}

VisualEditor::registerDynamicBlock(LatestPostsBlock::class);
```

Use a dynamic block when:

- Output depends on the current request (authenticated user, request
  time, query parameters).
- Output depends on database state that can change between save and
  render (latest posts, cart totals, stock levels).
- The block needs to run Laravel authorization before exposing data.

### Dynamic block hooks

`DynamicBlock` exposes four overridable methods. Only `name()` and
`render()` are required; the others have safe defaults.

```php
abstract class DynamicBlock
{
    abstract public function name(): string;
    abstract public function render(array $attrs); // : View|Stringable|string

    public function validateAttrs(array $attrs): array
    {
        return $attrs;
    }

    public function searchableText(array $attrs): string
    {
        return '';
    }

    public function authorize(?Authenticatable $user, array $attrs): bool
    {
        return true;
    }
}
```

- **`validateAttrs(array $attrs): array`** — runs before `render()` and
  before persistence. Normalize, coerce, and reject bad input. Throw
  `InvalidArgumentException` to abort. Defaults to passthrough.

  ```php
  public function validateAttrs(array $attrs): array
  {
      $limit = filter_var($attrs['limit'] ?? 5, FILTER_VALIDATE_INT);
      if ($limit === false || $limit < 1 || $limit > 50) {
          throw new \InvalidArgumentException('limit must be 1–50');
      }

      return ['limit' => $limit];
  }
  ```

- **`searchableText(array $attrs): string`** — plain-text extract used by
  `HasBlockContent::blockContentSearchableText()` and the Scout searchable
  array. Return whatever the block contributes to full-text search.
  Defaults to empty string.

  ```php
  public function searchableText(array $attrs): string
  {
      return (string) ($attrs['heading'] ?? '');
  }
  ```

- **`authorize(?Authenticatable $user, array $attrs): bool`** — gates
  preview rendering during authoring. Return `false` to render an
  authorization-denied placeholder instead of the block. Defaults to
  `true`. Public-site renders go through the renderer package directly
  and run the host app's own authorization.

  ```php
  public function authorize(?Authenticatable $user, array $attrs): bool
  {
      return $user?->can('view-internal-block') ?? false;
  }
  ```

### Block preview endpoint

While authoring, dynamic blocks render via
`POST /visual-editor/api/blocks/preview`. The editor sends
`{ blockName, attributes }`; the controller resolves the block, runs
`validateAttrs` → `authorize` → `render`, and returns HTML. Cache misses
during typing are amortized by the editor's per-block debounce.

---

## 5. Rendering on the public frontend

The visual editor ships three renderer packages. Each one looks up a
per-block partial/component by name and falls back to a "no renderer
registered" placeholder otherwise. A custom static block needs a
matching partial in every renderer you plan to use.

### 5.1 Blade renderer

`artisanpack-ui/visual-editor-renderer-blade` resolves the view name
`visual-editor-renderer-blade::blocks.{namespace}.{block}`. Add a
partial at:

```text
packages/visual-editor-renderer-blade/resources/views/blocks/artisanpack/callout.blade.php
```

The partial receives the block attributes in `$attributes` plus
`$innerBlocksHtml` (pre-rendered children). Mirror the `save.tsx` markup:

```blade
@php
    $severity = (string) ( $attributes['severity'] ?? 'info' );
    $content  = (string) ( $attributes['content'] ?? '' );
@endphp
<div class="ap-callout ap-callout--{{ $severity }}" data-severity="{{ $severity }}">
    <div class="ap-callout__body">{!! $content !!}</div>
</div>
```

Host apps can override any core or custom partial by publishing
`visual-editor-blade-views` and editing the file under
`resources/views/vendor/visual-editor-renderer-blade/blocks/…`.

### 5.2 React renderer

`artisanpack-ui/visual-editor-renderer-react` holds a module-level
registry keyed by block name. Create a renderer component:

```text
packages/visual-editor-renderer-react/src/blocks/artisanpack/callout.tsx
```

Then register it from `registerCoreBlocks.ts` (or from the host app's
own bootstrap):

```ts
import { registerBlockRenderer } from './registry';
import { CalloutBlock } from './blocks/artisanpack/callout';

registerBlockRenderer('artisanpack/callout', CalloutBlock);
```

The React component receives `{ attributes, innerBlocks, children }`.
Reuse the `attrString` / `classList` helpers in `support/attributes.ts`
for safe coercion.

### 5.3 Vue renderer

Identical pattern to React, using `defineComponent` and
`blockRendererProps`:

```text
packages/visual-editor-renderer-vue/src/blocks/artisanpack/callout.ts
```

Register via the same `registerBlockRenderer(name, component)` API.

### 5.4 Dynamic blocks and renderers

Dynamic blocks do **not** need per-renderer partials. The Blade renderer
invokes the registered `DynamicBlock::render()` directly; the React and
Vue renderers fall back to `<DynamicBlock>` which fetches the server-
rendered HTML from the preview endpoint.

---

## 6. Authoring checklist

For every new static block:

- [ ] Directory created under `resources/js/visual-editor/blocks/{name}/`
- [ ] `block.json` with a namespaced `name`, category, attributes, supports
- [ ] `edit.tsx` with `useBlockProps` and any `InspectorControls`
- [ ] `save.tsx` producing byte-identical markup for the `edit` output
- [ ] `index.ts` re-exporting `{ metadata, edit, save }`
- [ ] PHP registration (`VisualEditor::registerBlock(...)` in a boot hook)
- [ ] Added to `enabled_blocks` in `config/artisanpack/visual-editor.php`
- [ ] Blade partial in `visual-editor-renderer-blade`
- [ ] React renderer in `visual-editor-renderer-react` + registered
- [ ] Vue renderer in `visual-editor-renderer-vue` + registered
- [ ] Tests:
    - PHP: registry covers at least one of path / class / closure
    - JS: `registerCustomBlocks` + edit/save round-trip
    - Renderer-Blade / Renderer-React / Renderer-Vue: per-block render test

For dynamic blocks, swap `save.tsx` for a `DynamicBlock` subclass and
skip the per-renderer partials.
