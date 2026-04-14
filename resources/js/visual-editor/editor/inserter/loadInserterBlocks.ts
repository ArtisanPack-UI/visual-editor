import { registerBuiltinInserterBlocks } from './builtinInserterBlocks';
import { registerInserterBlock, type InserterBlock } from './inserterRegistry';

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

        const data = (await response.json()) as { blocks?: InserterBlock[] };

        if (Array.isArray(data.blocks)) {
            data.blocks.forEach((block) => {
                if (block && typeof block.name === 'string' && typeof block.title === 'string') {
                    registerInserterBlock(block);
                }
            });
        }
    } catch {
        // Silent fallback — the built-ins are already registered.
    }
}

function joinUrl(base: string, path: string): string {
    const trimmedBase = base.replace(/\/+$/, '');
    const trimmedPath = path.replace(/^\/+/, '');
    return `${trimmedBase}/${trimmedPath}`;
}
