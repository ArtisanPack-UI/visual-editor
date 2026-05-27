/**
 * Custom block auto-discovery.
 *
 * Host apps drop a block into
 * `resources/js/visual-editor/blocks/{block-name}/` with the conventional
 * `block.json`, `edit.tsx`, and (for static blocks) `save.tsx` files. The
 * Vite build picks them up via `import.meta.glob` and this module hands
 * the normalized modules to `registerBlockType` once per session.
 *
 * Keeping the discovery glob here — rather than inline in `editor-app.tsx`
 * — makes it straightforward to unit-test via the exported
 * `registerCustomBlocks(modules)` helper.
 */

/// <reference types="vite/client" />

import { getCategories, registerBlockType, setCategories } from '@wordpress/blocks';
import type { BlockConfiguration } from '@wordpress/blocks';

export const ARTISANPACK_CATEGORY_SLUG = 'artisanpack';

export interface CustomBlockModule {
    /**
     * Parsed `block.json` metadata. Must include a namespaced `name`.
     */
    readonly metadata: { readonly name: string } & Record<string, unknown>;
    /**
     * Editor-side React component for the block.
     */
    readonly edit: BlockConfiguration['edit'];
    /**
     * Save-side React component. Optional for server-rendered (dynamic)
     * blocks that resolve on the PHP side.
     */
    readonly save?: BlockConfiguration['save'];
    /**
     * Optional block-library icon override. Accepts the same shapes
     * `@wordpress/blocks` does — a Dashicon slug, an SVG React element,
     * or the `{ src, background, foreground }` object form. Host apps
     * use this when they want an inline SVG rather than the Dashicon
     * string declared in `block.json`, which requires `dashicons.css`
     * to be loaded.
     */
    readonly icon?: BlockConfiguration['icon'];
    /**
     * Optional deprecation chain — forwarded verbatim to
     * `registerBlockType`. Forked core blocks (`artisanpack/paragraph`,
     * etc.) must ship the full upstream deprecation array so legacy saved
     * markup deserializes cleanly.
     */
    readonly deprecated?: BlockConfiguration['deprecated'];
    /**
     * Optional `{ from, to }` transforms object — forwarded to
     * `registerBlockType`. Used by forks that want bidirectional
     * conversions with their upstream namespace (e.g.
     * `core/paragraph` ↔ `artisanpack/paragraph`).
     */
    readonly transforms?: BlockConfiguration['transforms'];
}

/**
 * Register the `artisanpack` block category on the first call.
 *
 * `setCategories` replaces the full list, so we append to whatever the
 * host (or `@wordpress/block-library`) has already seeded. Subsequent
 * calls are no-ops.
 */
export function ensureArtisanpackCategory(
    title: string = 'ArtisanPack'
): void {
    const categories = getCategories() as ReadonlyArray<{ slug: string }>;

    if (categories.some((category) => category.slug === ARTISANPACK_CATEGORY_SLUG)) {
        return;
    }

    setCategories([
        ...categories,
        { slug: ARTISANPACK_CATEGORY_SLUG, title },
    ]);
}

const registered = new Set<string>();

/**
 * Register a batch of custom block modules with `@wordpress/blocks`.
 *
 * Each module must expose a `metadata` object (from `block.json`) and an
 * `edit` component. `save` is optional — dynamic blocks omit it and rely
 * on the PHP render callback. Re-registering the same block is a no-op so
 * HMR reloads don't warn "Block X is already registered."
 */
export function registerCustomBlocks(
    modules: ReadonlyArray<CustomBlockModule>
): ReadonlyArray<string> {
    ensureArtisanpackCategory();

    const names: string[] = [];

    for (const module of modules) {
        const name = module.metadata?.name;

        if (typeof name !== 'string' || name === '') {
            console.warn(
                'visual-editor: skipping custom block with missing metadata.name.',
                module
            );
            continue;
        }

        if (registered.has(name)) {
            names.push(name);
            continue;
        }

        const { metadata, edit, save, icon, deprecated, transforms } = module;
        const { name: _metadataName, ...rest } = metadata;

        const settings: BlockConfiguration = {
            ...(rest as Partial<BlockConfiguration>),
            edit,
            ...(save !== undefined ? { save } : {}),
            ...(icon !== undefined ? { icon } : {}),
            ...(deprecated !== undefined ? { deprecated } : {}),
            ...(transforms !== undefined ? { transforms } : {}),
        } as BlockConfiguration;

        try {
            registerBlockType(name, settings);
            registered.add(name);
            names.push(name);
        } catch (error) {
            console.error(
                `visual-editor: failed to register custom block "${name}".`,
                error
            );
        }
    }

    return names;
}

