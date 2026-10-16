/**
 * Post Content — edit component.
 *
 * Server-rendered display block: the real markup is produced by the
 * Blade / React / Vue renderers from stamped `_resolved*` attributes, and
 * the editor's core-data shim does not expose the entity to the post
 * editor. The fork therefore previews through the lightweight
 * `createEntityPlaceholderEdit` helper (renders the resolved value when
 * present, otherwise a clearly-labelled placeholder) rather than
 * delegating to upstream's entity-querying edit. Phase I5 entity cluster
 * (#413).
 */

import { __ } from '@wordpress/i18n';

import { TEXT_DOMAIN } from '../../vendor/i18n';
import { createEntityPlaceholderEdit } from '../_shared/entity-placeholder-edit';

export default createEntityPlaceholderEdit( {
    label: () => __( 'Post Content', TEXT_DOMAIN ),
    resolvedKey: '_resolvedContent',
    kind: 'html',
} );
