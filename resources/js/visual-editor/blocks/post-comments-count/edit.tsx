/**
 * PostCommentsCount — edit component.
 *
 * Server-rendered display block. Previews through the post-family
 * `createEntityPlaceholderEdit`: stamped `_resolvedCommentCount` first,
 * then `artisanpack/postPreview` context, then the live page entity,
 * then a labelled placeholder. Comments family fork (#519) Pass 2.
 */

import { __ } from '@wordpress/i18n';

import { TEXT_DOMAIN } from '../../vendor/i18n';
import { createEntityPlaceholderEdit } from '../_shared/entity-placeholder-edit';

export default createEntityPlaceholderEdit( {
    label: __( 'Comments Count', TEXT_DOMAIN ),
    resolvedKey: '_resolvedCommentCount',
    kind: 'text',
    fromQueryPreview: ( post ) => {
        // QueryPreviewPost may carry a `commentCount` shape pulled from
        // the resolved post envelope; render whatever the host emits.
        // eslint-disable-next-line @typescript-eslint/no-explicit-any
        const count = ( post as any ).commentCount;
        return typeof count === 'number' && Number.isFinite( count )
            ? { text: String( count ) }
            : null;
    },
    fromPostEntity: ( entity ) => {
        // eslint-disable-next-line @typescript-eslint/no-explicit-any
        const count = ( entity as any ).commentCount ?? ( entity as any )._preview?.commentCount;
        return typeof count === 'number' && Number.isFinite( count )
            ? { text: String( count ) }
            : null;
    },
    dummyValue: { text: '5' },
} );
