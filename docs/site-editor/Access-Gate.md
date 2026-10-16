---
title: Site Editor Access Gate
---

# Site Editor Access Gate

The site-editor SPA at `/visual-editor/site/{path?}` is the package's most
sensitive surface — it exposes templates, patterns, global styles, menus
and template parts for the whole site. **The package does not assume it
knows who should reach it.** Each consuming application decides.

This document describes the contract a consumer implements to control
that access, and the gates the package ships out of the box.

## The contract

```php
namespace ArtisanPackUI\VisualEditor\SiteEditor\Gates;

use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

interface SiteEditorAccessGate
{
    public function check( Request $request ): ?Response;
}
```

- Return `null` to **allow** the request — the route renders the SPA
  mount view.
- Return a `Response` to **short-circuit** the request — the route
  returns that response verbatim. This is the hook for install-gate
  pages, login redirects, 403 / 503 templates, or anything else the
  consumer wants to show in place of the editor.

The package resolves whatever is bound to `SiteEditorAccessGate::class`
from the container on each request to the site-editor route. To change
the behaviour, bind your own implementation in a service provider.

## API writes use the same gate

Since 1.12.0, the same gate also guards every site-editor API route that
changes site-wide data: creating, updating, or deleting templates,
template parts, global styles, patterns, menus, and menu items. The
`EnsureSiteEditorAccess` middleware runs the gate on those routes. When
the gate returns a response, the request stops with a JSON `403`; a JSON
response from your gate is passed through unchanged.

Read routes are not gated. The post editor loads menus, template parts,
patterns, and global styles through them (for example, to render a
`core/navigation` or template-part block), so they keep the post
editor's `auth` requirement.

Two consequences to plan for:

- Users who can't reach the site editor also can't use the post
  editor's **Convert to pattern** action, because saving a pattern is a
  site-wide write.
- Under the package default (`DenyByDefaultGate`), every one of these
  writes is refused until you bind a gate.

## Content-authoring ability (`visual-editor.edit-content`)

