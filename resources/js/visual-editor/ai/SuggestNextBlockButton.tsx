/**
 * Inline "+ suggest" affordance for the block inserter (#610).
 *
 * Renders a small button that runs the ContentBlockSuggestionAgent when
 * clicked and calls back with the chosen suggestion. Always a
 * *suggestion* — never mutates the editor state itself; callers own the
 * apply flow per the AI RFC.
 */

import { useCallback, useState } from 'react';
import type { AiApiClient, BlockSuggestion } from './ai-api-client';
import { useSuggestNextBlock } from './hooks';

export interface SuggestNextBlockButtonProps {
    client: AiApiClient;
    existingBlocks: readonly unknown[];
    cursorPosition: number;
    documentType?: string | null;
    onPick: (suggestion: BlockSuggestion) => void;
    label?: string;
}

export function SuggestNextBlockButton(props: SuggestNextBlockButtonProps) {
    const { client, existingBlocks, cursorPosition, documentType, onPick, label } = props;
    const [open, setOpen] = useState(false);
    const call = useSuggestNextBlock(client);

    const handleClick = useCallback(async () => {
        setOpen(true);
        await call.run({
            existing_blocks: existingBlocks,
            cursor_position: cursorPosition,
            document_type: documentType ?? null,
        });
    }, [call, existingBlocks, cursorPosition, documentType]);

    return (
        <div className="ap-ve-ai-suggest-next">
            <button
                type="button"
                onClick={handleClick}
                disabled={call.status === 'loading'}
                data-testid="ap-ve-ai-suggest-next-trigger"
            >
                {label ?? '+ suggest next block'}
            </button>

            {open && call.status === 'loading' && (
                <span role="status" aria-live="polite">
                    Thinking…
                </span>
            )}

            {open && call.status === 'error' && call.error && (
                <div role="alert" className="ap-ve-ai-suggest-next__error">
                    {call.error.message}
                </div>
            )}

            {open && call.status === 'success' && call.result && (
                <ul className="ap-ve-ai-suggest-next__list" data-testid="ap-ve-ai-suggest-next-list">
                    {call.result.suggestions.map((s, i) => (
                        <li key={`${s.block_type}-${i}`}>
                            <button
                                type="button"
                                onClick={() => {
                                    onPick(s);
                                    setOpen(false);
                                    call.reset();
                                }}
                            >
                                <strong>{s.block_type}</strong>
                                <span> — {s.why}</span>
                            </button>
                        </li>
                    ))}
                </ul>
            )}
        </div>
    );
}
