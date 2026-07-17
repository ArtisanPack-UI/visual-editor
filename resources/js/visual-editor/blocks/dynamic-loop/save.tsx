/**
 * Dynamic Loop save.
 *
 * Persists the authored template as `<InnerBlocks.Content />` so the
 * server-side renderer receives the tree via `renderWithInner()` and
 * iterates it per record.
 *
 * @since 1.4.0
 */

import { InnerBlocks } from '@wordpress/block-editor';

export default function DynamicLoopSave() {
    return <InnerBlocks.Content />;
}
