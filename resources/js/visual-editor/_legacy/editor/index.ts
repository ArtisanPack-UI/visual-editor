/**
 * Public API for the ArtisanPack UI Visual Editor.
 *
 * External packages import from this module to register custom blocks
 * and use editor primitives:
 *
 * ```ts
 * import {
 *     registerBlockType,
 *     RichText,
 *     InnerBlocks,
 *     blockSupports,
 * } from '@artisanpack-ui/visual-editor';
 * ```
 */

// ---------------------------------------------------------------------------
// Registry — block type registration and query
// ---------------------------------------------------------------------------

export {
    registerBlockType,
    registerBlock,
    getBlock,
    getRegisteredBlocks,
    getRegisteredBlockNames,
    unregisterBlock,
    clearRegistry,
    subscribeRegistry,
    blockSupports,
    getBlockSupport,
} from './registry';

export type {
    BlockDefinition,
    BlockEditProps,
    BlockFactory,
    BlockTypeSettings,
    BlockJsonMetadata,
    BlockSupports,
    BlockAttributeSchema,
    BlockStyle,
    BlockVariation,
    BlockExample,
    TypographySupports,
    ColorSupports,
    SpacingSupports,
    DimensionsSupports,
    BorderSupports,
    LayoutSupports,
} from './registry';

// ---------------------------------------------------------------------------
// Primitives — React components for building block edit UIs
// ---------------------------------------------------------------------------

export { RichText } from './primitives';
export type { RichTextProps } from './primitives';

export {
    InnerBlocks,
    RenderBlock,
    useInnerBlocksProps,
} from './primitives';
export type {
    InnerBlocksElementProps,
    UseInnerBlocksPropsOptions,
    UseInnerBlocksPropsReturn,
} from './primitives';

export { EditorStoreProvider, useEditorStore, useOptionalEditorStore } from './primitives';
export { ReadOnlyProvider, useReadOnly } from './primitives';

export {
    BlockContextProvider,
    useBlockContext,
    useBlockContextValue,
} from './primitives';
export type {
    BlockContextValue,
    BlockContextTypeGuard,
} from './primitives';

// ---------------------------------------------------------------------------
// Store types — for advanced consumers
// ---------------------------------------------------------------------------

export type {
    Block,
    EditorStore,
    EditorState,
    EditorStoreState,
    Selection,
    SelectionEdge,
    InsertLocation,
    MoveLocation,
} from './store';

export { createClientId } from './store';

// ---------------------------------------------------------------------------
// Block editor registry — for blocks that need cross-block editor access
// ---------------------------------------------------------------------------

export {
    getBlockEditor,
    subscribeBlockEditors,
} from './blocks/shared/blockEditorRegistry';

// ---------------------------------------------------------------------------
// Split/merge utilities — for blocks with splitting support
// ---------------------------------------------------------------------------

export {
    handleBlockEnter,
    handleBlockBackspace,
} from './blocks/shared/blockSplitMerge';
