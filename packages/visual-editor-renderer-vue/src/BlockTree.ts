/**
 * Public Vue component for rendering a saved block tree.
 *
 * Walks a `{ clientId, name, attributes, innerBlocks }` block tree the visual
 * editor persists and renders it to Vue VNodes. Each block name is looked up
 * in the shared block registry; anything unregistered falls through to the
 * {@link DynamicBlock} component, which fetches the server-rendered HTML from
 * the visual-editor preview endpoint.
 *
 * `tree` accepts the array form the editor persists, a JSON-encoded string of
 * that shape, or `null`/`undefined` for an empty render.
 *
 * Optionally accepts a `templateParts` map. When provided, every
 * `core/template-part` block in the tree is replaced (pre-walk) with
 * its referenced part's blocks via {@link inlineTemplateParts}. The
 * registered `core/template-part` renderer wraps the resolved blocks in
 * a semantic element so the surrounding layout still gets the part's
 * regions.
 */

import { Fragment, computed, defineComponent, h } from 'vue';
import type { PropType, VNode } from 'vue';
import { DynamicBlock } from './DynamicBlock';
import { GlobalStyles } from './GlobalStyles';
import { UnknownBlock } from './blocks/unknownBlock';
import {
    buildArbitraryStyles,
    serializeFlex,
    type ArbitraryRule,
} from './support/flex-serializer';
import {
    DEFAULT_MAX_PATTERN_DEPTH,
    inlinePatterns,
} from './patterns';
import type { PatternRecord } from './patterns';
import { inlineQueries } from './queries';
import type { ResolvedQuery } from './queries';
import { getBlockRenderer } from './registry';
import { inlineSiteMeta } from './siteMeta';
import type { SiteMeta } from './siteMeta';
import {
    DEFAULT_MAX_TEMPLATE_PART_DEPTH,
    inlineTemplateParts,
} from './templateParts';
import type { TemplatePartRecord } from './templateParts';
import type { Block } from './types';

const DEFAULT_ENDPOINT = '/visual-editor/api/blocks/preview';

export interface BlockTreeProps {
    tree: Block[] | string | null | undefined;
    dynamicBlockEndpoint?: string;
    fetchOptions?: RequestInit;
    templateParts?: TemplatePartRecord[];
    /**
     * Synced-pattern records keyed by id. When supplied, every
     * `core/block` reference in the tree is replaced (pre-walk) with its
     * referenced pattern's blocks via {@link inlinePatterns}.
     */
    patterns?: PatternRecord[];
    defaultTheme?: string;
    maxTemplatePartDepth?: number;
    maxPatternDepth?: number;
    /**
     * Compiled global-styles CSS — same string the PHP
     * `GlobalStylesCssProvider` emits on Blade pages. When supplied,
     * `BlockTree` injects it as a `<style>` tag inside the rendered
     * fragment so the Vue-rendered page matches the canvas.
     */
    globalStylesCss?: string | null;
    /**
     * Site-meta envelope used to resolve `core/site-title`,
     * `core/site-tagline`, and `core/site-logo` blocks. Typically
     * fetched server-side from cms-framework's
     * `/api/v1/settings/site` endpoint and injected into the page
     * payload. When omitted, falls back to the global default
     * registered via `setDefaultSiteMeta`. Existing `_resolvedSite*`
     * attributes on a block always win, so hosts can override per
     * block when needed.
     */
    siteMeta?: SiteMeta | null;
    /**
     * Pre-resolved `core/query` results keyed by `queryId`. When
     * supplied, every `core/query` block in the tree is replaced with
     * one stamped copy of its inner blocks per result via
     * {@link inlineQueries}. Hosts that pre-fetch results server-side
     * pass them here.
     */
    queryResults?: ResolvedQuery[];
}

