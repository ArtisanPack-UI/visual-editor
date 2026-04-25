/**
 * Client-side template + template-part resolution helpers.
 *
 * Mirrors the behaviour of the server-side
 * `ArtisanPackUI\VisualEditor\Resources\TemplatePartInliner` and
 * `TemplateResolver` services so the Vue renderer can produce the same
 * inlined block tree without round-tripping to the server. Hosts pass a
 * pre-fetched list of `templates` and `templateParts`; this module walks
 * the WordPress-style fallback chain and recursively splices each
 * `core/template-part` reference's blocks into the output tree.
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

export type TemplatePartResolutionError =
    | 'missing-slug'
    | 'not-found'
    | 'cycle'
    | 'depth-limit';

export interface TemplatePartRecord {
    slug: string;
    theme?: string;
    blocks: Block[];
}

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
    return templates.find((template) => {
        if (template.slug !== slug) {
            return false;
        }

        if (theme === undefined || theme === '') {
            return true;
        }

        return template.theme === undefined || template.theme === '' || template.theme === theme;
    });
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

        if (name === 'core/template-part') {
            out.push(resolvePart(block, context));

            continue;
        }

        const inner = Array.isArray(block.innerBlocks) ? block.innerBlocks : [];

        out.push({
            ...block,
            innerBlocks: walk(inner, context),
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
        name: 'core/template-part',
        attributes: {
            ...attrs,
            slug,
            theme,
        },
        innerBlocks: walk(Array.isArray(part.blocks) ? part.blocks : [], childContext),
    };
}

function findPart(
    parts: TemplatePartRecord[],
    slug: string,
    theme: string
): TemplatePartRecord | undefined {
    return parts.find((part) => {
        if (part.slug !== slug) {
            return false;
        }

        if (theme === '') {
            return true;
        }

        return part.theme === undefined || part.theme === '' || part.theme === theme;
    });
}

function markUnresolved(
    block: Block,
    reason: TemplatePartResolutionError,
    slug: string,
    theme: string
): Block {
    return {
        clientId: block.clientId,
        name: 'core/template-part',
        attributes: {
            ...(block.attributes ?? {}),
            slug,
            theme,
            _resolutionError: reason,
        },
        innerBlocks: [],
    };
}
