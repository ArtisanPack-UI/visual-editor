# Plan 22 — Block Visibility: User & Auth Rules (#492)

Extends plan 21 with the three user-aware rules. Full runtime
contract in [`docs/visibility.md`](../visibility.md).

## Scope

- Login state (`LoginStateRule`) — `Logged in` / `Logged out` /
  `Either`.
- User role (`UserRoleRule`) — Any / All combinator, show/hide
  direction. Anonymous evaluation short-circuits without a DB query.
- Specific user (`SpecificUserRule`) — match by email (canonical) or
  id (fallback), backed by the `/visual-editor/api/users/search`
  autocomplete endpoint.

## Role resolution

The evaluator reads the visitor's roles through a fallback chain:

1. cms-framework's `RoleManager::rolesFor($user)` when the class exists.
2. Spatie-compatible `getRoleNames()`.
3. Eloquent `roles` relation, coercing each row's `slug` / `name`.
4. Empty list.

Hosts on a non-standard role stack rebind
`VisibilityEvaluator::resolveRoles()` via container extension.

## Anonymous safety

Role and specific-user rules short-circuit to hidden / visible without
issuing a DB query when `context.isAuthenticated === false`. No auth
lookup is ever performed for anonymous visitors.

## Files

- `src/Visibility/Rules/LoginStateRule.php`
- `src/Visibility/Rules/UserRoleRule.php`
- `src/Visibility/Rules/SpecificUserRule.php`
- `src/Http/Controllers/Visibility/UsersSearchController.php`
- `resources/js/visual-editor/visibility/user-search.ts`
- `routes/api.php` — `GET users/search`

Tests:

- `tests/Unit/VisualEditor/Visibility/AuthRulesTest.php`
- `tests/Feature/VisualEditor/VisibilityUsersSearchTest.php`

## Follow-ups

- Legacy role-lock migration notice on first-open of any post that
  already carries a legacy role-lock attribute (deferred — no such
  attribute is currently persisted by any released visual-editor
  version).
- Preview mode "mock logged-in user" + "mock role" inputs.
