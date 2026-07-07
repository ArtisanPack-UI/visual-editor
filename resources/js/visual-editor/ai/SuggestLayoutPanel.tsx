/**
 * Layout picker driven by the LayoutSuggestionAgent (#611).
 *
 * Renders a "suggest layout" button; on click, submits the current
 * section's blocks + the available-pattern whitelist to the agent and
 * shows ranked matches. Never applies a pattern implicitly — the caller
 * owns the apply flow.
 */

import { useCallback } from 'react';
import type { AiApiClient, LayoutMatch } from './ai-api-client';
import { useSuggestLayout } from './hooks';

export interface SuggestLayoutPanelProps {
    client: AiApiClient;
    sectionContent: readonly unknown[];
    availablePatterns: readonly string[];
    onPick: (match: LayoutMatch) => void;
    label?: string;
}

export function SuggestLayoutPanel(props: SuggestLayoutPanelProps) {
    const { client, sectionContent, availablePatterns, onPick, label } = props;
    const call = useSuggestLayout(client);

    const handleClick = useCallback(() => {
        void call.run({
            section_content: sectionContent,
            available_patterns: availablePatterns,
        });
    }, [call, sectionContent, availablePatterns]);

    return (
        <div className="ap-ve-ai-suggest-layout">
            <button
                type="button"
                onClick={handleClick}
                disabled={call.status === 'loading'}
                data-testid="ap-ve-ai-suggest-layout-trigger"
            >
                {label ?? 'Suggest a layout'}
            </button>

            {call.status === 'loading' && <span role="status">Matching patterns…</span>}

            {call.status === 'error' && call.error && (
                <div role="alert">{call.error.message}</div>
            )}

            {call.status === 'success' && call.result && (
                <ul className="ap-ve-ai-suggest-layout__list">
                    {call.result.matches.length === 0 && (
                        <li>No matching pattern in the current library.</li>
                    )}
                    {call.result.matches.map((m) => (
                        <li key={m.pattern_slug}>
                            <button type="button" onClick={() => onPick(m)}>
                                <strong>{m.pattern_slug}</strong>
                                <span> ({Math.round(m.confidence * 100)}%)</span>
                                <div>{m.rationale}</div>
                            </button>
                        </li>
                    ))}
                </ul>
            )}
        </div>
    );
}
