/**
 * PostTerms block entrypoint.
 *
 * Auto-discovered by `editor/custom-blocks.ts` and registered against
 * `@wordpress/blocks.registerBlockType`. Phase I-Block-Fork —
 * post navigation / metadata family (#520).
 *
 * Server-rendered display block: `edit` previews via
 * `createEntityPlaceholderEdit`, reading the stamped `_resolvedTermsLabel`
 * attribute (front-end / saved-tree) first, then the resolved post's
 * `terms[taxonomy]` from `artisanpack/postPreview` context, then the live
 * page entity's `_preview.terms` envelope; otherwise rendering a
 * clearly-labelled placeholder. Front-end markup is produced server-side
 * from stamped `_resolvedTerms*` attributes.
 */

import metadata from './block.json';
import edit from './edit';
import save from './save';
import deprecated from './deprecated';
import transforms from './transforms';
import icon from './inserter-icon';
import variations from './variations';

export { edit, save, metadata, icon, deprecated, transforms, variations };

export default {
    name: metadata.name,
    metadata,
    edit,
    save,
    icon,
    deprecated,
    transforms,
    variations,
};
