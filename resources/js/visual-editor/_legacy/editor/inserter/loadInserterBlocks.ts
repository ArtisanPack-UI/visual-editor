import { registerBuiltinInserterBlocks } from './builtinInserterBlocks';
import {
    getBlockFactory,
    registerInserterBlock,
    type InserterBlock,
} from './inserterRegistry';

export interface LoadInserterBlocksOptions {
    apiBase?: string;
    fetchImpl?: typeof fetch;
}

/**
 * Seeds the inserter registry with the built-in text blocks and, when an
 * `apiBase` is provided, fetches additional block metadata from
 * `GET {apiBase}/blocks` (shipped by #274). Network errors are swallowed
 * so the editor always boots with at least the built-in set.
 */
export async function loadInserterBlocks(
    options: LoadInserterBlocksOptions = {}
): Promise<void> {
    registerBuiltinInserterBlocks();

    const { apiBase, fetchImpl } = options;

    if (!apiBase) {
        return;
    }

    const fetcher = fetchImpl ?? (typeof fetch === 'function' ? fetch : null);

    if (!fetcher) {
        return;
    }

    try {
        const response = await fetcher(joinUrl(apiBase, 'blocks'));

        if (!response.ok) {
            return;
        }

        const data = (await response.json()) as { blocks?: unknown };

        if (Array.isArray(data.blocks)) {
            data.blocks.forEach((raw) => {
                const block = sanitizeInserterBlock(raw);

                if (!block) {
                    return;
                }

                // Skip entries without a client-side factory — without one,
                // the inserter actions can't produce a usable block instance,
                // so showing the entry would create dead list items.
                if (!getBlockFactory(block.name)) {
                    return;
                }

                registerInserterBlock(block);
            });
        }
    } catch {
        // Silent fallback — the built-ins are already registered.
    }
}

function sanitizeInserterBlock(raw: unknown): InserterBlock | null {
    if (!raw || typeof raw !== 'object') {
        return null;
    }

    const source = raw as Record<string, unknown>;

    if (typeof source.name !== 'string' || typeof source.title !== 'string') {
        return null;
    }

    const block: InserterBlock = {
        name: source.name,
        title: source.title,
    };

    if (typeof source.description === 'string') {
        block.description = source.description;
    }

    if (Array.isArray(source.keywords)) {
        const keywords = source.keywords.filter(
            (keyword): keyword is string => typeof keyword === 'string'
        );

        if (keywords.length > 0) {
            block.keywords = keywords;
        }
    }

    return block;
}

function joinUrl(base: string, path: string): string {
    const trimmedBase = base.replace(/\/+$/, '');
    const trimmedPath = path.replace(/^\/+/, '');
    return `${trimmedBase}/${trimmedPath}`;
}
