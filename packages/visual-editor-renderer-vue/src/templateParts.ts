/**
 * Client-side template + template-part resolution helpers.
 *
 * Mirrors the behaviour of the server-side
 * `ArtisanPackUI\VisualEditor\Resources\TemplatePartInliner` and
 * `TemplateResolver` services so the Vue renderer can produce the same
 * inlined block tree without round-tripping to the server. Hosts pass a
 * pre-fetched list of `templates` and `templateParts`; this module walks
 * the WordPress-style fallback chain and recursively splices each
 * template-part reference's blocks into the output tree.
 *
 * Recursion guard: an in-flight stack of `theme/slug` keys catches
 * cycles (header → nav → header) and a depth counter caps how deep the
 * resolution chain can go (default 10). Either guard turns the offending
 * block into a marker whose `attributes._resolutionError` records the
 * reason — the registered `core/template-part` renderer translates that
 * into a graceful empty render in production and a visible warning in
 * dev.
 */

import type { Block } from './types';

export const DEFAULT_MAX_TEMPLATE_PART_DEPTH = 10;

/**
 * Block names that reference a template part. Theme files ship
 * `core/template-part`; templates saved from the site editor store the
 * `artisanpack/template-part` fork (#822). Mirrors the server-side
 * `BlockShape::TEMPLATE_PART_NAMES`.
 */
export const TEMPLATE_PART_BLOCK_NAMES: ReadonlySet<string> = new Set([
    'core/template-part',
    'artisanpack/template-part',
]);

export type TemplatePartResolutionError =
    | 'missing-slug'
    | 'not-found'
    | 'cycle'
    | 'depth-limit';

export interface TemplatePartRecord {
    slug: string;
    theme?: string;
    /**
     * Template-part area (`header`, `footer`, `navigation-overlay`, …).
     * A navigation block's `overlay` reference only resolves against a
     * part in the {@link NAVIGATION_OVERLAY_AREA} area (#804).
     */
    area?: string;
    blocks: Block[];
}

/**
 * Navigation block names whose `overlay` attribute references a
 * navigation-overlay template part (#804). `artisanpack/navigation` is
 * the legacy stub persisted by 1.11 saves.
 */
export const NAVIGATION_BLOCK_NAMES: ReadonlySet<string> = new Set([
    'core/navigation',
    'artisanpack/navigation',
]);

/**
 * Template-part area a navigation `overlay` reference must resolve to.
 *
 * @since 1.13.0
 */
export const NAVIGATION_OVERLAY_AREA = 'navigation-overlay';

/**
 * Synthetic block appended to a navigation block's inner blocks when its
 * `overlay` part resolves. It carries the part's blocks and is routed by
 * `BlockTree` into the navigation renderer's `overlay` slot rather than
 * rendered in place, so the overlay content renders inside the open
 * drawer instead of the menu `<ul>`.
 */
export const NAVIGATION_OVERLAY_CONTENT_BLOCK = 'artisanpack/navigation-overlay-content';

/**
 * Slot name the overlay content block is routed into. A host that
 * overrides the `core/navigation` renderer reads the resolved overlay
 * part from `slots[NAVIGATION_OVERLAY_SLOT]`.
 *
 * @since 1.13.0
 */
export const NAVIGATION_OVERLAY_SLOT = 'overlay';

/**
 * Private marker stamped on every overlay-content block the inliner
 * creates. Stored content (JSON) can never carry a symbol key, so the
 * marker separates a genuine inliner-produced block from a stored block
 * that merely uses the {@link NAVIGATION_OVERLAY_CONTENT_BLOCK} name.
 * Object spread copies own enumerable symbol keys, so the marker
 * survives the downstream `{ ...block }` passes.
 */
const NAVIGATION_OVERLAY_MARKER: unique symbol = Symbol('artisanpack.navigationOverlayContent');

type MarkedBlock = Block & { [NAVIGATION_OVERLAY_MARKER]?: true };

export interface TemplateRecord {
    slug: string;
    theme?: string;
    blocks: Block[];
}

