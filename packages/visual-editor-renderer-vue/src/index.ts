/**
 * Public API for `@artisanpack-ui/visual-editor-renderer-vue`.
 *
 * Auto-registers every core block renderer at import time so the simplest
 * consumer only has to render `<BlockTree :tree="post.content" />`. Apps that
 * need to override a core block or ship their own can call
 * {@link registerBlockRenderer} after this import.
 */

import { registerCoreBlocks } from './blocks/registerCoreBlocks';

registerCoreBlocks();

export { BlockTree } from './BlockTree';
export type { BlockTreeProps } from './BlockTree';
export { Template } from './Template';
export type { TemplateProps } from './Template';
export { DynamicBlock } from './DynamicBlock';
export type { DynamicBlockProps } from './DynamicBlock';
export { GlobalStyles } from './GlobalStyles';
export type { GlobalStylesProps } from './GlobalStyles';
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
export {
    DEFAULT_MAX_TEMPLATE_PART_DEPTH,
    findTemplate,
    inlineTemplateParts,
    resolveTemplate,
    templateFallbackChain,
} from './templateParts';
export type {
    InlineTemplatePartsOptions,
    TemplatePartRecord,
    TemplatePartResolutionError,
    TemplateRecord,
} from './templateParts';
export {
    DEFAULT_MAX_PATTERN_DEPTH,
    inlinePatterns,
} from './patterns';
export type {
    InlinePatternsOptions,
    PatternRecord,
    PatternResolutionError,
} from './patterns';
export type { Block, BlockRenderer, BlockRendererProps } from './types';
