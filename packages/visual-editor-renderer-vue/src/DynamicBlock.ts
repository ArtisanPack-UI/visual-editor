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

import { defineComponent, h, onBeforeUnmount, onMounted, ref } from 'vue';
import type { PropType } from 'vue';
import { UnknownBlock } from './blocks/unknownBlock';

type FetchFn = typeof fetch;

type DynamicBlockStatus = 'loading' | 'ready' | 'error';

export interface DynamicBlockProps {
    name: string;
    attributes: Record<string, unknown>;
    endpoint: string;
    fetchOptions?: RequestInit;
    fetchFn?: FetchFn;
}

export const DynamicBlock = defineComponent({
    name: 'DynamicBlock',
    props: {
        name: {
            type: String,
            required: true,
        },
        attributes: {
            type: Object as PropType<Record<string, unknown>>,
            required: true,
        },
        endpoint: {
            type: String,
            required: true,
        },
        fetchOptions: {
            type: Object as PropType<RequestInit>,
            default: undefined,
        },
        fetchFn: {
            type: Function as PropType<FetchFn>,
            default: undefined,
        },
    },
    setup(props) {
        const status = ref<DynamicBlockStatus>('loading');
        const html = ref('');
        const controller = new AbortController();

        onMounted(() => {
            const doFetch = props.fetchFn ?? globalThis.fetch;

            if (typeof doFetch !== 'function') {
                status.value = 'error';

                return;
            }

            const headers = new Headers(props.fetchOptions?.headers);

            if (!headers.has('Accept')) {
                headers.set('Accept', 'application/json');
            }

            if (!headers.has('Content-Type')) {
                headers.set('Content-Type', 'application/json');
            }

            const body = `{"name":${JSON.stringify(props.name)},"attributes":${JSON.stringify(props.attributes)}}`;

            doFetch(props.endpoint, {
                method: 'POST',
                credentials: 'same-origin',
                ...props.fetchOptions,
                headers,
                body,
                signal: controller.signal,
            })
                .then(async (response) => {
                    if (!response.ok) {
                        throw new Error(`Preview request failed: ${response.status}`);
                    }

                    const data = (await response.json()) as { html?: unknown };
                    const rendered = typeof data.html === 'string' ? data.html : '';

                    html.value = rendered;
                    status.value = 'ready';
                })
                .catch((error: unknown) => {
                    if ((error as { name?: string } | null)?.name === 'AbortError') {
                        return;
                    }

                    status.value = 'error';
                });
        });

        onBeforeUnmount(() => {
            controller.abort();
        });

        return () => {
            if (status.value === 'loading') {
                return h('div', {
                    'aria-busy': 'true',
                    'data-ve-dynamic-block': props.name,
                });
            }

            if (status.value === 'error') {
                return h(UnknownBlock, { name: props.name });
            }

            return h('div', {
                'data-ve-dynamic-block': props.name,
                innerHTML: html.value,
            });
        };
    },
});
