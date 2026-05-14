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
