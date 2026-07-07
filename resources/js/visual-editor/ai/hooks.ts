/**
 * Feature-specific hooks for the five visual-editor AI triggers.
 * Each thin-wraps `useAgentCall` over one AI client method.
 */

import { useCallback } from 'react';
import type {
    AiApiClient,
    AltTextInput,
    AltTextOutput,
    HeadingHierarchyInput,
    HeadingHierarchyOutput,
    RewriteInput,
    RewriteOutput,
    SuggestLayoutInput,
    SuggestLayoutOutput,
    SuggestNextBlockInput,
    SuggestNextBlockOutput,
} from './ai-api-client';
import { useAgentCall } from './use-agent-call';

export function useSuggestNextBlock(client: AiApiClient) {
    const invoke = useCallback(
        (input: SuggestNextBlockInput) => client.suggestNextBlock(input),
        [client],
    );
    return useAgentCall<SuggestNextBlockInput, SuggestNextBlockOutput>(invoke);
}

export function useSuggestLayout(client: AiApiClient) {
    const invoke = useCallback(
        (input: SuggestLayoutInput) => client.suggestLayout(input),
        [client],
    );
    return useAgentCall<SuggestLayoutInput, SuggestLayoutOutput>(invoke);
}

export function useAltText(client: AiApiClient) {
    const invoke = useCallback(
        (image: AltTextInput) => client.altText(image),
        [client],
    );
    return useAgentCall<AltTextInput, AltTextOutput>(invoke);
}

export function useRewrite(client: AiApiClient) {
    const invoke = useCallback(
        (input: RewriteInput) => client.rewrite(input),
        [client],
    );
    return useAgentCall<RewriteInput, RewriteOutput>(invoke);
}

export function useHeadingHierarchy(client: AiApiClient) {
    const invoke = useCallback(
        (input: HeadingHierarchyInput) => client.headingHierarchy(input),
        [client],
    );
    return useAgentCall<HeadingHierarchyInput, HeadingHierarchyOutput>(invoke);
}
