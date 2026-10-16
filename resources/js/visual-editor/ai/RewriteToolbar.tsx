/**
 * Selection-toolbar rewrite affordance (#613).
 *
 * Given the currently selected content + a rewrite intent (shorter,
 * formal, reading level 6, ...), calls the ContentRewriteAgent and shows
 * an accept/dismiss diff-style preview pane. Never auto-applies — the
 * caller owns the apply flow so the editor can decide whether to replace
 * the selection, insert alongside, or open a modal.
 *
 * Streaming is deliberately NOT wired here in the initial pass — the
 * shipped API client returns whole responses. When the ai package's
 * streaming transport lands for HTTP consumers, this component is where
 * the incremental preview goes.
 */

import { useCallback, useState } from 'react';
import { __, sprintf } from '@wordpress/i18n';
import { TEXT_DOMAIN } from '../vendor/i18n';
import type { AiApiClient, RewriteOutput } from './ai-api-client';
import { useRewrite } from './hooks';

/**
 * Preset rewrite intents. Built per render so the labels pick up locale
 * data loaded after this module is imported.
 */
function getPresetIntents(): Array<{ key: string; label: string }> {
    return [
        { key: 'shorter', label: __('Make shorter', TEXT_DOMAIN) },
        { key: 'longer', label: __('Expand', TEXT_DOMAIN) },
        { key: 'formal', label: __('More formal', TEXT_DOMAIN) },
        { key: 'casual', label: __('More casual', TEXT_DOMAIN) },
        { key: 'grade-6', label: __('Reading level 6', TEXT_DOMAIN) },
    ];
}

export interface RewriteToolbarProps {
    client: AiApiClient;
    content: string;
    onAccept: (result: RewriteOutput) => void;
    onCancel: () => void;
}

export function RewriteToolbar(props: RewriteToolbarProps) {
    const { client, content, onAccept, onCancel } = props;
    const [intent, setIntent] = useState<string>('');
    const call = useRewrite(client);

    const runIntent = useCallback(
        (nextIntent: string) => {
            setIntent(nextIntent);
            void call.run({ content, intent: nextIntent });
        },
        [call, content],
    );

    return (
        <div className="ap-ve-ai-rewrite" data-testid="ap-ve-ai-rewrite">
            <div className="ap-ve-ai-rewrite__intents" role="toolbar" aria-label={__('Rewrite', TEXT_DOMAIN)}>
                {getPresetIntents().map((p) => (
                    <button
                        key={p.key}
                        type="button"
                        onClick={() => runIntent(p.key)}
                        disabled={call.status === 'loading'}
                    >
                        {p.label}
                    </button>
                ))}
                <form
                    onSubmit={(e) => {
                        e.preventDefault();
                        const form = e.currentTarget;
                        const value = new FormData(form).get('custom-intent');
                        if (typeof value === 'string' && value.trim() !== '') {
                            runIntent(value.trim());
                        }
                    }}
                >
                    <label>
                        {__('Custom intent', TEXT_DOMAIN)}
                        <input
                            name="custom-intent"
                            type="text"
                            placeholder={__('e.g. more concrete', TEXT_DOMAIN)}
                        />
                    </label>
                </form>
            </div>

            {call.status === 'loading' && (
                <div role="status" aria-live="polite">
                    {sprintf(
                        /* translators: %s: the rewrite intent, e.g. "shorter". */
                        __('Rewriting for “%s”…', TEXT_DOMAIN),
                        intent,
                    )}
                </div>
            )}

            {call.status === 'error' && call.error && (
                <div role="alert">{call.error.message}</div>
            )}

            {call.status === 'success' && call.result && (
                <div className="ap-ve-ai-rewrite__preview" data-testid="ap-ve-ai-rewrite-preview">
                    <p className="ap-ve-ai-rewrite__meta">
                        {sprintf(
                            /* translators: %d: percentage of the text the rewrite changed. */
                            __('%d%% changed —', TEXT_DOMAIN),
                            Math.round(call.result.changed_ratio * 100),
                        )}{' '}
                        <em>{call.result.rationale}</em>
                    </p>
                    <pre className="ap-ve-ai-rewrite__text">{call.result.rewrite}</pre>
                    <div className="ap-ve-ai-rewrite__actions">
                        <button
                            type="button"
                            onClick={() => onAccept(call.result!)}
                            data-testid="ap-ve-ai-rewrite-accept"
                        >
                            {__('Apply rewrite', TEXT_DOMAIN)}
                        </button>
                        <button type="button" onClick={onCancel}>
                            {__('Cancel', TEXT_DOMAIN)}
                        </button>
                    </div>
                </div>
            )}
        </div>
    );
}
