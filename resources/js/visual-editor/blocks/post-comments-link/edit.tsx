/**
 * PostCommentsLink — edit component.
 *
 * Server-rendered display block. Previews through the post-family
 * `createEntityPlaceholderEdit`. The link itself is emitted server-side
 * from the stamped `_resolvedCommentsUrl` / `_resolvedCommentsLabel`
 * attributes; the editor preview just shows the label text.
 * Comments family fork (#519) Pass 2.
 */

import { __ } from '@wordpress/i18n';

import { TEXT_DOMAIN } from '../../vendor/i18n';
import { createEntityPlaceholderEdit } from '../_shared/entity-placeholder-edit';

export default createEntityPlaceholderEdit( {
    label: __( 'Comments Link', TEXT_DOMAIN ),
    resolvedKey: '_resolvedCommentsLabel',
    kind: 'text',
    dummyValue: { text: '5 Comments' },
} );