export interface InlineTemplatePartsOptions {
    parts: TemplatePartRecord[];
    defaultTheme?: string;
    maxDepth?: number;
}

interface WalkContext {
    parts: TemplatePartRecord[];
    defaultTheme: string | undefined;
    maxDepth: number;
    stack: string[];
    depth: number;
    /**
     * Overlay-only pass ({@link inlineNavigationOverlays}): descend into
     * already-resolved template parts instead of re-resolving them.
     */
    overlaysOnly: boolean;
}

/**
 * Whether `block` is an overlay-content block produced by the inliner
 * (as opposed to a stored block that only shares the name).
 *
 * @since 1.13.0
 */
export function isNavigationOverlayContent(block: Block): boolean {
    return (
        block !== null &&
        typeof block === 'object' &&
        block.name === NAVIGATION_OVERLAY_CONTENT_BLOCK &&
        (block as MarkedBlock)[NAVIGATION_OVERLAY_MARKER] === true
    );
}

/**
 * Recursively drop stored {@link NAVIGATION_OVERLAY_CONTENT_BLOCK} blocks
 * — only the inliner may create them, so a saved one is never rendered
 * or routed into the overlay slot. Inliner-produced (marked) blocks are
 * kept, so an in-memory tree that already went through
 * {@link inlineTemplateParts} passes through unchanged.
 *
 * @since 1.13.0
 */
export function stripStoredNavigationOverlayContent(tree: Block[]): Block[] {
    const out: Block[] = [];

    for (const block of tree) {
        if (block === null || typeof block !== 'object' || Array.isArray(block)) {
            continue;
        }

        if (block.name === NAVIGATION_OVERLAY_CONTENT_BLOCK) {
            if (isNavigationOverlayContent(block)) {
                out.push(block);
            }

            continue;
        }

        if (!Array.isArray(block.innerBlocks) || block.innerBlocks.length === 0) {
            out.push(block);

            continue;
        }

        const innerBlocks = stripStoredNavigationOverlayContent(block.innerBlocks);
        const changed =
            innerBlocks.length !== block.innerBlocks.length ||
            innerBlocks.some((child, index) => child !== block.innerBlocks?.[index]);

        out.push(changed ? { ...block, innerBlocks } : block);
    }

    return out;
}

/**
 * Returns the ordered chain of slugs the resolver walks for a given
 * input slug. Mirrors `TemplateResolver::fallbackChain()` on the PHP
 * side so renderers see the same fallback order their host app's saved
 * pages would resolve to.
 */
export function templateFallbackChain(slug: string): string[] {
    const chain = [slug];

    if (slug !== 'single' && slug.startsWith('single-')) {
        chain.push('single');
    } else if (slug !== 'page' && slug.startsWith('page-')) {
        chain.push('page');
    }

    if (slug !== 'index') {
        chain.push('index');
    }

    return chain;
}

export function findTemplate(
    templates: TemplateRecord[],
    slug: string,
    theme?: string
): TemplateRecord | undefined {
    if (theme === undefined || theme === '') {
        return templates.find((template) => template.slug === slug);
    }

    // Two-pass lookup: prefer an exact theme match, then fall back to an
    // unthemed record. Single-pass matching would let array order pick the
    // unthemed record over a more-specific themed one for the same slug.
    const exact = templates.find(
        (template) => template.slug === slug && template.theme === theme
    );

    if (exact !== undefined) {
        return exact;
    }

    return templates.find(
        (template) =>
            template.slug === slug && (template.theme === undefined || template.theme === '')
    );
}

export function resolveTemplate(
    templates: TemplateRecord[],
    slug: string,
    theme?: string
): TemplateRecord | undefined {
    for (const candidate of templateFallbackChain(slug)) {
        const match = findTemplate(templates, candidate, theme);

        if (match !== undefined) {
            return match;
        }
    }

    return undefined;
}

