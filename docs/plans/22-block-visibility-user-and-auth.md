# Visual Editor — Block Visibility (User Role & Auth State)

**Package:** `artisanpack-ui/visual-editor`
**Version Target:** TBD (Awaiting Review)
**Created:** 2026-05-30
**Status:** Planning
**Reference:** [Block Visibility (WordPress plugin, by Nick Diego)](https://github.com/ndiego/block-visibility)
**Related:**
 - [`21-block-visibility-contextual.md`](21-block-visibility-contextual.md) — primary visibility feature; this extends its panel.
 - [`07-permissions-locking.md`](07-permissions-locking.md) — block-level locking via cms-framework's role system. Distinct concept (locking gates *editing*; this gates *rendering*).

---

## 1. Problem Statement

The contextual visibility feature ([`21-block-visibility-contextual.md`](21-block-visibility-contextual.md)) handles request-time rules. It deliberately defers user-aware rules to here because of the question of overlap with `artisanpack-ui/cms-framework`'s permission system. This plan resolves that question by adding block-level visibility for:

- the visitor's login state (logged in / logged out),
- the visitor's user role(s),
- a specific user (by ID/email),

and explicitly distinguishes those from the cms-framework permission system, which protects *editing* but not *rendering*.

## 2. Target User

Membership-site operators, app builders with role-gated features, and editors who want different content shown to admins vs. members vs. anonymous visitors — all without dropping into custom Blade conditionals.

## 3. User Stories

- As an editor on a membership site, I want a CTA visible to logged-out visitors and a different CTA visible to logged-in members.
- As an editor, I want a block visible only to users with the `admin` role (e.g. an internal debug widget left in the page).
- As an editor, I want a block visible only to a specific user (by email) — useful for personalised landing pages.
- As a developer, I want the role list shown in the InspectorControls to be the roles the host application's permission system actually exposes, not a hard-coded list.
- As a developer, I want this visibility evaluated server-side so logged-out visitors never receive the markup intended for members.

## 4. Scope

### 4.1 In scope

- **Login State rule** — `Show if logged in` / `Show if logged out` / `Either`.
- **User Role rule** — list of roles with Any/All combinator and show/hide direction. The list is read at runtime from the host app's permission system; default integration is `cms-framework`'s permission registry, falling back to Laravel's standard `Gate` / role helpers if `cms-framework` isn't installed.
- **Specific User rule** — list of users by ID or email, with show/hide direction. UI is an autocomplete that queries `/users/search?q=...` (a small new endpoint behind the existing editor middleware).
- **Anonymous-safe evaluation** — if the visitor is logged out, role and user rules short-circuit cleanly (no DB lookups, no exceptions).
- **Renderer treats hidden blocks as fully absent** — no markup, no DOM placeholder, no flash of content. This is the existing visibility renderer behavior from [`21-block-visibility-contextual.md`](21-block-visibility-contextual.md).
- **Visibility Preview mode integration** — extends the existing preview controls with "mock logged-in as: [user picker]" and "mock role: [role picker]".
- **Migration consideration** — if the cms-framework block-locking system already records "this block is only visible to role X," surface a one-time editor notice on first open: "This block has a legacy role lock. Migrate to the new Visibility panel?" Migration is opt-in per block.

### 4.2 Out of scope

- **Permission-based editing gates.** Already handled by cms-framework + [`07-permissions-locking.md`](07-permissions-locking.md). This feature gates rendering only.
- **Per-route or per-page visibility.** Use the existing route middleware for that.
- **Custom claims / OIDC scopes.** Out of scope; would need an integration interface and is rarely needed.

## 5. Behavior

### 5.1 Happy path

1. Editor sets a Premium Content block to "Show if logged in AND role is `member`."
2. Save. Renderer evaluates against `auth()->user()` and the cms-framework `Permission` registry.
3. Anonymous visitor: block is not emitted. Markup not present in the response at all.
4. Logged-in `member`: block renders normally.
5. Logged-in `admin` (not a member): block is not emitted unless the editor adds `admin` to the role list.

### 5.2 Edge cases

- **Host app has no roles configured** (no permission system installed). Role rule UI shows an empty list with a "No roles available" note; the rule evaluator returns "visible" for any visitor (rule contributes nothing).
- **Specific User rule references a deleted user.** Audit command flags the orphan; renderer treats as no-match.
- **Block is rendered inside a cached fragment.** Visibility rules that depend on the request user break caching unless the cache key includes the user. Documented; the editor warns when a block with a role/user rule is placed inside a known-cacheable wrapper (template-part with `cache_until` set).
- **Visibility Preview "logged in as X" must not actually log the editor in as X.** The preview is rule-evaluation simulation only — no auth side effects.

## 6. Acceptance Criteria

- [ ] Login state rule works end-to-end across all three renderers.
- [ ] Role rule reads the live role list from cms-framework's permission registry; falls back gracefully when absent.
- [ ] Specific User rule autocomplete queries the new `/users/search` endpoint behind editor auth middleware.
- [ ] Anonymous evaluation short-circuits without DB queries.
- [ ] Visibility Preview lets the editor mock login state, role, and specific user.
- [ ] Legacy role-lock migration notice appears on first open of a previously locked block.
- [ ] Cache-warning surfaces when a user/role rule is inside a cacheable template part.
- [ ] Pest tests cover all three rule evaluators, anonymous edge cases, and the migration path.
- [ ] Vitest tests cover the InspectorControls + autocomplete.
- [ ] Playwright E2E covers a member-only-CTA scenario across logged-in / logged-out sessions.
- [ ] Docs in `docs/visibility.md` extend with a User & Auth section, including the relationship to cms-framework permissions.

## 7. Implementation Notes

### 7.1 Files to create

- `src/Visibility/Rules/LoginStateRule.php`
- `src/Visibility/Rules/UserRoleRule.php`
- `src/Visibility/Rules/SpecificUserRule.php`
- `src/Visibility/RoleRegistryAdapter.php` — abstraction over cms-framework / Laravel Gate; tested with both.
- `src/Http/Controllers/UserSearchController.php` — autocomplete endpoint.
- `resources/js/visual-editor/visibility/UserAuthSection.tsx` — extends the existing VisibilityPanel.
- `resources/js/visual-editor/visibility/UserAutocomplete.tsx`.
- `tests/Unit/Visibility/Rules/*`, `tests/Feature/Visibility/UserAuth*`, Vitest + Playwright suites.

### 7.2 Files to modify

- `routes/api.php` (or equivalent in the package) — register `/users/search` behind editor auth.
- `src/Visibility/VisibilityEvaluator.php` — wire in the three new rules.
- `resources/js/visual-editor/visibility/VisibilityPanel.tsx` — render the new section.
- `resources/js/visual-editor/visibility/PreviewControls.tsx` — add the auth mockers.
- `docs/visibility.md` — extend.

### 7.3 Database / schema

No DB migrations. Rules live in the existing `artisanpackVisibility` attribute object.

### 7.4 Dependencies

None new. Depends on [`21-block-visibility-contextual.md`](21-block-visibility-contextual.md) shipping first.

## 8. Open Questions

- Should the role-list source be configurable (default: cms-framework, alternates: Gate / Spatie permissions)? (Tentative: yes, via `config('artisanpack.visual-editor.visibility.role_source')`.)
- Should we ship a "Show during impersonation" sub-rule for admins impersonating other users? (Tentative: defer; out of scope until impersonation lands in cms-framework.)
