/**
 * Public surface of the post-editor's composed view (#618).
 *
 * @since 1.1.0
 */

export {
    fetchAppliedTemplate,
    type AppliedTemplate,
    type AppliedTemplateConfig,
    type AppliedTemplateMissing,
    type AppliedTemplatePart,
    type AppliedTemplateResult,
} from './api';
export {
    createDefaultTemplateBlocks,
    defaultTemplateName,
} from './default-template';
export {
    composedFallbackNotice,
    selectComposedTemplate,
    type ComposedFallbackNotice,
    type ComposedFallbackReason,
    type ComposedTemplateSelection,
} from './fallback';
export { hydrateAppliedTemplate, hydrateBlocks } from './hydrate';
export {
    splitTemplateAroundContentSlot,
    type SplitTemplateResult,
} from './split';
export { ChromeBlocks, type ChromeBlocksProps } from './ChromeBlocks';
export {
    ComposedViewRibbon,
    type ComposedViewRibbonProps,
} from './composed-view-ribbon';
export {
    useAppliedTemplate,
    type AppliedTemplateState,
    type UseAppliedTemplateOptions,
} from './use-applied-template';
export {
    useComposedFallbackToast,
    type UseComposedFallbackToastOptions,
} from './use-fallback-toast';
