/**
 * Public entry point for the visual-editor AI trigger surfaces (#610-#614).
 *
 * Import from `../ai` to consume the API client, hooks, and UI components
 * used by the editor's five AI-powered affordances.
 */

export { AI_FEATURE_KEYS, AiApiError, createAiApiClient } from './ai-api-client';
export type {
    AiApiClient,
    AiApiClientConfig,
    AiFeatureKey,
    AiFeaturesMap,
    AltTextInput,
    AltTextOutput,
    BlockSuggestion,
    HeadingHierarchyInput,
    HeadingHierarchyOutput,
    HeadingIssue,
    LayoutMatch,
    RewriteInput,
    RewriteOutput,
    SuggestLayoutInput,
    SuggestLayoutOutput,
    SuggestNextBlockInput,
    SuggestNextBlockOutput,
} from './ai-api-client';

export { useAiFeatures } from './use-ai-features';
export type { UseAiFeaturesResult } from './use-ai-features';

export { useAgentCall } from './use-agent-call';
export type {
    AgentCallState,
    AgentCallStatus,
    UseAgentCallReturn,
} from './use-agent-call';

export {
    useAltText,
    useHeadingHierarchy,
    useRewrite,
    useSuggestLayout,
    useSuggestNextBlock,
} from './hooks';

export { SuggestNextBlockButton } from './SuggestNextBlockButton';
export type { SuggestNextBlockButtonProps } from './SuggestNextBlockButton';

export { SuggestLayoutPanel } from './SuggestLayoutPanel';
export type { SuggestLayoutPanelProps } from './SuggestLayoutPanel';

export { AltTextSuggestionCard } from './AltTextSuggestionCard';
export type { AltTextSuggestionCardProps } from './AltTextSuggestionCard';

export { RewriteToolbar } from './RewriteToolbar';
export type { RewriteToolbarProps } from './RewriteToolbar';

export { HeadingHierarchyPanel } from './HeadingHierarchyPanel';
export type { HeadingHierarchyPanelProps } from './HeadingHierarchyPanel';
