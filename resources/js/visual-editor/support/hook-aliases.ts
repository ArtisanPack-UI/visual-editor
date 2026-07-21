/**
 * JS hook name deprecation aliases.
 *
 * Issue #664 renamed every visual-editor hook from kebab-case to camelCase.
 * The PHP side installs aliases via `deprecateHook()` from the
 * `artisanpack-ui/hooks` package. `@wordpress/hooks` has no equivalent
 * primitive, so this module installs bidirectional passthrough filters that
 * route:
 *
 *   applyFilters(OLD, value, ...args)  →  new-name subscribers fire
 *   applyFilters(NEW, value, ...args)  →  old-name subscribers still fire
 *
 * The alias handlers are registered at `Number.MIN_SAFE_INTEGER` priority
 * so they always run *before* any real subscribers on the applied hook —
 * that way real subscribers on the paired name are surfaced first via a
 * nested `applyFilters(other, ...)` call, and the applied name's real
 * subscribers then run in their natural priority order relative to the
 * value that came out of the paired chain. This matches the "collect
 * callbacks from every alias then dispatch in unified priority order"
 * behavior of PHP `ManagesHookBuckets` (hooks 1.3) closely enough for the
 * hook shapes visual-editor exposes.
 *
 * To avoid infinite recursion — the old-name filter re-applies the new
 * name, whose own alias would re-apply the old name — a module-level
 * re-entry counter guards each hook. A counter (not a Set) is required so
 * nested `applyFilters` on the same hook name from user callbacks does
 * not clear a guard the outer invocation set. Registration is idempotent
 * via a separate `registeredPairs` Set.
 *
 * Call {@link registerHookAliases} once at the top of every editor bootstrap
 * entry (post editor, site editor, sandbox) before any block registration
 * runs, so subscribers registered by first-party or third-party code fire
 * regardless of which name they used.
 */

import { addFilter, applyFilters } from '@wordpress/hooks';

/**
 * Priority slot for the alias forward/reverse handlers. `MIN_SAFE_INTEGER`
 * so no realistic host-supplied priority puts a real subscriber ahead of
 * the alias fan-out. Kept as a named constant so the intent is explicit.
 */
const ALIAS_PRIORITY = Number.MIN_SAFE_INTEGER;

/**
 * The exhaustive old-name → new-name rename table. Kept in lock-step with
 * `ArtisanPackUI\VisualEditor\Support\HookAliases::RENAMES` on the PHP
 * side. The three JS-only hooks (background-controls, canvas-styles,
 * document-panels) live only here — they have no PHP counterpart.
 */
const RENAMES: ReadonlyArray<readonly [string, string]> = [
    ['ap.visual-editor.resources', 'ap.visualEditor.resources'],
    ['ap.visual-editor.templates', 'ap.visualEditor.templates'],
    ['ap.visual-editor.template-parts', 'ap.visualEditor.templateParts'],
    ['ap.visual-editor.patterns', 'ap.visualEditor.patterns'],
    ['ap.visual-editor.global-styles', 'ap.visualEditor.globalStyles'],
    ['ap.visual-editor.navigation', 'ap.visualEditor.navigation'],
    [
        'ap.visual-editor.visibility.register-rules',
        'ap.visualEditor.visibility.registerRules',
    ],
    [
        'ap.visual-editor.visibility.evaluated',
        'ap.visualEditor.visibility.evaluated',
    ],
    [
        'ap.visual-editor.visibility.user-search-results',
        'ap.visualEditor.visibility.userSearchResults',
    ],
    ['ap.visual-editor.rendered-block', 'ap.visualEditor.renderedBlock'],
    ['ap.visual-editor.breadcrumbs.trail', 'ap.visualEditor.breadcrumbs.trail'],
    [
        'ap.visual-editor.loginout.envelope',
        'ap.visualEditor.loginout.envelope',
    ],
    [
        'ap.visual-editor.loginout.login-form',
        'ap.visualEditor.loginout.loginForm',
    ],
    ['ap.icons.register-icon-sets', 'ap.icons.registerIconSets'],
    // JS-only hooks (no PHP counterpart) — renamed for surface consistency.
    [
        'ap.visual-editor.background-controls',
        'ap.visualEditor.backgroundControls',
    ],
    ['ap.visual-editor.canvas-styles', 'ap.visualEditor.canvasStyles'],
    ['ap.visual-editor.document-panels', 'ap.visualEditor.documentPanels'],
];

/**
 * Pairs already installed this process. Guards `registerHookAliases()`
 * from double-registering.
 */
const registeredPairs = new Set<string>();

/**
 * Per-hook re-entry counter. Incremented before a nested `applyFilters`
 * routes to the paired name and decremented in `finally`. A callback that
 * itself calls `applyFilters(sameHook, ...)` nests safely because each
 * entry has its own increment/decrement pair.
 */
const reentryDepth = new Map<string, number>();

function enter(hook: string): void {
    reentryDepth.set(hook, (reentryDepth.get(hook) ?? 0) + 1);
}

function leave(hook: string): void {
    const current = reentryDepth.get(hook) ?? 0;
    if (current <= 1) {
        reentryDepth.delete(hook);
    } else {
        reentryDepth.set(hook, current - 1);
    }
}

function isReentering(hook: string): boolean {
    return (reentryDepth.get(hook) ?? 0) > 0;
}

/**
 * Install bidirectional aliases for every hook renamed in #664.
 *
 * Idempotent — a re-invocation is a no-op.
 */
export function registerHookAliases(): void {
    for (const [oldName, newName] of RENAMES) {
        const key = `${oldName}=>${newName}`;
        if (registeredPairs.has(key)) {
            continue;
        }
        registeredPairs.add(key);

        // Old → new: subscribers to `applyFilters(oldName, ...)` still get
        // to influence the value even though everything now applies under
        // the new name.
        addFilter(
            oldName,
            'artisanpack-ui/visual-editor/hook-alias-forward',
            (value: unknown, ...args: unknown[]): unknown => {
                if (isReentering(oldName)) {
                    return value;
                }
                enter(newName);
                try {
                    return applyFilters(newName, value, ...args);
                } finally {
                    leave(newName);
                }
            },
            ALIAS_PRIORITY,
        );

        // New → old: subscribers to `applyFilters(newName, ...)` fire even
        // when legacy code applies the old name.
        addFilter(
            newName,
            'artisanpack-ui/visual-editor/hook-alias-reverse',
            (value: unknown, ...args: unknown[]): unknown => {
                if (isReentering(newName)) {
                    return value;
                }
                enter(oldName);
                try {
                    return applyFilters(oldName, value, ...args);
                } finally {
                    leave(oldName);
                }
            },
            ALIAS_PRIORITY,
        );
    }
}

/**
 * Test-only helper — resets the module-level guards so a fresh
 * `registerHookAliases()` call installs aliases again. Not exported from
 * the package's public surface.
 *
 * @internal
 */
export function __resetHookAliasesForTests(): void {
    registeredPairs.clear();
    reentryDepth.clear();
}
