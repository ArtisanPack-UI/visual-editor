/**
 * QueryPagination — save component.
 *
 * Ported from `@wordpress/block-library/src/query-pagination/save.js`
 * (v9.43.0): wrapper block whose saved markup is the serialized
 * inner-block tree (previous / numbers / next leaves). The renderer
 * wraps the children in the pagination `<nav>` element. Phase
 * I-Block-Fork query family (#521).
 */

import type { ReactElement } from 'react';
import { InnerBlocks } from '@wordpress/block-editor';

export default function QueryPaginationSave(): ReactElement {
    return <InnerBlocks.Content />;
}
