/**
 * Server-side render component for dynamic blocks.
 *
 * Mirrors Gutenberg's `@wordpress/server-side-render`: on each attribute
 * change the component debounces a fetch to the `/blocks/preview` endpoint,
 * aborts any in-flight request when a newer one is issued, and renders the
 * returned HTML. While a fetch is pending the last successful render stays
 * visible (or a slot-provided loading placeholder if nothing has been
 * rendered yet) so the canvas does not flash empty on every keystroke.
 */

import { useEffect, useRef, useState, type ReactNode } from 'react';

import { ApiError, previewBlock, type PreviewBlockConfig } from './api-client';

export type ServerSideRenderStatus = 'idle' | 'loading' | 'ready' | 'error';

export interface ServerSideRenderProps {
    /** REST base (defaults to `/visual-editor/api`). */
    apiBase?: string;
    /** Fully-qualified dynamic block name (e.g. `acme/latest-posts`). */
    block: string;
    /** Block attributes sent to the server each render. */
    attributes?: Record<string, unknown>;
    /** Debounce window in ms before the next fetch fires. Defaults to 300. */
    debounceMs?: number;
    /** Optional class applied to the outer wrapper. */
    className?: string;
    /** Optional placeholder while the first fetch is in flight. */
    loadingPlaceholder?: ReactNode;
    /** Optional fallback when the server returns an empty string. */
    emptyPlaceholder?: ReactNode;
    /** Optional renderer for error states. */
    errorPlaceholder?: (error: ApiError) => ReactNode;
}

const DEFAULT_DEBOUNCE_MS = 300;
const DEFAULT_API_BASE = '/visual-editor/api';

function serializeAttributes(attributes: Record<string, unknown> | undefined): string {
    if (!attributes) {
        return '';
    }

    try {
        return JSON.stringify(attributes);
    } catch {
        return '';
    }
}

export function ServerSideRender({
    apiBase = DEFAULT_API_BASE,
    block,
    attributes,
    debounceMs = DEFAULT_DEBOUNCE_MS,
    className,
    loadingPlaceholder,
    emptyPlaceholder,
    errorPlaceholder,
}: ServerSideRenderProps): JSX.Element {
    const [html, setHtml] = useState<string | null>(null);
    const [status, setStatus] = useState<ServerSideRenderStatus>('idle');
    const [error, setError] = useState<ApiError | null>(null);

    const serializedAttributes = serializeAttributes(attributes);
    const abortRef = useRef<AbortController | null>(null);
    const timeoutRef = useRef<ReturnType<typeof setTimeout> | null>(null);

    useEffect(() => {
        const config: PreviewBlockConfig = { apiBase };

        if (timeoutRef.current !== null) {
            clearTimeout(timeoutRef.current);
        }

        if (abortRef.current !== null) {
            abortRef.current.abort();
        }

        setStatus('loading');

        timeoutRef.current = setTimeout(() => {
            const controller = new AbortController();
            abortRef.current = controller;

            previewBlock(config, {
                name: block,
                attributes,
                signal: controller.signal,
            })
                .then((response) => {
                    if (controller.signal.aborted) {
                        return;
                    }

                    setHtml(response.html);
                    setError(null);
                    setStatus('ready');
                })
                .catch((caught: unknown) => {
                    if (controller.signal.aborted) {
                        return;
                    }

                    const apiError =
                        caught instanceof ApiError
                            ? caught
                            : new ApiError('Failed to preview block.', 0, caught);

                    setError(apiError);
                    setStatus('error');
                });
        }, debounceMs);

        return () => {
            if (timeoutRef.current !== null) {
                clearTimeout(timeoutRef.current);
                timeoutRef.current = null;
            }

            if (abortRef.current !== null) {
                abortRef.current.abort();
                abortRef.current = null;
            }
        };
        // `attributes` is captured via its serialized form so deep changes
        // retrigger the effect without depending on referential identity.
        // eslint-disable-next-line react-hooks/exhaustive-deps
    }, [apiBase, block, debounceMs, serializedAttributes]);

    if (status === 'error' && error !== null) {
        return (
            <div className={className} data-status="error">
                {errorPlaceholder
                    ? errorPlaceholder(error)
                    : <p>{error.message}</p>}
            </div>
        );
    }

    if (html === null) {
        return (
            <div className={className} data-status={status}>
                {loadingPlaceholder ?? <p>Loading preview…</p>}
            </div>
        );
    }

    if (html === '') {
        return (
            <div className={className} data-status={status}>
                {emptyPlaceholder ?? null}
            </div>
        );
    }

    return (
        <div
            className={className}
            data-status={status}
            dangerouslySetInnerHTML={{ __html: html }}
        />
    );
}
