/**
 * Document-inspector panel that lists heading-hierarchy issues (#614).
 *
 * Runs on demand (button click) rather than on every keystroke — the
 * heading-audit prompt is deliberately batchy. Renders `{issues}` inline
 * with a "focus block" callback so callers can scroll the offending
 * block into view when the writer clicks an issue.
 */

import { useCallback } from 'react';
import type { AiApiClient, HeadingIssue } from './ai-api-client';
import { useHeadingHierarchy } from './hooks';

export interface HeadingHierarchyPanelProps {
    client: AiApiClient;
    blocks: readonly unknown[];
    onFocusBlock?: (blockId: string) => void;
    label?: string;
}

export function HeadingHierarchyPanel(props: HeadingHierarchyPanelProps) {
    const { client, blocks, onFocusBlock, label } = props;
    const call = useHeadingHierarchy(client);

    const handleClick = useCallback(() => {
        void call.run({ blocks });
    }, [call, blocks]);

    return (
        <section className="ap-ve-ai-headings" data-testid="ap-ve-ai-headings">
            <header>
                <h3>{label ?? 'Heading hierarchy check'}</h3>
                <button
                    type="button"
                    onClick={handleClick}
                    disabled={call.status === 'loading'}
                    data-testid="ap-ve-ai-headings-run"
                >
                    {call.status === 'loading' ? 'Checking…' : 'Run check'}
                </button>
            </header>

            {call.status === 'error' && call.error && (
                <div role="alert">{call.error.message}</div>
            )}

            {call.status === 'success' && call.result && (
                <ul className="ap-ve-ai-headings__list">
                    {call.result.issues.length === 0 && (
                        <li className="ap-ve-ai-headings__clean">No heading issues found.</li>
                    )}
                    {call.result.issues.map((issue: HeadingIssue) => (
                        <li key={issue.block_id} className="ap-ve-ai-headings__item">
                            <button
                                type="button"
                                onClick={() => onFocusBlock?.(issue.block_id)}
                            >
                                <strong>{issue.issue}</strong>
                                <div>{issue.suggestion}</div>
                            </button>
                        </li>
                    ))}
                </ul>
            )}
        </section>
    );
}
