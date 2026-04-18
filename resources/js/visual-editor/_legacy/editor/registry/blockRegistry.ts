import type { ComponentType } from 'react';
import type { Block } from '../store';
import type { BlockContextValue } from '../primitives/BlockContext';
import type {
    BlockAttributeSchema,
    BlockJsonMetadata,
    BlockSupports,
    BlockStyle,
    BlockVariation,
    BlockExample,
} from './types';

// ---------------------------------------------------------------------------
// Block edit props — passed to every block's `edit` component
// ---------------------------------------------------------------------------

export interface BlockEditProps {
    clientId: string;
    attributes: Record<string, unknown>;
    block: Block;
}

// ---------------------------------------------------------------------------
// Block factory — creates a new block instance with default attributes
// ---------------------------------------------------------------------------

export type BlockFactory = () => Omit<Block, 'clientId'>;

// ---------------------------------------------------------------------------
// Block definition — unified type merging block.json metadata + JS settings
// ---------------------------------------------------------------------------

export interface BlockDefinition {
    // Required (from block.json)
    name: string;
    title: string;

    // Metadata (from block.json)
    apiVersion?: number;
    category?: string;
    description?: string;
    keywords?: string[];
    icon?: string;
    textdomain?: string;
    version?: string;

    // Nesting (from block.json)
    parent?: string[];
    ancestor?: string[];
    allowedBlocks?: string[];

    // Assets (from block.json)
    editorScript?: string | string[];
    editorStyle?: string | string[];
    style?: string | string[];
    viewScript?: string | string[];
    render?: string;

    // Configuration (from block.json)
    attributes?: Record<string, BlockAttributeSchema>;
    supports?: BlockSupports;
    selectors?: Record<string, unknown>;
    styles?: BlockStyle[];
    variations?: BlockVariation[];
    example?: BlockExample;

    // JS-only settings (provided at registration time)
    edit: ComponentType<BlockEditProps>;
    factory?: BlockFactory;
    providesContext?: (attributes: Record<string, unknown>, block: Block) => BlockContextValue;
    usesContext?: readonly string[];
}

// ---------------------------------------------------------------------------
// JS settings passed alongside block.json metadata to registerBlockType()
// ---------------------------------------------------------------------------

export interface BlockTypeSettings {
    edit: ComponentType<BlockEditProps>;
    factory?: BlockFactory;
    providesContext?: (attributes: Record<string, unknown>, block: Block) => BlockContextValue;
    usesContext?: readonly string[];
}

// ---------------------------------------------------------------------------
// Registry state
// ---------------------------------------------------------------------------

const registry = new Map<string, BlockDefinition>();
const listeners = new Set<() => void>();
let cachedSnapshot: readonly BlockDefinition[] = Object.freeze([]);

function invalidateSnapshot(): void {
    cachedSnapshot = Object.freeze(Array.from(registry.values()));
    listeners.forEach((listener) => listener());
}

// ---------------------------------------------------------------------------
// Registration API
// ---------------------------------------------------------------------------

/**
 * Registers a block type by merging block.json metadata with JS settings.
 * This is the primary registration API — all blocks should use this.
 *
 * @example
 * ```ts
 * import metadata from './block.json';
 * import MyEdit from './edit';
 *
 * registerBlockType(metadata, { edit: MyEdit });
 * ```
 */
export function registerBlockType(
    metadata: BlockJsonMetadata,
    settings: BlockTypeSettings
): void {
    if (registry.has(metadata.name)) {
        console.warn(
            `[visual-editor] Block "${metadata.name}" is already registered; ignoring duplicate registration.`
        );
        return;
    }

    // Separate providesContext from block.json metadata — the block.json
    // version is Record<string, string> (attribute name mapping), while the
    // JS version is a function. We store the JS function; the block.json
    // mapping is available via the metadata fields.
    const {
        providesContext: _jsonProvidesContext,
        usesContext: _jsonUsesContext,
        variations: rawVariations,
        ...metadataRest
    } = metadata;

    const definition: BlockDefinition = {
        ...metadataRest,
        // Normalize variations from block.json (may be string ref; ignore non-array)
        variations: Array.isArray(rawVariations) ? rawVariations : undefined,
        // Preserve block.json usesContext if JS doesn't override
        usesContext: settings.usesContext ?? (metadata.usesContext as readonly string[] | undefined),
        // Merge JS settings (edit, factory, providesContext function)
        ...settings,
    };

    registry.set(definition.name, definition);
    invalidateSnapshot();
}

// ---------------------------------------------------------------------------
// Legacy registration (deprecated)
// ---------------------------------------------------------------------------

export interface LegacyBlockDefinition {
    name: string;
    title?: string;
    edit: ComponentType<BlockEditProps>;
    providesContext?: (attributes: Record<string, unknown>, block: Block) => BlockContextValue;
    usesContext?: readonly string[];
}

/**
 * @deprecated Use `registerBlockType(metadata, settings)` instead.
 * Kept for backward compatibility during migration.
 */
export function registerBlock(definition: LegacyBlockDefinition): void {
    if (registry.has(definition.name)) {
        console.warn(
            `[visual-editor] Block "${definition.name}" is already registered; ignoring duplicate registration.`
        );
        return;
    }

    const full: BlockDefinition = {
        title: definition.name,
        ...definition,
    };

    registry.set(full.name, full);
    invalidateSnapshot();
}

// ---------------------------------------------------------------------------
// Query API
// ---------------------------------------------------------------------------

export function getBlock(name: string): BlockDefinition | undefined {
    return registry.get(name);
}

export function getRegisteredBlocks(): readonly BlockDefinition[] {
    return cachedSnapshot;
}

export function getRegisteredBlockNames(): string[] {
    return Array.from(registry.keys());
}

// ---------------------------------------------------------------------------
// Mutation API
// ---------------------------------------------------------------------------

export function unregisterBlock(name: string): void {
    if (registry.delete(name)) {
        invalidateSnapshot();
    }
}

export function clearRegistry(): void {
    registry.clear();
    invalidateSnapshot();
}

// ---------------------------------------------------------------------------
// Observer API (for UI reactivity — inserter panel, slash command, etc.)
// ---------------------------------------------------------------------------

export function subscribeRegistry(listener: () => void): () => void {
    listeners.add(listener);
    return () => {
        listeners.delete(listener);
    };
}
