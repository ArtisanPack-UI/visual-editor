/**
 * PostCommentsTitle — edit component.
 *
 * Server-rendered display block. Previews through the post-family
 * `createEntityPlaceholderEdit`: stamped `_resolvedCommentsTitle`
 * first, then a labelled placeholder. The server-side renderer
 * computes the final "X Comments" string from `_resolvedCommentCount`
 * and the `showCommentsCount` / `showPostTitle` attributes; the
 * editor preview just shows the stamped value or the label.
 * Comments family fork (#519) Pass 2.
 */

import { __ } from '@wordpress/i18n';

import { TEXT_DOMAIN } from '../../vendor/i18n';
import { createEntityPlaceholderEdit } from '../_shared/entity-placeholder-edit';

export default createEntityPlaceholderEdit( {
    label: __( 'Comments Title', TEXT_DOMAIN ),
    resolvedKey: '_resolvedCommentsTitle',
    kind: 'text',
    dummyValue: { text: '5 Comments' },
} );
