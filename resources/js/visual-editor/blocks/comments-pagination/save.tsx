/**
 * CommentsPagination — save component.
 *
 * Wrapper block whose saved markup is the serialized inner-block
 * tree. Comments family fork (#519) Pass 2.
 */

import type { ReactElement } from 'react';
import { InnerBlocks } from '@wordpress/block-editor';

export default function CommentsPaginationSave(): ReactElement {
    return <InnerBlocks.Content />;
}
