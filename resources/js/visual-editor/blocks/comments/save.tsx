/**
 * Comments — save component.
 *
 * Wrapper block whose saved markup is the serialized inner-block tree
 * (`comment-template` + post-level comments metadata + pagination).
 * Comments family fork (#519).
 */

import type { ReactElement } from 'react';
import { InnerBlocks } from '@wordpress/block-editor';

export default function CommentsSave(): ReactElement {
    return <InnerBlocks.Content />;
}