export function inlineTemplateParts(tree: Block[], options: InlineTemplatePartsOptions): Block[] {
    const context: WalkContext = {
        parts: options.parts,
        defaultTheme: options.defaultTheme,
        maxDepth: options.maxDepth ?? DEFAULT_MAX_TEMPLATE_PART_DEPTH,
        stack: [],
        depth: 0,
        overlaysOnly: false,
    };

    return walk(tree, context);
}

/**
 * Overlay-only pass: resolve the `overlay` part of every navigation block
 * that does not have one yet, without re-resolving template parts.
 *
 * `BlockTree` runs this after synced patterns are inlined so a navigation
 * that only appears inside a pattern (or a query loop's template) still
 * gets its overlay. Navigations already resolved by
 * {@link inlineTemplateParts} are skipped, so the pass is idempotent.
 * Blocks inside a newly resolved overlay part go through the full
 * inliner (template parts included), but synced patterns inside it are
 * not expanded because the pattern pass has already run.
 *
 * @since 1.13.0
 */
export function inlineNavigationOverlays(tree: Block[], options: InlineTemplatePartsOptions): Block[] {
    const context: WalkContext = {
        parts: options.parts,
        defaultTheme: options.defaultTheme,
        maxDepth: options.maxDepth ?? DEFAULT_MAX_TEMPLATE_PART_DEPTH,
        stack: [],
        depth: 0,
        overlaysOnly: true,
    };

    return walk(tree, context);
}

function walk(tree: Block[], context: WalkContext): Block[] {
    const out: Block[] = [];

    for (const block of tree) {
        if (block === null || typeof block !== 'object' || Array.isArray(block)) {
            continue;
        }

        const name = typeof block.name === 'string' ? block.name : '';

        // Overlay content the inliner already produced is kept as-is (it
        // was walked when it was created); a stored block with that name
        // is dropped so it is never rendered or routed.
        if (name === NAVIGATION_OVERLAY_CONTENT_BLOCK) {
            if (isNavigationOverlayContent(block)) {
                out.push(block);
            }

            continue;
        }

        if (TEMPLATE_PART_BLOCK_NAMES.has(name)) {
            out.push(context.overlaysOnly ? descendResolvedPart(block, context) : resolvePart(block, context));

            continue;
        }

        const inner = Array.isArray(block.innerBlocks) ? block.innerBlocks : [];
        const innerBlocks = walk(inner, context);

        // Idempotent: a navigation whose overlay is already resolved is
        // left alone, so re-running the inliner never duplicates it.
        if (NAVIGATION_BLOCK_NAMES.has(name) && !innerBlocks.some(isNavigationOverlayContent)) {
            const overlay = resolveNavigationOverlay(block, context);

            if (overlay !== null) {
                innerBlocks.push(overlay);
            }
        }

        out.push({
            ...block,
            innerBlocks,
        });
    }

    return out;
}

function resolvePart(block: Block, context: WalkContext): Block {
    const attrs = block.attributes ?? {};
    const slug = typeof attrs.slug === 'string' ? attrs.slug.trim() : '';
    let theme = typeof attrs.theme === 'string' ? attrs.theme.trim() : '';

    if (theme === '' && context.defaultTheme !== undefined) {
        theme = context.defaultTheme;
    }

    if (slug === '') {
        return markUnresolved(block, 'missing-slug', slug, theme);
    }

    if (context.depth >= context.maxDepth) {
        return markUnresolved(block, 'depth-limit', slug, theme);
    }

    const key = `${theme}/${slug}`;

    if (context.stack.includes(key)) {
        return markUnresolved(block, 'cycle', slug, theme);
    }

    const part = findPart(context.parts, slug, theme);

    if (part === undefined) {
        return markUnresolved(block, 'not-found', slug, theme);
    }

    const childContext: WalkContext = {
        ...context,
        defaultTheme: theme !== '' ? theme : context.defaultTheme,
        stack: [...context.stack, key],
        depth: context.depth + 1,
    };

    return {
        clientId: block.clientId,
        name: block.name,
        attributes: {
            ...attrs,
            slug,
            theme,
        },
        innerBlocks: walk(Array.isArray(part.blocks) ? part.blocks : [], childContext),
    };
}

