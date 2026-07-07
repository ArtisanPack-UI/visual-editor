/**
 * Inline accept/dismiss card for AI-generated alt text (#612).
 *
 * Fires when an image block is added or its src changes AND the current
 * alt is empty. Presents the suggested alt + confidence + any warnings,
 * with explicit Accept / Dismiss actions. Never auto-applies.
 */

import { useEffect, useMemo, useRef } from 'react';
import type { AiApiClient, AltTextInput, AltTextOutput } from './ai-api-client';
import { useAltText } from './hooks';

export interface AltTextSuggestionCardProps {
    client: AiApiClient;
    image: AltTextInput;
    currentAlt: string;
    onAccept: (suggestion: AltTextOutput) => void;
    onDismiss: () => void;
    /** Skip suggestion when currentAlt is already set. Defaults to true. */
    skipIfAltSet?: boolean;
}

/**
 * Stable identity string for an image reference. Cards re-render on
 * every parent tick and `image` may be a fresh object each time; hashing
 * it into a primitive lets the effect key on the actual image, not the
 * object identity, without re-serializing on every render.
 */
function imageIdentity(image: AltTextInput): string {
    if (typeof image === 'string') {
        return `str:${image}`;
    }
    return `${image.source}:${image.value}`;
}

export function AltTextSuggestionCard(props: AltTextSuggestionCardProps) {
    const { client, image, currentAlt, onAccept, onDismiss, skipIfAltSet = true } = props;
    const call = useAltText(client);
    const shouldRun = !skipIfAltSet || currentAlt.trim() === '';
    const identity = useMemo(() => imageIdentity(image), [image]);
    // Track which image identity we've already fetched for, so
    // clear→retype→clear cycles on the alt input don't re-fire the
    // agent for the same image (review #5). Only a change in the image
    // itself resets the guard.
    const fetchedIdentityRef = useRef<string | null>(null);

    useEffect(() => {
        if (!shouldRun) {
            return;
        }
        if (fetchedIdentityRef.current === identity) {
            return;
        }
        fetchedIdentityRef.current = identity;
        void call.run(image);
        // `identity` is the primitive derived from `image`; running
        // when that changes gives us one fetch per real image. `call`
        // and `image` are deliberately not tracked here — `call`
        // identity churns every render, and `image` is captured through
        // `identity`.
        // eslint-disable-next-line react-hooks/exhaustive-deps
    }, [shouldRun, identity]);

    if (!shouldRun) {
        return null;
    }

    if (call.status === 'loading') {
        return (
            <div role="status" className="ap-ve-ai-alt-card ap-ve-ai-alt-card--loading">
                Generating alt text…
            </div>
        );
    }

    if (call.status === 'error') {
        return (
            <div role="alert" className="ap-ve-ai-alt-card ap-ve-ai-alt-card--error">
                <p>Could not suggest alt text.</p>
                <button type="button" onClick={onDismiss}>
                    Dismiss
                </button>
            </div>
        );
    }

    if (call.status !== 'success' || !call.result) {
        return null;
    }

    const suggestion = call.result;

    return (
        <div className="ap-ve-ai-alt-card" data-testid="ap-ve-ai-alt-card">
            <p className="ap-ve-ai-alt-card__label">Suggested alt text</p>
            <p className="ap-ve-ai-alt-card__text">
                {suggestion.alt_text.trim() === ''
                    ? '(empty — decorative image)'
                    : suggestion.alt_text}
            </p>
            {suggestion.warnings.length > 0 && (
                <ul className="ap-ve-ai-alt-card__warnings">
                    {suggestion.warnings.map((w, i) => (
                        <li key={i}>{w}</li>
                    ))}
                </ul>
            )}
            <div className="ap-ve-ai-alt-card__actions">
                <button
                    type="button"
                    onClick={() => onAccept(suggestion)}
                    data-testid="ap-ve-ai-alt-accept"
                >
                    Accept
                </button>
                <button type="button" onClick={onDismiss} data-testid="ap-ve-ai-alt-dismiss">
                    Dismiss
                </button>
            </div>
        </div>
    );
}
