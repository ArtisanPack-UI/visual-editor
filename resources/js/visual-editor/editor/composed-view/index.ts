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
export { hydrateAppliedTemplate, hydrateBlocks } from './hydrate';
export {
    splitTemplateAroundContentSlot,
    type SplitTemplateResult,
} from './split';
export { ChromeBlocks, type ChromeBlocksProps } from './ChromeBlocks';
export {
    useAppliedTemplate,
    type AppliedTemplateState,
    type UseAppliedTemplateOptions,
} from './use-applied-template';