*Since v1.13.0 (#834).* A second, lighter check guards the
content-authoring API endpoints that sit outside any model policy:

| Endpoint | Notes |
|----------|-------|
| `GET /visual-editor/api/icons/sets` | Icon picker |
| `GET /visual-editor/api/icons/search` | Icon picker |
| `GET /visual-editor/api/icons/svg` | Icon picker |
| `POST /visual-editor/api/icons/svg/sanitize` | Custom SVG sanitizer; also throttled |
| `POST /visual-editor/api/patterns/preview` | Pattern card previews; also throttled at 120/min |

These routes run the `EnsureContentEditorAccess` middleware, which checks
the `visual-editor.edit-content` Gate ability
(`ContentAccess::ABILITY`). A user who fails it gets a JSON `403`:

```json
{ "message": "You are not allowed to edit content." }
```

Guests get the usual `401` from the `auth` middleware first.

### How the default check works

The package registers a default `visual-editor.edit-content` gate (only
when the host hasn't defined one) backed by `ContentAccess::allows()`:

1. With `content_access.capability` unset (`null`, `''` or any
   non-string value), **any authenticated user passes**. This is the
   default, so nothing changes unless you opt in.
2. With a capability string (for example `'edit_content'`), the user
   needs it. The check calls the first of these methods the user model
   has: `checkPermissionTo()` (since v1.15.0), then `hasCapability()`,
   then `hasPermissionTo()`, then `hasPermission()`. That covers
   `artisanpack-ui/rbac`, Spatie Permission and similar packages. If the
   method throws (Spatie's `hasPermissionTo()` does for a permission
   that was never created), the exception is reported and the check
   denies.
3. If the user model has none of those methods, the check falls back to
   Laravel's `$user->can( $capability )` and logs a warning once per
   request, since that usually means the capability doesn't match your
   authorization setup. (A capability equal to `visual-editor.edit-content`
   itself is denied rather than checked through `can()`, which would
   recurse.)

```php
// config/artisanpack/visual-editor.php
'content_access' => [
    'capability'        => 'edit_content',
    'sanitize_throttle' => '60,1',
],
```

`content_access.sanitize_throttle` is the per-user rate limit for
`icons/svg/sanitize`, as `throttle` middleware arguments
(`"max attempts,minutes"`, default `'60,1'`). It has its own rate-limit
bucket, so it doesn't count against other throttled routes. `null` or
`''` uses the default and `false` turns the throttle off. The value is
read when routes are registered, so re-run `php artisan route:cache`
after changing it on an install with cached routes. See
[Configuration](../Configuration.md#content_access).

### Overriding the gate

Define the ability yourself to replace the default check entirely. The
package registers its default only when `Gate::has()` reports no
definition, and a `Gate::before()` callback wins as usual:

```php
// AppServiceProvider::boot()
use Illuminate\Support\Facades\Gate;

Gate::define( 'visual-editor.edit-content', function ( $user ): bool {
    return $user->is_staff;
} );
```

### How it differs from the site-editor gate

| | Site-editor gate | `visual-editor.edit-content` |
|-|------------------|------------------------------|
| Guards | The site-editor SPA and site-wide API writes | Icon picker, SVG sanitizer, pattern previews |
| Mechanism | A bound `SiteEditorAccessGate` class | A Laravel Gate ability |
| Package default | Deny (`DenyByDefaultGate`) | Allow any authenticated user |
| Failure | The gate's response (a JSON `403` on API writes) | JSON `403` |

It's a post-editor-level check: authors who can't reach the site editor
can still pass it, so they keep the icon picker and pattern previews in
the post editor.

## Custom CSS ability (`visual-editor.edit-block-css`)

*Since v1.15.0 (#881).* A block's **Custom CSS** (Styles → Effects) is
printed unescaped inside the block's rule on the public site. The
sanitizer keeps it inside that rule and its `<style>` element, but CSS
is never a sandbox: it can restyle or hide anything on the page. So
writing it is a separate, **deny-by-default** Gate ability,
`visual-editor.edit-block-css` (`BlockCssAccess::ABILITY`).

### How the default check works

The package registers a default gate (only when the host hasn't defined
the ability) backed by `BlockCssAccess::allows()`:

1. Guests are denied.
2. The user needs the capability named by
   `effects.custom_css_capability` (default `edit_block_css`). An empty
   string denies everyone.
3. The capability is checked through `CapabilityCheck::check()`, which
   calls the first of these methods the user model defines and lets it
   decide: `checkPermissionTo()`, `hasCapability()`,
   `hasPermissionTo()`, `hasPermission()`. If that method throws, the
   exception is reported and the check denies, so a Spatie install that
   never created the `edit_block_css` permission simply doesn't get
   Custom CSS, rather than a 500 on every editor page. A user model
   with none of the four methods is denied; unlike
   `visual-editor.edit-content`, there is no `can()` fallback.

Nobody has the capability until you grant it to a role:

```php
// config/artisanpack/visual-editor.php
'effects' => [
    'custom_css_capability' => 'edit_block_css',
],
```

The same capability check (methods, order and deny-on-throw) is used by
the `content_access`, `ai` and `fonts` capabilities. See
[Configuration](../Configuration.md#how-capabilities-are-checked).

### Overriding the gate

Define the ability yourself to replace the default check:

```php
// AppServiceProvider::boot()
use Illuminate\Support\Facades\Gate;

Gate::define( 'visual-editor.edit-block-css', function ( $user ): bool {
    return $user->is_admin;
} );
```

### What a user without the ability sees

- **In the editor**, the Custom CSS field is hidden. The Blade
  `<x-visual-editor>` component resolves the gate for you. Vue and
  Inertia hosts that mount through `mountEditor()` or the Vue
  `<VisualEditor>` component pass the result themselves, or the field
  stays hidden:

  ```php
  use ArtisanPackUI\VisualEditor\BlockStyles\BlockStyleResolver;
  use ArtisanPackUI\VisualEditor\Effects\BlockCssAccess;
  use Illuminate\Support\Facades\Gate;

  return Inertia::render( 'Posts/Edit', [
      'canEditBlockCss'    => Gate::allows( BlockCssAccess::ABILITY ),
      'customCssAllowUrls' => (bool) config( 'artisanpack.visual-editor.effects.custom_css_allow_urls', true ),
      'blockStyles'        => app( BlockStyleResolver::class )->editorPayload(),
  ] );
  ```

  Pass them on as `MountConfig.canEditBlockCss` / `customCssAllowUrls`
  / `blockStyles`, or as the Vue component's `can-edit-block-css`,
  `custom-css-allow-urls` and `block-styles` props. The flag only
  changes the UI; the server enforces the gate on save.
- **On save**, each block's `artisanpackEffects.customCss` survives only
  when the same value (after normalization) is already stored on the
  record being saved. So an editor without the ability can keep, move
  or duplicate a block an administrator styled, but can't add or change
  its Custom CSS. Both the editor (`attributes`) and parse (`attrs`)
  block shapes are checked, including nested `innerBlocks`.

### The guard runs on every model save

The guard runs in the `saving` event of every model that uses
`HasBlockContent` (posts, pages, cms-framework's templates, template
parts, block patterns, and your own content models) and of the package's
`Snippet` model. Every write path is covered, including cms-framework's
`/api/v1/blocks`, `/api/v1/block-patterns/patterns`, `/api/v1/templates`
and `/api/v1/template-parts`, and any code that calls `save()`. It only
acts when the block column changed.

To guard a model that stores block trees without `HasBlockContent`,
register it in a service provider's `boot()`:

```php
use ArtisanPackUI\VisualEditor\Effects\CustomCssModelGuard;

CustomCssModelGuard::watch( App\Models\LandingPage::class, 'blocks' );

// Or resolve the column per record:
CustomCssModelGuard::watch( App\Models\Widget::class, fn ( $widget ) => $widget->blockColumn() );
```

The guard is skipped when **no user is authenticated and the app is
running in the console**, so seeders, imports, queued jobs and Artisan
commands keep their Custom CSS. Query-builder mass updates
(`Model::query()->update( … )`) fire no model events and aren't
covered; don't write block content that way from user input.

### Trusted writes while a user is logged in

Code that saves block content containing Custom CSS on a user's behalf,
while that user lacks the ability, has its CSS stripped. Typical cases
are a theme import or a pattern clone triggered from an admin page, and
test suites that seed such records while `actingAs()` a user. Wrap the
write in `trusted()`:

```php
use ArtisanPackUI\VisualEditor\Effects\CustomCssModelGuard;

app( CustomCssModelGuard::class )->trusted( function () use ( $import ): void {
    $import->run();
} );
```

`trusted()` returns the callback's return value. Use it only for content
that doesn't come from the current user.

### cms-framework's Site Editor API

cms-framework 2.15.0 and later run the block trees their Site Editor API
stores through the `ap.cmsFramework.siteEditor.blockContent` filter,
which visual-editor hooks to apply the same check. It passes the
record's stored blocks, or the theme file's blocks when a save copies a
theme file into the database (including `PatternResolver::cloneToUser()`),
so the theme's own Custom CSS is kept on those copies. Older
cms-framework versions are still covered by the model-level guard. See
[Hooks and Events](../Hooks-and-Events.md#apcmsframeworksiteeditorblockcontent).

### Restricting what Custom CSS can load

Even a trusted author's Custom CSS can use attribute selectors plus a
remote `url()` to send input values to another server. Set
`effects.custom_css_allow_urls` to `false` to drop any Custom CSS that
loads a resource. See [Configuration](../Configuration.md#effects) and
[Effects](../blocks/Effects.md).

## Binding previews check the record's policy

*Since v1.15.0.* The editor's bindings preview
(`POST /visual-editor/api/bindings/resolve`) and dynamic block previews
(`POST /visual-editor/api/blocks/preview`) read the record named in the
request. When the host registers a policy for that record's model, the
user needs its `view` ability, or the endpoint answers `403`. Models
without a policy behave as before. See
[Block Bindings](../block-bindings.md#record-access-check).

## Package default — fail closed

If a consuming app does not bind a gate, the package binds
`DenyByDefaultGate` automatically. That gate returns a 503 view on every
request explaining the editor has not been configured. **A fresh
install cannot expose the site editor by accident.**

## Bundled `CmsFrameworkInstallGate`

For the historical behaviour — allow when cms-framework's SiteEditor
module is booted, render the install-instructions page otherwise — bind
the bundled gate directly:

```php
// AppServiceProvider::register()
use ArtisanPackUI\VisualEditor\SiteEditor\Gates\CmsFrameworkInstallGate;
use ArtisanPackUI\VisualEditor\SiteEditor\Gates\SiteEditorAccessGate;

$this->app->bind( SiteEditorAccessGate::class, CmsFrameworkInstallGate::class );
```

This is the right choice for dev / demo apps where any authenticated
visitor should reach the editor as long as cms-framework is installed.

## Composing your own gate

Production CMS hosts almost always want at least two checks: an
authorisation check (is this user an admin?) and an install check (is
cms-framework actually on the classpath?). The recommended pattern is
to wrap `CmsFrameworkInstallGate` and run it before your auth check, so
an unauthorised visitor still sees the install instructions in a
half-installed state rather than a 403 that leaks deployment state.

```php
namespace App\SiteEditor;

use ArtisanPackUI\VisualEditor\SiteEditor\Gates\CmsFrameworkInstallGate;
use ArtisanPackUI\VisualEditor\SiteEditor\Gates\SiteEditorAccessGate;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class MyAppSiteEditorGate implements SiteEditorAccessGate
{
    public function __construct(
        protected CmsFrameworkInstallGate $installGate,
    ) {}

    public function check( Request $request ): ?Response
    {
        if ( $denial = $this->installGate->check( $request ) ) {
            return $denial;
        }

        $user = $request->user();

        if ( ! $user ) {
            return redirect()->route( 'login' );
        }

        if ( ! $user->hasRole( 'admin' ) ) {
            return response()->view( 'errors.403', status: Response::HTTP_FORBIDDEN );
        }

        return null;
    }
}
```

Bind it the same way:

```php
// AppServiceProvider::register()
$this->app->bind( SiteEditorAccessGate::class, MyAppSiteEditorGate::class );
```

## Contract guarantees

- The gate is resolved per-request, so it can depend on request-scoped
  state (the authenticated user, the route, the session).
- The gate runs inside the `web` middleware group, so session, CSRF
  and auth state are available on the `Request`.
- The package binds its default with `bindIf`, so a consumer-supplied
  binding registered earlier in the boot order always wins.
- Implementations **must not throw on the unauthenticated /
  unauthorised path** — return a `Response` instead so the user sees a
  useful page rather than a generic framework error.

## Testing a custom gate

Bind a stub in the test's `beforeEach` and assert the route's
behaviour:

```php
beforeEach( function (): void {
    $this->app->bind( SiteEditorAccessGate::class, function () {
        return new class implements SiteEditorAccessGate
        {
            public function check( Request $request ): ?Response
            {
                return null; // allow
            }
        };
    } );
} );
```

See `tests/Feature/SiteEditor/SiteEditorAccessGateTest.php` for the
full contract test set.
