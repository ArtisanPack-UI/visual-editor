/**
 * Comment Template — save component.
 *
 * Loop wrapper whose saved markup is the serialized per-comment
 * template; the server-side `CommentInliner` clones it once per
 * resolved comment. Comments family fork (#519).
 */

import type { ReactElement } from 'react';
import { InnerBlocks } from '@wordpress/block-editor';

export default function CommentTemplateSave(): ReactElement {
    return <InnerBlocks.Content />;
}
