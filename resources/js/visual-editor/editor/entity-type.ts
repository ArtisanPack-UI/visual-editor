/**
 * Maps an editor mount resource slug onto its core-data entity type
 * name.
 *
 * cms-framework's `posts` / `pages` slugs (registered into
 * visual-editor's resource map via the
 * `ap.visual-editor.resources` filter from #397 + cms-framework's
 * #99 bridge) drive `core/post-*` blocks via the `EntityProvider`
 * wrap inside `EditorApp`. Any other resource (custom
 * `HasBlockContent` models, legacy fixtures) returns `null` so the
 * wrap is skipped and the blocks render the placeholder shell
 * `core-data` emits for missing context. See plan 12 §4.4 for the
 * full G3 entity adapter contract.
 *
 * Lives in its own module so its tests can import it without
 * pulling the entire `@wordpress/*` editor bundle through
 * `editor-app.tsx`'s transitive deps.
 */

import type { DocumentType } from './document-panels';

export function entityTypeForResource(resource: string): DocumentType {
    if (resource === 'posts') {
        return 'post';
    }

    if (resource === 'pages') {
        return 'page';
    }

    return null;
}

/**
 * Resolves the post-type slug the page-pattern modal (#639) scopes its
 * fetch to.
 *
 * A host-supplied `override` (the mount's `data-pattern-post-type`)
 * wins so custom content types — e.g. a host-registered `package` —
 * can opt into the modal without being enrolled in the core-data
 * entity wrap that {@see entityTypeForResource} gates. Without one,
 * falls back to the `posts` / `pages` mapping; `null` keeps the modal
 * off.
 */
export function patternPostTypeForResource(
    resource: string,
    override?: string | null
): string | null {
    const normalized = override?.trim().toLowerCase();

    if (normalized) {
        return normalized;
    }

    return entityTypeForResource(resource);
}
