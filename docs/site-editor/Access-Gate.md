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
   has: `hasCapability()`, then `hasPermissionTo()`, then
   `hasPermission()`. That covers `artisanpack-ui/rbac`, Spatie
   Permission and similar packages.
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
