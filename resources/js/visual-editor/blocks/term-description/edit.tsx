/**
 * Term Description — edit component.
 *
 * Server-rendered display block: the real markup is produced by the
 * Blade / React / Vue renderers from the stamped `_resolvedTermDescription`
 * attribute. The fork previews through `createEntityPlaceholderEdit`:
 *
 *  1. Stamped `_resolvedTermDescription` attribute (front-end /
 *     saved-tree path).
 *  2. `artisanpack/postPreview.term.description` query-loop context
 *     (#520) — surfaces the post's primary-term description when the
 *     block is dropped inside a resolved `artisanpack/query` loop.
 *  3. A clearly-labelled "Term Description" placeholder. The block's
 *     `termId` / `taxonomy` block context isn't populated for the post
 *     editor (the term is only known on archive pages), so the editor
 *     preview can't fetch the live entity through the core-data shim.
 *
 * Phase I-Block-Fork — post navigation / metadata family (#520).
 */

import { createEntityPlaceholderEdit } from '../_shared/entity-placeholder-edit';
import type { QueryPreviewPost } from '../../editor/use-query-preview';

export default createEntityPlaceholderEdit( {
    label: 'Term Description',
    resolvedKey: '_resolvedTermDescription',
    kind: 'html',
    fromQueryPreview: ( post: QueryPreviewPost ) => {
        const description =
            post.term?.description !== undefined &&
            typeof post.term.description === 'string'
                ? post.term.description
                : '';

        return description === '' ? null : { text: description };
    },
    // Authors editing a template (or a post whose primary term has no
    // description) get a representative dummy so the styled paragraph
    // shape is visible. Front-end render never sees this.
    dummyValue: {
        text: 'A short description of the current term, surfaced on archive pages.',
    },
} );
