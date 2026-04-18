import {
    getRegisteredBlocks,
    subscribeRegistry,
    getBlock,
    type BlockDefinition,
    type BlockFactory,
} from '../registry';

/**
 * Lightweight inserter view-model derived from BlockDefinition.
 * Used by the inserter panel, slash command, and filter logic.
 */
export interface InserterBlock {
    name: string;
    title: string;
    description?: string;
    keywords?: readonly string[];
}

// ---------------------------------------------------------------------------
// Factory registry — kept separate since factories are an operational concern
// (how to create a block instance) rather than a type-definition concern.
// ---------------------------------------------------------------------------

const blockFactories = new Map<string, BlockFactory>();

export { type BlockFactory };

export function registerBlockFactory(name: string, factory: BlockFactory): void {
    blockFactories.set(name, factory);
    rebuildInserterSnapshot();
}

export function getBlockFactory(name: string): BlockFactory | undefined {
    // First check the factory map (explicit registrations)
    const explicit = blockFactories.get(name);

    if (explicit) {
        return explicit;
    }

    // Fall back to the factory on the block definition (if registered via registerBlockType)
    const definition = getBlock(name);
    return definition?.factory;
}

export function clearFactories(): void {
    blockFactories.clear();
    rebuildInserterSnapshot();
}

// ---------------------------------------------------------------------------
// Inserter block list — derived from the unified block registry
// ---------------------------------------------------------------------------

const listeners = new Set<() => void>();
let cachedInserterBlocks: readonly InserterBlock[] = Object.freeze([]);
let unsubscribeRegistry: (() => void) | null = null;

function definitionToInserterBlock(def: BlockDefinition): InserterBlock {
    const block: InserterBlock = {
        name: def.name,
        title: def.title,
    };

    if (def.description) {
        block.description = def.description;
    }

    if (def.keywords && def.keywords.length > 0) {
        block.keywords = def.keywords;
    }

    return block;
}

function rebuildInserterSnapshot(): void {
    const allBlocks = getRegisteredBlocks();
    const inserterBlocks: InserterBlock[] = [];

    for (const def of allBlocks) {
        // Skip blocks that explicitly opt out of the inserter
        if (def.supports?.inserter === false) {
            continue;
        }

        // Skip blocks without a factory — the inserter can't create them
        if (!def.factory && !blockFactories.has(def.name)) {
            continue;
        }

        inserterBlocks.push(definitionToInserterBlock(def));
    }

    cachedInserterBlocks = Object.freeze(inserterBlocks);
    listeners.forEach((listener) => listener());
}

function ensureRegistrySubscription(): void {
    if (unsubscribeRegistry !== null) {
        return;
    }
    unsubscribeRegistry = subscribeRegistry(() => {
        rebuildInserterSnapshot();
    });
    // Build the initial snapshot from blocks already in the registry
    rebuildInserterSnapshot();
}

/**
 * @deprecated Use `registerBlockType(metadata, settings)` from the registry
 * module instead. This function remains for backward compatibility during
 * migration and for REST-API-loaded blocks that don't have a JS edit component.
 */
export function registerInserterBlock(block: InserterBlock): void {
    // For blocks registered via REST API without a client-side definition,
    // we don't have a full BlockDefinition. Keep these in a supplemental map.
    if (!getBlock(block.name)) {
        supplementalInserterBlocks.set(block.name, block);
    }
    rebuildInserterSnapshot();
}

const supplementalInserterBlocks = new Map<string, InserterBlock>();

export function getInserterBlocks(): readonly InserterBlock[] {
    ensureRegistrySubscription();

    // Merge supplemental blocks (from REST API) with registry-derived blocks
    if (supplementalInserterBlocks.size === 0) {
        return cachedInserterBlocks;
    }

    // Build combined list — registry blocks take precedence
    const registryNames = new Set(cachedInserterBlocks.map((b) => b.name));
    const supplemental = Array.from(supplementalInserterBlocks.values()).filter(
        (b) => !registryNames.has(b.name) && (blockFactories.has(b.name) || getBlock(b.name)?.factory)
    );

    if (supplemental.length === 0) {
        return cachedInserterBlocks;
    }

    return Object.freeze([...cachedInserterBlocks, ...supplemental]);
}

export function subscribeInserterBlocks(listener: () => void): () => void {
    ensureRegistrySubscription();
    listeners.add(listener);
    return () => {
        listeners.delete(listener);
    };
}

export function clearInserterRegistry(): void {
    blockFactories.clear();
    supplementalInserterBlocks.clear();
    if (unsubscribeRegistry) {
        unsubscribeRegistry();
        unsubscribeRegistry = null;
    }
    cachedInserterBlocks = Object.freeze([]);
    listeners.forEach((listener) => listener());
}