interface GlobbedModule {
    readonly default?: unknown;
    readonly edit?: unknown;
    readonly save?: unknown;
    readonly metadata?: unknown;
    readonly icon?: unknown;
    readonly deprecated?: unknown;
    readonly transforms?: unknown;
}

/**
 * Discover and register every custom block under
 * `resources/js/visual-editor/blocks/{block-name}/index.ts` via Vite's
 * eager `import.meta.glob`. The glob pattern is deliberately restricted
 * to the conventional `index.ts` entrypoint so the filesystem layout is
 * predictable.
 *
 * Returns the registered block names for diagnostic logging.
 */
export function discoverAndRegisterCustomBlocks(): ReadonlyArray<string> {
    // `import.meta.glob` is a Vite-specific API that Vite transforms at
    // build time — the string literal + options object MUST appear
    // inline, otherwise Vite leaves the call in place and the runtime
    // blows up with "undefined is not a function". The `vite/client`
    // triple-slash reference at the top of this file gives TypeScript
    // the matching declaration.
    const modules = import.meta.glob<GlobbedModule>(
        '../blocks/*/index.ts',
        { eager: true }
    );

    const discovered: CustomBlockModule[] = [];

    for (const module of Object.values(modules)) {
        const resolved = resolveCustomBlockModule(module);

        if (resolved !== null) {
            discovered.push(resolved);
        }
    }

    return registerCustomBlocks(discovered);
}

function hasBlockShape(value: unknown): value is GlobbedModule {
    if (value === null || value === undefined || typeof value !== 'object') {
        return false;
    }

    const candidate = value as GlobbedModule;
    const metadata = candidate.metadata;

    return (
        metadata !== null &&
        metadata !== undefined &&
        typeof metadata === 'object' &&
        typeof (metadata as { name?: unknown }).name === 'string' &&
        typeof candidate.edit === 'function'
    );
}

function resolveCustomBlockModule(module: GlobbedModule): CustomBlockModule | null {
    // Prefer the `default` export only when it actually looks like a
    // block module (has the metadata + edit shape). Otherwise fall back
    // to the named exports on `module` itself — a default export that's
    // unrelated (e.g. a helper object) shouldn't shadow valid named
    // exports.
    const candidate: GlobbedModule = hasBlockShape(module.default)
        ? (module.default as GlobbedModule)
        : module;

    const metadata = candidate.metadata;

    if (
        metadata === null ||
        metadata === undefined ||
        typeof metadata !== 'object'
    ) {
        return null;
    }

    const name = (metadata as { name?: unknown }).name;

    if (typeof name !== 'string' || name === '') {
        return null;
    }

    const edit = candidate.edit;

    if (typeof edit !== 'function') {
        return null;
    }

    const save = candidate.save;
    const icon = candidate.icon;
    const deprecated = candidate.deprecated;
    const transforms = candidate.transforms;

    return {
        metadata: metadata as CustomBlockModule['metadata'],
        edit: edit as CustomBlockModule['edit'],
        ...(typeof save === 'function'
            ? { save: save as CustomBlockModule['save'] }
            : {}),
        ...(icon !== undefined
            ? { icon: icon as CustomBlockModule['icon'] }
            : {}),
        ...(Array.isArray(deprecated)
            ? { deprecated: deprecated as CustomBlockModule['deprecated'] }
            : {}),
        ...(typeof transforms === 'object' && transforms !== null
            ? { transforms: transforms as CustomBlockModule['transforms'] }
            : {}),
    };
}

/**
 * Test-only: reset the dedupe cache so a fresh registration pass can run.
 *
 * @internal
 */
export function __resetCustomBlockRegistrationCache(): void {
    registered.clear();
}
