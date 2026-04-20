/**
 * Public API for `@artisanpack-ui/visual-editor-renderer-react`.
 *
 * Auto-registers every core block renderer at import time so the simplest
 * consumer only has to render `<BlockTree tree={post.content} />`. Apps that
 * need to override a core block or ship their own can call
 * {@link registerBlockRenderer} after this import.
 */

import { registerCoreBlocks } from './blocks/registerCoreBlocks';

registerCoreBlocks();

export { BlockTree } from './BlockTree';
export type { BlockTreeProps } from './BlockTree';
export { DynamicBlock } from './DynamicBlock';
export type { DynamicBlockProps } from './DynamicBlock';
export { UnknownBlock } from './blocks/unknownBlock';
export {
    getBlockRenderer,
    getRegisteredBlockNames,
    hasBlockRenderer,
    registerBlockRenderer,
    resetBlockRegistry,
    unregisterBlockRenderer,
} from './registry';
export { registerCoreBlocks } from './blocks/registerCoreBlocks';
export type { Block, BlockRenderer, BlockRendererProps } from './types';
