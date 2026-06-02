/**
 * CommentEditLink — edit component.
 *
 * Server-rendered display block. Previews through
 * `createCommentPlaceholderEdit`: the edit link is always shown as a
 * labelled placeholder in the canvas because the editor cannot reason
 * about the viewer's capabilities — the real visibility check happens
 * server-side. Comments family fork (#519).
 */

import { createCommentPlaceholderEdit } from '../_shared/comment-placeholder-edit';

export default createCommentPlaceholderEdit( {
    label: 'Edit',
    resolvedKey: '_resolvedEditLinkLabel',
    kind: 'text',
} );
