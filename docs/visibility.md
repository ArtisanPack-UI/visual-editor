# Block Visibility

Runtime rules that decide whether an individual block renders. Ships in
1.4.0 across three issues:

- **#491 — Contextual rules**: master Hide, screen-size, query-string,
  referrer, browser / OS / device.
- **#492 — User & auth rules**: login state, user role, specific user.
- **#493 — Scheduling rules**: single date/time window, recurring
  weekly schedule, per-rule timezone override.

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

The editor canvas dims hidden blocks so authors can still see and
select them while they're toggled off.

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

User- and role-based rules make a page's rendered output dependent on
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
