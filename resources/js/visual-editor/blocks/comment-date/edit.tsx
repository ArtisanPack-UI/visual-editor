/**
 * CommentDate — edit component.
 *
 * Server-rendered display block. Previews through
 * `createCommentPlaceholderEdit`: stamped `_resolvedDateFormatted`
 * first, then `artisanpack/commentPreview` context, then placeholder.
 * Comments family fork (#519).
 */

import { __ } from '@wordpress/i18n';

import { TEXT_DOMAIN } from '../../vendor/i18n';
import { createCommentPlaceholderEdit } from '../_shared/comment-placeholder-edit';

export default createCommentPlaceholderEdit( {
    label: __( 'Comment Date', TEXT_DOMAIN ),
    resolvedKey: '_resolvedDateFormatted',
    kind: 'text',
    fromCommentPreview: ( comment ) => {
        const formatted = comment.dateFormatted ?? comment.date;
        return typeof formatted === 'string' && formatted !== ''
            ? { text: formatted }
            : null;
    },
    dummyValue: { text: 'October 25, 2024' },
} );
