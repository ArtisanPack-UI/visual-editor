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
    COMPOSED_CHROME_MARKER,
    COMPOSED_CONTENT_SLOT_MARKER,
    composeBlocks,
} from './compose';
export { extractContentBlocks } from './extract';
export {
    useAppliedTemplate,
    type AppliedTemplateState,
    type UseAppliedTemplateOptions,
} from './use-applied-template';
