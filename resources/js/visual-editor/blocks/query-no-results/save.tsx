/**
 * QueryNoResults — save component.
 *
 * Ported from `@wordpress/block-library/src/query-no-results/save.js`
 * (v9.43.0): the saved markup is the serialized inner-block template.
 * The server-side `QueryInliner` drops the wrapper when the surrounding
 * query resolves to one or more posts, so the empty-state markup is
 * only emitted when there are no results. Phase I-Block-Fork query
 * family (#521).
 */

import type { ReactElement } from 'react';
import { InnerBlocks } from '@wordpress/block-editor';

export default function QueryNoResultsSave(): ReactElement {
    return <InnerBlocks.Content />;
}