/**
 * Overlay-only pass over an already-resolved template part: walk its
 * inlined blocks under the part's theme / stack / depth so the overlay
 * recursion guard sees the same context as the first pass. Unresolved
 * markers are returned untouched.
 */
function descendResolvedPart(block: Block, context: WalkContext): Block {
    const attrs = block.attributes ?? {};

    if (attrs._resolutionError !== undefined || !Array.isArray(block.innerBlocks)) {
        return block;
    }

    const slug = typeof attrs.slug === 'string' ? attrs.slug.trim() : '';
    const theme = typeof attrs.theme === 'string' && attrs.theme.trim() !== ''
        ? attrs.theme.trim()
        : context.defaultTheme ?? '';

    return {
        ...block,
        innerBlocks: walk(block.innerBlocks, {
            ...context,
            defaultTheme: theme !== '' ? theme : context.defaultTheme,
            stack: [...context.stack, `${theme}/${slug}`],
            depth: context.depth + 1,
        }),
    };
}

/**
 * Resolve a navigation block's `overlay` template part into the synthetic
 * overlay-content block (#804). Mirrors the Blade partial's fallbacks:
 * the overlay is skipped (the drawer keeps showing the menu) when the
 * overlay is switched off, the slug is empty or unknown, the part sits
 * outside the `navigation-overlay` area, it has no blocks, or resolving
 * it would cycle / exceed the depth limit.
 */
function resolveNavigationOverlay(block: Block, context: WalkContext): Block | null {
    const attrs = block.attributes ?? {};

    if (attrs.overlayMenu === 'never') {
        return null;
    }

    const slug = typeof attrs.overlay === 'string' ? attrs.overlay.trim() : '';

    if (slug === '' || context.depth >= context.maxDepth) {
        return null;
    }

    const theme = context.defaultTheme ?? '';
    const key = `${theme}/${slug}`;

    if (context.stack.includes(key)) {
        return null;
    }

    const part = findPart(context.parts, slug, theme);

    if (part === undefined || part.area !== NAVIGATION_OVERLAY_AREA) {
        return null;
    }

    const partBlocks = Array.isArray(part.blocks) ? part.blocks : [];

    if (partBlocks.length === 0) {
        return null;
    }

    const navClientId = typeof block.clientId === 'string' ? block.clientId : '';

    const overlay: MarkedBlock = {
        ...(navClientId === '' ? {} : { clientId: `${navClientId}-overlay` }),
        name: NAVIGATION_OVERLAY_CONTENT_BLOCK,
        attributes: { _slot: NAVIGATION_OVERLAY_SLOT, slug },
        // The part's blocks are fresh, so they always get the full walk.
        innerBlocks: walk(partBlocks, {
            ...context,
            stack: [...context.stack, key],
            depth: context.depth + 1,
            overlaysOnly: false,
        }),
    };

    overlay[NAVIGATION_OVERLAY_MARKER] = true;

    return overlay;
}

function findPart(
    parts: TemplatePartRecord[],
    slug: string,
    theme: string
): TemplatePartRecord | undefined {
    if (theme === '') {
        return parts.find((part) => part.slug === slug);
    }

    // Two-pass lookup matching findTemplate's contract: prefer an exact
    // theme match, then fall back to an unthemed record. Single-pass would
    // let array order pick the wrong part for the slug.
    const exact = parts.find((part) => part.slug === slug && part.theme === theme);

    if (exact !== undefined) {
        return exact;
    }

    return parts.find(
        (part) => part.slug === slug && (part.theme === undefined || part.theme === '')
    );
}

function markUnresolved(
    block: Block,
    reason: TemplatePartResolutionError,
    slug: string,
    theme: string
): Block {
    return {
        clientId: block.clientId,
        name: block.name,
        attributes: {
            ...(block.attributes ?? {}),
            slug,
            theme,
            _resolutionError: reason,
        },
        innerBlocks: [],
    };
}
