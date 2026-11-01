# Block Visibility

Runtime rules that decide whether an individual block renders. Shipped
in 1.4.0 across three issues, with the Content rule added in 1.20.0:

- **#491 — Contextual rules**: master Hide, screen-size, query-string,
  referrer, browser / OS / device.
- **#492 — User & auth rules**: login state, user role, specific user.
- **#493 — Scheduling rules**: single date/time window, recurring
  weekly schedule, per-rule timezone override.
- **#1030 — Content rules**: show or hide a block based on what a query,
  related posts, post navigation or pagination block inside it resolved
  to.

All rules evaluate **server-side** so hidden blocks never emit markup
— there is no flash of content and no client-only privacy leak.

---

## For editors

Every block that opts in surfaces a single **Visibility** panel in the
inspector with a subsection per rule family. Only families you've
enabled render — an untouched panel stays minimal. Combine any number
of rules on a single block; the block renders only when *every* active
rule passes.

Rule shape at a glance:

| Rule                 | What it checks                                                    |
|----------------------|-------------------------------------------------------------------|
| Hide block           | Master toggle — the block disappears from output entirely.       |
| Screen size          | Emits `@media` rules per named breakpoint. Zero runtime JS.       |
| Query string         | `?utm_source=newsletter`, wildcard `?debug=*`, `Any` / `All`.      |
| Referrer             | Literal `twitter.com`, wildcard `*.example.com`, or `(direct)`.   |
| Browser / OS / Device| Chrome / Firefox / Safari / …, iOS / Android / …, mobile / tablet.|
| Login state          | `Logged in` / `Logged out` / `Either`.                            |
| User role            | Any / All of a list of role slugs.                                |
| Specific user        | Match by email or id. Autocomplete backed by `/users/search`.     |
| Date/time window     | Start and/or end datetime. Per-rule timezone override.            |
| Recurring schedule   | Up to 14 weekly windows (day + `HH:MM` start/end).                |
| Content              | Whether a loop or link inside the block has anything to show.     |

### Content

