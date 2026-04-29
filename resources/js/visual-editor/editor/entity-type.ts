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
