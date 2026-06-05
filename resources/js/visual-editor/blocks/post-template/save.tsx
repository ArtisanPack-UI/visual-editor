/**
 * Post Template — save component.
 *
 * Ported from `@wordpress/block-library/src/post-template/save.js`
 * (v9.43.0): the saved markup is the serialized inner-block template, which
 * the server-side `QueryInliner` clones once per resolved post. Phase I6
 * loop / feed cluster (#414).
 */

import type { ReactElement } from 'react';
import { InnerBlocks } from '@wordpress/block-editor';

export default function PostTemplateSave(): ReactElement {
    return <InnerBlocks.Content />;
}
