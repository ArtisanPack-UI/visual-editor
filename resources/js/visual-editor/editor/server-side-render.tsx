/**
 * Server-side render component for dynamic blocks.
 *
 * Mirrors Gutenberg's `@wordpress/server-side-render`: on each attribute
 * change the component debounces a request for the block's HTML from the
 * `/blocks/preview` endpoint and renders it. While a request is pending the
 * last successful render stays visible (or a slot-provided loading
 * placeholder if nothing has been rendered yet) so the canvas does not
 * flash empty on every keystroke.
 *
 * Requests go through the shared {@link queueBlockPreview} batcher (#1010):
 * every instance that fetches at about the same time shares one batch
 * request, identical blocks share one render, and rendered HTML is cached
 * for the editor session so remounts and undo/redo paint straight away.
 * An instance doesn't request anything until it first comes within
 * {@link SERVER_SIDE_RENDER_ROOT_MARGIN} of the viewport, so blocks far
 * below the fold cost nothing until they're scrolled to.
 */

import { useEffect, useRef, useState, type ReactNode, type RefObject } from 'react';
import { __ } from '@wordpress/i18n';

import { TEXT_DOMAIN } from '../vendor/i18n';
import { ApiError } from './api-client';
import { getCachedBlockPreview, queueBlockPreview } from './block-preview-batcher';

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

/**
 * How far outside the viewport a block starts loading its preview, so
 * it's usually ready by the time it scrolls into view.
 *
 * @since 1.17.0
 */
export const SERVER_SIDE_RENDER_ROOT_MARGIN = '400px 0px';

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

function isAbortError(error: unknown): boolean {
    return error instanceof Error && error.name === 'AbortError';
}

/**
 * Flips to `true` the first time the element comes within
 * {@link SERVER_SIDE_RENDER_ROOT_MARGIN} of the viewport, then stops
 * observing. Starts `true` where `IntersectionObserver` is unavailable,
 * so those environments render straight away.
 */
function useHasBeenInView(ref: RefObject<Element>): boolean {
    const [inView, setInView] = useState(() => typeof IntersectionObserver === 'undefined');

    useEffect(() => {
        const element = ref.current;

        if (inView || element === null || typeof IntersectionObserver === 'undefined') {
            return undefined;
        }

        const observer = new IntersectionObserver(
            (entries) => {
                if (entries.some((entry) => entry.isIntersecting)) {
                    setInView(true);
                    observer.disconnect();
                }
            },
            { rootMargin: SERVER_SIDE_RENDER_ROOT_MARGIN }
        );

        observer.observe(element);

        return () => observer.disconnect();
    }, [inView, ref]);

    return inView;
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
    const serializedAttributes = serializeAttributes(attributes);
    const [html, setHtml] = useState<string | null>(
        () => getCachedBlockPreview(apiBase, { name: block, attributes: attributes ?? {} }) ?? null
    );
    const [status, setStatus] = useState<ServerSideRenderStatus>(() =>
        html !== null ? 'ready' : 'idle'
    );
    const [error, setError] = useState<ApiError | null>(null);

    const containerRef = useRef<HTMLDivElement>(null);
    const inView = useHasBeenInView(containerRef);

    useEffect(() => {
        if (!inView) {
            return undefined;
        }

        const payload = { name: block, attributes: attributes ?? {} };
        const cached = getCachedBlockPreview(apiBase, payload);

        // A render this session already has paints without a request
        // or a debounce, so undo/redo and remounts don't flash.
        if (cached !== undefined) {
            setHtml(cached);
            setError(null);
            setStatus('ready');

            return undefined;
        }

        const controller = new AbortController();

        setStatus('loading');

        const timeout = setTimeout(() => {
            queueBlockPreview(apiBase, payload, controller.signal)
                .then((rendered) => {
                    if (controller.signal.aborted) {
                        return;
                    }

                    setHtml(rendered);
                    setError(null);
                    setStatus('ready');
                })
                .catch((caught: unknown) => {
                    if (controller.signal.aborted || isAbortError(caught)) {
                        return;
                    }

                    const apiError =
                        caught instanceof ApiError
                            ? caught
                            : new ApiError(__('Failed to preview block.', TEXT_DOMAIN), 0, caught);

                    setError(apiError);
                    setStatus('error');
                });
        }, debounceMs);

        return () => {
            clearTimeout(timeout);
            controller.abort();
        };
        // `attributes` is captured via its serialized form so deep changes
        // retrigger the effect without depending on referential identity.
        // eslint-disable-next-line react-hooks/exhaustive-deps
    }, [apiBase, block, debounceMs, serializedAttributes, inView]);

    if (status === 'error' && error !== null) {
        return (
            <div ref={containerRef} className={className} data-status="error">
                {errorPlaceholder
                    ? errorPlaceholder(error)
                    : <p>{error.message}</p>}
            </div>
        );
    }

    if (html === null) {
        return (
            <div ref={containerRef} className={className} data-status={status}>
                {loadingPlaceholder ?? <p>{__('Loading preview…', TEXT_DOMAIN)}</p>}
            </div>
        );
    }

    if (html === '') {
        return (
            <div ref={containerRef} className={className} data-status={status}>
                {emptyPlaceholder ?? null}
            </div>
        );
    }

    return (
        <div
            ref={containerRef}
            className={className}
            data-status={status}
            dangerouslySetInnerHTML={{ __html: html }}
        />
    );
}
