# Getting Started — Site Editor

Open the site editor surface, bind a permissive access gate, and start editing site-wide chrome (templates, parts, global styles, navigation, patterns).

This page assumes the visual-editor package is already installed. The site editor is hard-coupled to [`artisanpack-ui/cms-framework`](https://github.com/ArtisanPack-UI/cms-framework) — install it first if you haven't.

---

## 1. Install cms-framework

```bash
composer require artisanpack-ui/cms-framework
php artisan migrate
```

This adds the `Template`, `TemplatePart`, `Pattern`, `GlobalStyles`, `Menu`, and `MenuItem` models that persist site-editor entities. It also auto-binds `CmsFrameworkInstallGate` to the site-editor access contract — so once you've added a permissive check on top, the editor mounts.

Without cms-framework, the site-editor route surfaces an install-gate page instead of mounting the editor.

---

## 2. Open the access gate

The site editor is fail-closed. The package binds `DenyByDefaultGate` by default — every request to `/visual-editor/site` returns a 503 view explaining the editor has not been configured.

For dev / demo apps, bind the bundled install gate directly:

```php
// AppServiceProvider::register()
use ArtisanPackUI\VisualEditor\SiteEditor\Gates\CmsFrameworkInstallGate;
use ArtisanPackUI\VisualEditor\SiteEditor\Gates\SiteEditorAccessGate;

$this->app->bind(SiteEditorAccessGate::class, CmsFrameworkInstallGate::class);
```

For production, write a wrapper that runs the install check first, then an auth check:

```php
namespace App\SiteEditor;

use ArtisanPackUI\VisualEditor\SiteEditor\Gates\CmsFrameworkInstallGate;
use ArtisanPackUI\VisualEditor\SiteEditor\Gates\SiteEditorAccessGate;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class MyAppGate implements SiteEditorAccessGate
{
    public function __construct(
        protected CmsFrameworkInstallGate $installGate,
    ) {}

    public function check(Request $request): ?Response
    {
        if ($denial = $this->installGate->check($request)) {
            return $denial;
        }

        $user = $request->user();

        if (! $user) {
            return redirect()->route('login');
        }

        if (! $user->hasRole('admin')) {
            return response()->view('errors.403', status: Response::HTTP_FORBIDDEN);
        }

        return null;
    }
}
```

Bind it the same way:

```php
$this->app->bind(SiteEditorAccessGate::class, App\SiteEditor\MyAppGate::class);
```

Full contract and recipes: [[Access Gate]].

---

## 3. Visit the editor

```text
GET /visual-editor/site
```

The site-editor shell mounts a React SPA with a left-hand navigator (Templates / Template Parts / Patterns / Styles / Navigation) and a canvas frame on the right. The canvas is the same `BlockEditorProvider` as the post editor, scoped to the selected entity.

Direct links to specific entities work:

```text
/visual-editor/site/templates/single
/visual-editor/site/template-parts/header
/visual-editor/site/patterns/hero
/visual-editor/site/styles
/visual-editor/site/navigation/primary
```

These are shareable — bookmark or send to a colleague to jump straight into editing that entity.

---

## 4. Edit your first entity

Pick a section in the navigator and click an entry. The canvas loads the entity's block tree; edits autosave back through the REST surface (`PUT /visual-editor/api/templates/{slug}`, `/template-parts/{slug}`, etc.).

Switching entities saves pending edits to the previous entity first (blocking on the network round-trip), then loads the new entity.

---

## 5. (Optional) Seed initial templates and patterns

cms-framework's site-editor module reads from both the database and static config. To ship initial templates / parts / patterns / menus with your theme, declare them in `config/artisanpack/visual-editor.php`:

```php
'site-editor' => [
    'templates' => [
        'single' => ['title' => 'Single Post', 'content' => '…'],
    ],
    'template-parts' => [
        'header' => ['title' => 'Header', 'content' => '…'],
    ],
    'patterns' => [
        'hero' => ['title' => 'Hero', 'content' => '…'],
    ],
],
```

Or register them at runtime via filter — see [[Hooks and Events#ap-visual-editor-templates-template-parts-patterns-navigation]].

Static config is merged with DB-stored user overrides via the fallback chain — user records win on the same slug. See [[Templates#4-fallback-chain]].

---

## What to do next

- **Tour the surface:** [[Site Editor]] walks through the layout, canvas, and per-section behaviour.
- **Edit templates and template parts:** [[Templates]].
- **Edit theme-wide styles:** [[Global Styles]].
- **Edit menus:** [[Navigation]].
- **Author patterns:** [[Patterns]].
- **Render the templates on the public site:** [[Renderers]].