*Since v1.20.0 (#1030).* A Content rule shows or hides a block based on
the dynamic content inside it, so a wrapper doesn't render around nothing.

- **Hide an empty section.** Put the rule on a Group holding a "Related
  posts" heading and a Related Posts block, with source *Related Posts*,
  condition *Has results* and *Show when*. With no related posts, the
  whole Group disappears, heading included.
- **Hide an empty navigation band.** Put the rule on the Group around your
  Post Navigation Links, with source *Post Navigation* and condition *Has
  a previous or next post*.
- **Hide a pagination row.** Use source *Query Pagination* and condition
  *Has more than one page*.
- **Show a fallback.** Use *Hide when* on a block that also contains the
  loop to render it only when the loop is empty. Unlike Query No Results,
  the fallback can sit outside the loop.

The rule checks the block it's on and everything inside that block, and
below its controls the panel says what it found ("Checks 1 Related Posts
block"). With several matches, *Any* renders the block when at least one
passes, and *All* only when every one does.

| Source            | Blocks it inspects                     | Conditions                                    | Reads                                         |
|-------------------|----------------------------------------|-----------------------------------------------|-----------------------------------------------|
| Query Loop        | `query`                                | `hasResults`                                  | `_resolvedTotal`, `_resolvedItems` above zero |
| Related Posts     | `artisanpack/related-posts`            | `hasResults`                                  | `_resolvedTotal`, `_resolvedItems` above zero |
| Post Navigation   | `post-navigation-link`                 | `hasEither`, `hasPrevious`, `hasNext`         | `_resolvedPrevUrl`, `_resolvedNextUrl`        |
| Query Pagination  | `query-pagination`, `query`            | `hasMultiplePages`                            | `_resolvedTotalPages` above one               |

`query`, `post-navigation-link` and `query-pagination` match in either the
`artisanpack` or `core` namespace.

*Has results* means the current page shows at least one row, so a page
past the end of a loop counts as empty even though its total is above
zero. That's the same test Query No Results uses.

The rule is stored under `artisanpackVisibility.content`:

```json
{"artisanpackVisibility":{"content":{"source":"relatedPosts","condition":"hasResults","match":"any","direction":"show"}}}
```

If there's nothing for the rule to check, it has no effect and the block
renders: no matching block, or a `source` or `condition` that isn't
registered. A matching block that failed to resolve counts as empty, so
`show` hides the block and `hide` renders it.

The rule only looks inside the block, so it can't react to a loop
elsewhere on the page. It also reads what the loop resolved to, not what
rendered, so a loop hidden by its own visibility rule still counts.

### Screen size

*Since v1.15.2 (#974).* The Screen size subsection offers:

| Option              | Stored key(s)          | Hides the block at                 |
|---------------------|------------------------|------------------------------------|
| Mobile (≤767px)     | `mobile`               | `@media (max-width:767px)`         |
| Tablet (≤1023px)    | `tablet`               | `@media (max-width:1023px)` — phones too |
| Desktop (≥1024px)   | `lg`, `xl`, `2xl`      | 1024px and up                      |

`mobile` and `tablet` are the desktop-first device keys of the
[breakpoint registry](blocks/Responsive-Design-Tools.md), so a host
that narrows them (e.g. `'mobile' => '599px'`) moves the query with
them. Desktop is offered only when a legacy key starts one pixel above
the widest device key.

The legacy mobile-first keys (`sm`, `md`, `lg`, `xl`, `2xl`) still
work for saved content: each is a width **range** from its min-width
to one pixel below the next (`sm` = 640–767px, `md` = 768–1023px,
`2xl` = 1536px and up). The panel lists them under "Legacy width
ranges" only when a block's saved rule uses one the options above
don't cover. Keys of both families can be mixed:

```json
{"artisanpackVisibility":{"screenSize":{"direction":"hide","breakpoints":["mobile"]}}}
```

`direction: "show"` (not offered in the panel, but honoured when
saved) makes the block visible in the union of the listed ranges and
hides it at every unlisted legacy range that starts above the widest
listed device key. So `"show"` + `["mobile"]` hides from 768px up
(visible on phones only), `["tablet"]` hides from 1024px up, and
`["mobile", "xl"]` is visible ≤767px and 1280–1535px. With only legacy
keys listed, widths below 640px stay visible, as before. If a host
narrows a device key off a legacy boundary, `show` rounds to the
legacy boundaries: a legacy range starting inside the device's width
stays visible in full.

### In the editor

*Since v1.13.0 (#842).* The editor canvas previews visibility rules so
authors see roughly what visitors will see:

- **Screen-size rules hide the block at the previewed viewport.** Switch
  the viewport switcher to a device and blocks hidden at that width
  disappear from the canvas, using the same hidden width ranges as the
  front end. At the default (`base`) preview, the post editor's iframe
  canvas applies the front end's `@media` rules, so they test the
  canvas width. The site editor renders its canvas in the page rather
  than in an iframe, so at `base` it measures the canvas container's
  width (between the sidebars) and checks the ranges against that,
  instead of letting `@media` test the browser window.
- **A selected hidden block is shown dimmed.** When a hidden block, or a
  block inside it, is selected (for example from List View), it is
  revealed at reduced opacity so its toolbar and inline editing keep
  working.
- **Rules the canvas can't evaluate dim the block at every width.** The
  master Hide setting and the server-evaluated rules (login state, user
  role, specific user, date/time, schedule, query string, referrer,
  browser / OS / device) show the block dimmed rather than hidden.
- **Content rules don't affect the canvas.** They're enforced on the
  front end only, so a block with just a Content rule is neither hidden
  nor dimmed, and you can always see and edit what it wraps.
- **List View labels say why.** A block's List View label gets a suffix:
  "Hidden" (master Hide is on), "Hidden at some screen sizes" (a
  screen-size rule hides it at one or more breakpoints) or
  "Conditionally visible" (it has a server-evaluated or Content rule). Custom block
  names keep the suffix.

The canvas styles for this live in a shared `<style>` in the canvas
document's `<head>`, not next to each block, so they don't affect
`:first-child` or block-gap selectors.

---

## For developers

### Configuration

```php
// config/artisanpack/visual-editor.php
'visibility' => [
    'enabled'              => true,
    'user_model'           => null,        // defaults to auth.providers.users.model
    'user_search_columns'  => null,        // defaults to [ 'email', 'name' ] when present
],
```

Set `enabled` to `false` to bypass every visibility rule site-wide —
useful during incident response if a mis-published rule accidentally
hides critical content. When disabled the evaluator returns
`VisibilityDecision::visible()` for every block without touching the
rule registry.

### Server-side integration

The Blade renderer calls `VisibilityEvaluator::evaluate()` for every
block before rendering it. Blocks that return `hidden()` are dropped
outright; blocks that return `cssHidden()` (only the screen-size rule
today) are wrapped in a per-block `<style>` block emitting
breakpoint-scoped `display:none !important` rules.

Most CSS-hidden blocks are wrapped in a
`<div class="ve-vis-N" data-ve-vis-scope>` that carries the scope
class. Blocks that render a single `<li>` root (`core/navigation-link`,
`core/navigation-submenu`, `core/list-item`, and similar) sit directly
inside a `<ul>` / `<ol>`, where a `<div>` is invalid HTML. For those,
the scope class is added to the `<li>` itself and the `<style>` block
is placed inside it, so list semantics stay intact for assistive
technology. The React and Vue renderers do the same for
`core/navigation-link`, `core/navigation-submenu`, and `core/list-item`.

For the React and Vue renderers, pipe your tree through
`\ArtisanPackUI\VisualEditor\Visibility\TreePruner::prune()` before
serialising it into the page payload. Hidden blocks are removed and
CSS-hidden blocks receive a `_veHiddenBreakpoints` side-channel
attribute the client picks up via `stampVisibilityScopes()`.

```php
use ArtisanPackUI\VisualEditor\Visibility\TreePruner;

$tree = $pruner->prune( $post->content );
return Inertia::render( 'PostShow', [ 'blocks' => $tree ] );
```

Content rules read the `_resolved*` attributes, so the tree must be
resolved before you prune it. Otherwise every source counts as empty, and
the decision carries a `content:unresolved` reason:

```php
use ArtisanPackUI\VisualEditor\Resources\PostResolver;
use ArtisanPackUI\VisualEditor\Resources\QueryInliner;

$tree = $queryInliner->inline( $post->content, $post );
$tree = $postResolver->stampTree( $tree, $post );
$tree = $pruner->prune( $tree );
```

```tsx
import { BlockTree, filterVisibleBlocks, stampVisibilityScopes } from '@artisanpack-ui/visual-editor-renderer-react';

const { tree, css } = stampVisibilityScopes( filterVisibleBlocks( blocks ) );

return (
    <>
        {css !== '' && <style>{css}</style>}
        <BlockTree tree={tree} />
    </>
);
```

### Debug hook

Every evaluation fires
`ap.visualEditor.visibility.evaluated( decision, blockName, attributes, context )`.
Listen from a service provider to answer "why is this block hidden?":

```php
addAction( 'ap.visualEditor.visibility.evaluated', function ( $decision, $name, $attrs, $ctx ) {
    if ( $decision->isHidden() ) {
        \Log::debug( sprintf(
            '%s hidden by %s (viewer: %s)',
            $name,
            implode( ', ', $decision->reasons ),
            $ctx->isAuthenticated ? $ctx->userEmail : 'anonymous',
        ) );
    }
} );
```

Reasons are recorded on visible decisions too, for rules that had a
notable reason not to act. The Content rule uses these:

| Reason                       | Meaning                                                              |
|------------------------------|----------------------------------------------------------------------|
| `content`                    | The rule hid the block.                                              |
| `content:no-source`          | No matching block in the subtree, so the rule had no effect.         |
| `content:unknown-source`     | The saved `source` isn't registered, so the rule had no effect.      |
| `content:unknown-condition`  | The saved `condition` isn't one of the source's, so it had no effect. |
| `content:unresolved`         | A matching block never resolved and was counted as empty.            |

### Adding a custom rule

Implement the `VisibilityRule` interface and register through the
`ap.visualEditor.visibility.registerRules` filter:

```php
use ArtisanPackUI\VisualEditor\Visibility\RuleRegistry;

addFilter( 'ap.visualEditor.visibility.registerRules', function ( RuleRegistry $registry ) {
    $registry->register( new \App\Visibility\CookieRule() );
    return $registry;
} );
```

A rule that needs the block tree implements
`BlockAwareVisibilityRule`, whose `evaluateBlock()` receives the block,
`innerBlocks` included, in place of `evaluate()`.

### Adding a content source

A source tells the Content rule how to read one kind of block, and a
registered source appears in the panel automatically. Extend
`AbstractContentSource`, name the blocks, and say which `_resolved*`
attribute your block carries once it has resolved. A block without that
attribute counts as empty.

```php
use ArtisanPackUI\VisualEditor\Visibility\ContentSourceRegistry;
use ArtisanPackUI\VisualEditor\Visibility\ContentSources\AbstractContentSource;

class ProductLoopSource extends AbstractContentSource
{
    public function key(): string
    {
        return 'products';
    }

    public function label(): string
    {
        return __( 'Product Loop' );
    }

    public function blockNames(): array
    {
        return [ 'acme/product-loop' ];
    }

    public function conditions(): array
    {
        return [ 'hasProducts' => __( 'Has products' ) ];
    }

    public function passes( string $condition, array $block ): bool
    {
        return $block['attributes']['_resolvedProductCount'] > 0;
    }

    protected function resolvedKey(): string
    {
        return '_resolvedProductCount';
    }
}

addFilter( 'ap.visualEditor.visibility.registerContentSources', function ( ContentSourceRegistry $registry ) {
    $registry->register( new ProductLoopSource() );
    return $registry;
} );
```

To match on something other than the block name, implement `ContentSource`
directly, or override `matches()`, and return `[]` from `blockNames()`. The
panel then shows no detected-block hint for that source.

### Per-block opt-out

Every block ships opted in. Blocks that must not be conditionally
hidden — e.g. a document title on a post editor — set
`supports.artisanpackVisibility: false` in their `block.json`. Opt-out
suppresses both the attribute injection and the inspector panel.

### Audit command

`php artisan visual-editor:audit-scheduled-blocks` walks every resource
registered under `artisanpack.visual-editor.resources` and prints a
table of blocks with active `dateTimeWindow` or `recurring` rules.
Filter to a single resource with `--resource=pages`.

### Timezones

Both scheduling rules interpret their configured times in the
`timezone` field. When omitted they fall back to
`config( 'app.timezone' )`, which itself falls back to UTC. Wall-clock
schedules ("10:00 America/Chicago every Sunday") stay correct across
DST transitions — the rule matches at 10:30 whether that instant is
CST or CDT.

### Cache implications

Content rules depend on the content rather than the visitor, so they add
no per-visitor caveat. User- and role-based rules make a page's rendered output dependent on
the visitor. If a scheduled or auth-dependent block sits inside a
cached template part or output cache, the cache TTL must be shorter
than the window's precision (for a 5-minute recurring window, the
cache TTL must be <5 minutes). A cache-tightening layer that
auto-shortens TTLs when scheduled blocks are discovered is planned
for a follow-up release; until then, hosts running the cache-heavy
plans should either scope their cache keys to `Auth::id()` or set a
short TTL for pages containing visibility-gated content.

### Roles

The evaluator reads the viewer's role list from cms-framework's
`RoleManager` when installed, falling back to
`Auth::user()->getRoleNames()` (Spatie-compatible), `->roles` (Eloquent
relation with `slug` or `name`), or an empty list. Hosts on a
non-standard role stack can override
`VisibilityEvaluator::resolveRoles()` via container rebinding.

### User-agent parsing

The Browser / OS / Device rule uses a bundled regex parser covering
the top ~95% of real traffic. Hosts that need finer classification
install [`jenssegers/agent`](https://packagist.org/packages/jenssegers/agent)
and swap the bound `UserAgentParser` in their own service provider.
