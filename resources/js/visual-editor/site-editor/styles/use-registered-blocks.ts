/**
 * Registered-blocks hook.
 *
 * Fetches the block-type registry exposed at `GET /visual-editor/api/blocks`.
 * The Styles → Blocks navigator node renders only these blocks — per
 * issue #370's out-of-scope note, we do not list every Gutenberg core
 * block if it's been disabled via the `disabled_blocks` config (same
 * principle the site-editor shell's `D2_DISABLED_BLOCKS` enforces on
 * the JS side).
 */

import { __ } from '@wordpress/i18n';
import { useEffect, useRef, useState } from 'react';

import { TEXT_DOMAIN } from '../../vendor/i18n';
import {
    SiteEditorApiError,
    type SiteEditorApiConfig,
} from '../api-client';
import type { StyleBlock } from './styles-navigator';

export interface UseRegisteredBlocksResult {
    blocks: readonly StyleBlock[];
    isLoading: boolean;
    error: string | null;
}

interface BlocksEnvelope {
    blocks: ReadonlyArray<Record<string, unknown>>;
}

const READ_HEADERS: Readonly<Record<string, string>> = {
    Accept: 'application/json',
    'X-Requested-With': 'XMLHttpRequest',
};

function buildUrl(config: SiteEditorApiConfig): string {
    const base = config.apiBase.replace(/\/+$/, '');

    return `${base}/blocks`;
}

async function parseBody(response: Response): Promise<unknown> {
    const text = await response.text();

    if (text === '') {
        return null;
    }

    try {
        return JSON.parse(text);
    } catch {
        return text;
    }
}

function normalizeBlocks(envelope: unknown): readonly StyleBlock[] {
    if (envelope === null || typeof envelope !== 'object') {
        return [];
    }

    const maybe = (envelope as BlocksEnvelope).blocks;

    if (!Array.isArray(maybe)) {
        return [];
    }

    const result: StyleBlock[] = [];

    for (const entry of maybe) {
        if (entry === null || typeof entry !== 'object') {
            continue;
        }

        const row = entry as Record<string, unknown>;
        const name = typeof row.name === 'string' ? row.name : null;

        if (name === null) {
            continue;
        }

        const title = typeof row.title === 'string' ? row.title : undefined;

        result.push(title !== undefined ? { name, title } : { name });
    }

    return result;
}

export function useRegisteredBlocks(
    apiConfig: SiteEditorApiConfig,
    enabled: boolean
): UseRegisteredBlocksResult {
    const [blocks, setBlocks] = useState<readonly StyleBlock[]>([]);
    const [isLoading, setIsLoading] = useState<boolean>(false);
    const [error, setError] = useState<string | null>(null);

    const requestCounterRef = useRef(0);

    useEffect(() => {
        if (!enabled) {
            return;
        }

        const requestId = ++requestCounterRef.current;
        setIsLoading(true);
        setError(null);

        void (async () => {
            try {
                const response = await fetch(buildUrl(apiConfig), {
                    method: 'GET',
                    credentials: 'same-origin',
                    headers: READ_HEADERS,
                });

                const body = await parseBody(response);

                if (requestCounterRef.current !== requestId) {
                    return;
                }

                if (!response.ok) {
                    throw new SiteEditorApiError(
                        `Block registry request failed with status ${response.status}`,
                        response.status,
                        body
                    );
                }

                setBlocks(normalizeBlocks(body));
                setIsLoading(false);
            } catch (err: unknown) {
                if (requestCounterRef.current !== requestId) {
                    return;
                }

                const message =
                    err instanceof Error && err.message !== ''
                        ? err.message
                        : __(
                              'Failed to load registered blocks.',
                              TEXT_DOMAIN
                          );

                setError(message);
                setIsLoading(false);
            }
        })();
    }, [apiConfig, enabled]);

    return { blocks, isLoading, error };
}
