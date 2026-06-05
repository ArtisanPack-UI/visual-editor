/**
 * Dynamic block renderer.
 *
 * When a block has no client-side renderer registered, the renderer falls back
 * to this component. On mount it POSTs `{ name, attributes }` to the
 * visual-editor's `/visual-editor/api/blocks/preview` endpoint and mounts the
 * HTML the server returns. If the request fails or the endpoint returns 404
 * (no registered `DynamicBlock`), it renders the unknown-block marker so the
 * page layout stays intact and the missing block is easy to spot.
 *
 * The endpoint is configurable via the `dynamicBlockEndpoint` prop on
 * {@link BlockTree}, so hosts on a non-standard prefix can still use the
 * renderer without patching it.
 */

import { useEffect, useMemo, useState } from 'react';
import { UnknownBlock } from './blocks/unknownBlock';

type FetchFn = typeof fetch;

export interface DynamicBlockProps {
    name: string;
    attributes: Record<string, unknown>;
    endpoint: string;
    fetchOptions?: RequestInit;
    fetchFn?: FetchFn;
}

interface DynamicBlockState {
    status: 'loading' | 'ready' | 'error';
    html: string;
}

export function DynamicBlock({
    name,
    attributes,
    endpoint,
    fetchOptions,
    fetchFn,
}: DynamicBlockProps): JSX.Element {
    const [state, setState] = useState<DynamicBlockState>({
        status: 'loading',
        html: '',
    });

    const attributesJson = useMemo(() => JSON.stringify(attributes), [attributes]);

    useEffect(() => {
        const controller = new AbortController();
        const doFetch = fetchFn ?? globalThis.fetch;

        if (typeof doFetch !== 'function') {
            setState({ status: 'error', html: '' });

            return () => controller.abort();
        }

        const headers = new Headers(fetchOptions?.headers);

        if (!headers.has('Accept')) {
            headers.set('Accept', 'application/json');
        }

        if (!headers.has('Content-Type')) {
            headers.set('Content-Type', 'application/json');
        }

        const request = doFetch(endpoint, {
            method: 'POST',
            credentials: 'same-origin',
            ...fetchOptions,
            headers,
            body: `{"name":${JSON.stringify(name)},"attributes":${attributesJson}}`,
            signal: controller.signal,
        });

        request
            .then(async (response) => {
                if (!response.ok) {
                    throw new Error(`Preview request failed: ${response.status}`);
                }

                const data = (await response.json()) as { html?: unknown };
                const html = typeof data.html === 'string' ? data.html : '';

                setState({ status: 'ready', html });
            })
            .catch((error: unknown) => {
                if ((error as { name?: string } | null)?.name === 'AbortError') {
                    return;
                }

                setState({ status: 'error', html: '' });
            });

        return () => controller.abort();
    }, [name, endpoint, fetchFn, fetchOptions, attributesJson]);

    if (state.status === 'loading') {
        return <div aria-busy="true" data-ve-dynamic-block={name} />;
    }

    if (state.status === 'error') {
        return <UnknownBlock name={name} />;
    }

    return (
        <div
            data-ve-dynamic-block={name}
            dangerouslySetInnerHTML={{ __html: state.html }}
        />
    );
}
