/**
 * Read More — edit component.
 *
 * Server-rendered display block: the real markup is produced by the
 * Blade / React / Vue renderers from the block's `content` attribute and
 * the stamped `_resolvedPermalink` attribute. The fork previews through
 * `createEntityPlaceholderEdit`:
 *
 *  1. The block's `content` attribute, when set — preview the configured
 *     link text so authors get instant feedback while editing it.
 *  2. A localized "Read more" placeholder otherwise.
 *
 * The permalink isn't surfaced in the editor preview (it's a
 * server-resolved value); the canvas shows the styled text shape only.
 *
 * Phase I-Block-Fork — post navigation / metadata family (#520).
 */

import { createEntityPlaceholderEdit } from '../_shared/entity-placeholder-edit';

// `resolvedKey: 'content'` makes the placeholder helper treat the block's
// own `content` attribute as the resolved value — so a non-empty `content`
// previews the configured link text, and an empty one falls through to
// the `dummyValue` ("Read more"). No wrapper logic needed.
export default createEntityPlaceholderEdit( {
    label: 'Read More',
    resolvedKey: 'content',
    kind: 'text',
    dummyValue: { text: 'Read more' },
} );
