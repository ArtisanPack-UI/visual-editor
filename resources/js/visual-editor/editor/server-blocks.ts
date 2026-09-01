/**
 * Runtime discovery of server-rendered third-party blocks (#766).
 *
 * The build-time `import.meta.glob` in `custom-blocks.ts` only ever sees the
 * blocks committed to this package's own source tree. To let a downstream
 * package (a host app, a module, a WordPress-style plugin/theme) contribute a
 * block **without rebuilding this bundle**, the editor fetches the
 * server-registered block types from `/visual-editor/api/blocks` at boot and,
 * for every type flagged `apServerRender` that has no build-time client
 * module, synthesizes a block module: a generic edit component
 * ({@see createServerBlockEdit}) plus a `null` save (the HTML comes from
 * PHP). Those modules go through the same {@see registerCustomBlocks} helper
 * the glob path uses, so its by-name dedupe transparently skips any block a
 * first-party client module already registered.
 */

import type { AttributeSchema } from './attribute-controls';
import { registerCustomBlocks, type CustomBlockModule } from './custom-blocks';
import { createServerBlockEdit } from './server-block-edit';

const DEFAULT_API_BASE = '/visual-editor/api';

/** Headers mirroring the editor's other authenticated GET reads. */
const READ_HEADERS: Readonly<Record<string, string>> = {
    Accept: 'application/json',
    'X-Requested-With': 'XMLHttpRequest',
};

/**
 * A block-type definition as returned by `/visual-editor/api/blocks` — the
 * block.json metadata plus the `apServerRender` flag the controller stamps on
 * types that have a server renderer.
 */
export interface ServerBlockDefinition {
    readonly name?: unknown;
    readonly apServerRender?: unknown;
    readonly attributes?: unknown;
    readonly [key: string]: unknown;
}

/**
 * Keys the editor manages itself and must not forward into the
 * `registerBlockType` settings object.
 */
const INTERNAL_KEYS: ReadonlySet<string> = new Set(['apServerRender']);

function isServerRendered(definition: ServerBlockDefinition): boolean {
    return (
        definition.apServerRender === true &&
        typeof definition.name === 'string' &&
        definition.name !== ''
    );
}

/**
 * Build a registrable block module from one server block definition.
 *
 * The metadata is forwarded verbatim (minus editor-internal keys), defaulting
 * `apiVersion` to 3 so a downstream that omits it still registers cleanly.
 * `edit` is the generic server-block edit; `save` returns `null` because the
 * markup is produced by the PHP render callback.
 */
export function buildServerBlockModule(
    definition: ServerBlockDefinition
): CustomBlockModule | null {
    const name = definition.name;

    if (typeof name !== 'string' || name === '') {
        return null;
    }

    const attributes =
        typeof definition.attributes === 'object' && definition.attributes !== null
            ? (definition.attributes as Record<string, AttributeSchema>)
            : undefined;

    const metadata: Record<string, unknown> = { apiVersion: 3 };

    for (const [key, value] of Object.entries(definition)) {
        if (!INTERNAL_KEYS.has(key)) {
            metadata[key] = value;
        }
    }

    metadata.name = name;

    return {
        metadata: metadata as CustomBlockModule['metadata'],
        edit: createServerBlockEdit(name, attributes) as CustomBlockModule['edit'],
        save: (() => null) as CustomBlockModule['save'],
    };
}

/**
 * Map a list of server block definitions to registrable modules, keeping only
 * the server-rendered ones.
 */
export function synthesizeServerBlockModules(
    definitions: ReadonlyArray<ServerBlockDefinition>
): ReadonlyArray<CustomBlockModule> {
    const modules: CustomBlockModule[] = [];

    for (const definition of definitions) {
        if (!isServerRendered(definition)) {
            continue;
        }

        const module = buildServerBlockModule(definition);

        if (module !== null) {
            modules.push(module);
        }
    }

    return modules;
}

/**
 * Fetch the server-registered block-type definitions. Resolves to an empty
 * list (never rejects) so a failed or unauthenticated read can't abort editor
 * boot — server blocks are additive.
 */
export async function fetchServerBlockDefinitions(
    apiBase: string = DEFAULT_API_BASE
): Promise<ReadonlyArray<ServerBlockDefinition>> {
    const base = apiBase.replace(/\/$/, '');

    try {
        const response = await fetch(`${base}/blocks`, {
            method: 'GET',
            credentials: 'same-origin',
            headers: READ_HEADERS,
        });

        if (!response.ok) {
            return [];
        }

        const body: unknown = await response.json();
        const blocks = (body as { blocks?: unknown } | null)?.blocks;

        return Array.isArray(blocks) ? (blocks as ServerBlockDefinition[]) : [];
    } catch (error) {
        console.warn('visual-editor: failed to load server block types.', error);
        return [];
    }
}

/**
 * Fetch, synthesize, and register every server-rendered third-party block.
 *
 * Call once, after the build-time blocks have registered, so the dedupe in
 * {@see registerCustomBlocks} skips any block that already has a first-party
 * client module. Returns the names actually registered, for diagnostics.
 */
export async function registerServerRenderedBlocks(
    apiBase: string = DEFAULT_API_BASE
): Promise<ReadonlyArray<string>> {
    const definitions = await fetchServerBlockDefinitions(apiBase);
    const modules = synthesizeServerBlockModules(definitions);

    if (modules.length === 0) {
        return [];
    }

    return registerCustomBlocks(modules);
}