export const BlockTree = defineComponent({
    name: 'BlockTree',
    props: {
        tree: {
            type: [Array, String, null] as unknown as PropType<Block[] | string | null | undefined>,
            default: null,
        },
        dynamicBlockEndpoint: {
            type: String,
            default: DEFAULT_ENDPOINT,
        },
        fetchOptions: {
            type: Object as PropType<RequestInit>,
            default: undefined,
        },
        templateParts: {
            type: Array as PropType<TemplatePartRecord[]>,
            default: undefined,
        },
        patterns: {
            type: Array as PropType<PatternRecord[]>,
            default: undefined,
        },
        defaultTheme: {
            type: String,
            default: undefined,
        },
        maxTemplatePartDepth: {
            type: Number,
            default: DEFAULT_MAX_TEMPLATE_PART_DEPTH,
        },
        maxPatternDepth: {
            type: Number,
            default: DEFAULT_MAX_PATTERN_DEPTH,
        },
        globalStylesCss: {
            type: String as PropType<string | null | undefined>,
            default: null,
        },
        siteMeta: {
            type: Object as PropType<SiteMeta | null | undefined>,
            default: undefined,
        },
        queryResults: {
            type: Array as PropType<ResolvedQuery[]>,
            default: undefined,
        },
    },
    setup(props) {
        const blocks = computed(() => {
            const normalized = normalizeTree(props.tree);

            // Run the inliner whenever templateParts is supplied at all,
            // even when it's an empty array — the host explicitly told us
            // "no parts available," so any core/template-part references
            // in the tree should be flagged unresolved instead of
            // silently rendering an empty wrapper.
            const withParts =
                props.templateParts === undefined
                    ? normalized
                    : inlineTemplateParts(normalized, {
                          parts: props.templateParts,
                          defaultTheme: props.defaultTheme,
                          maxDepth: props.maxTemplatePartDepth,
                      });

            // Same opt-in semantics as templateParts: an empty `patterns`
            // array means "no synced patterns available," so any
            // `core/block` references resolve to an unresolved marker
            // instead of falling through to the dynamic-block fallback.
            // Pattern inlining runs after template-part inlining so a
            // part that itself contains a synced-pattern reference
            // resolves in the same pass.
            const withPatterns =
                props.patterns === undefined
                    ? withParts
                    : inlinePatterns(withParts, {
                          patterns: props.patterns,
                          maxDepth: props.maxPatternDepth,
                      });

            const withSiteMeta = inlineSiteMeta(withPatterns, props.siteMeta);

            // Query inlining runs last so a `core/query` block reachable
            // only through a resolved template part / pattern still gets
            // its loop expanded.
            return props.queryResults === undefined
                ? withSiteMeta
                : inlineQueries(withSiteMeta, { queries: props.queryResults });
        });

        const flexArbitraryCss = computed(() =>
            buildArbitraryStyles(collectFlexArbitraryRules(blocks.value))
        );

        return () => {
            const endpoint = props.dynamicBlockEndpoint ?? DEFAULT_ENDPOINT;
            const children: VNode[] = [];

            const styleNode = h(GlobalStyles, { css: props.globalStylesCss });

            children.push(styleNode);

            if (flexArbitraryCss.value !== '') {
                children.push(
                    h(
                        'style',
                        { 'data-ve-flex-arbitrary': '' },
                        flexArbitraryCss.value
                    )
                );
            }

            blocks.value
                .map((block, index) => renderBlock(block, index, endpoint, props.fetchOptions))
                .filter((vnode): vnode is VNode => vnode !== null)
                .forEach((vnode) => children.push(vnode));

            return h(Fragment, null, children);
        };
    },
});

/**
 * Walk a normalized block tree collecting every `artisanpackFlex`
 * attribute's arbitrary-value rules. The result is handed to
 * {@link buildArbitraryStyles} so the page emits a single
 * `<style data-ve-flex-arbitrary>` block matching what the Blade
 * renderer's `ResponsiveCssAccumulator` flushes on the front-end.
 */
function collectFlexArbitraryRules(blocks: Block[]): ArbitraryRule[] {
    const rules: ArbitraryRule[] = [];
    const stack: Block[] = [...blocks];

    while (stack.length > 0) {
        const block = stack.pop();
        if (!block) {
            continue;
        }

        const flex =
            block.attributes !== null &&
            typeof block.attributes === 'object' &&
            !Array.isArray(block.attributes)
                ? (block.attributes as Record<string, unknown>).artisanpackFlex
                : null;

        if (flex && typeof flex === 'object') {
            const { arbitraryRules } = serializeFlex(flex);
            if (arbitraryRules.length > 0) {
                rules.push(...arbitraryRules);
            }
        }

        if (Array.isArray(block.innerBlocks)) {
            for (const child of block.innerBlocks) {
                if (child && typeof child === 'object') {
                    stack.push(child as Block);
                }
            }
        }
    }

    return rules;
}

function renderBlock(
    block: Block,
    index: number,
    endpoint: string,
    fetchOptions: RequestInit | undefined
): VNode | null {
    const name = typeof block.name === 'string' ? block.name.trim() : '';

    if (name === '') {
        return null;
    }

    const attributes =
        block.attributes !== null && typeof block.attributes === 'object' && !Array.isArray(block.attributes)
            ? (block.attributes as Record<string, unknown>)
            : {};

    const innerBlocks = Array.isArray(block.innerBlocks) ? block.innerBlocks.filter(isBlock) : [];
    const key = typeof block.clientId === 'string' && block.clientId !== '' ? block.clientId : `${name}-${index}`;

    const renderedChildren = innerBlocks
        .map((child, childIndex) => renderBlock(child, childIndex, endpoint, fetchOptions))
        .filter((vnode): vnode is VNode => vnode !== null);

    const renderer = getBlockRenderer(name);

    if (renderer !== undefined) {
        const slots =
            renderedChildren.length === 0
                ? undefined
                : { default: () => renderedChildren };

        return h(
            renderer,
            {
                key,
                name,
                attributes,
                innerBlocks,
            },
            slots
        );
    }

    return h(DynamicBlock, {
        key,
        name,
        attributes,
        endpoint,
        fetchOptions,
    });
}

function normalizeTree(tree: Block[] | string | null | undefined): Block[] {
    if (tree === null || tree === undefined) {
        return [];
    }

    if (typeof tree === 'string') {
        try {
            const parsed = JSON.parse(tree);

            return Array.isArray(parsed) ? parsed.filter(isBlock) : [];
        } catch {
            return [];
        }
    }

    return Array.isArray(tree) ? tree.filter(isBlock) : [];
}

function isBlock(value: unknown): value is Block {
    return (
        value !== null &&
        typeof value === 'object' &&
        !Array.isArray(value) &&
        typeof (value as { name?: unknown }).name === 'string'
    );
}

export { UnknownBlock };
