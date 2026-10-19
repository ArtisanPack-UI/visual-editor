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
    keywordOverrideRules,
    type ArbitraryRule,
} from './support/flex-serializer';
import { stampColumnWidthScopes } from './support/columnWidth';
import {
    NAVIGATION_OVERLAY_CSS,
    treeHasNavigationOverlay,
} from './support/navigationOverlayCss';
import { treeNavigationCollapseCss } from './support/navigationCollapseCss';
import { stampDuotoneScopes } from './support/duotone';
import type { DuotonePresets } from './support/duotone';
import { stampEffectsScopes } from './support/effects';
import { stampHeadingAnchors } from './support/headingAnchors';
import { stampLayoutScopes } from './support/layoutContainer';
import { stampNavigationCurrent } from './support/navigationCurrent';
import { stampPhotoGridScopes } from './support/photoGrid';
import { stampResponsiveScopes } from './support/responsive';
import {
    DEFAULT_MAX_PATTERN_DEPTH,
    inlinePatterns,
} from './patterns';
import type { PatternRecord } from './patterns';
import { inlineQueries } from './queries';
import type { ResolvedQuery } from './queries';
import { getBlockRenderer } from './registry';
import { attrString, classList } from './support/attributes';
import { inlineSiteMeta } from './siteMeta';
import type { SiteMeta } from './siteMeta';
import {
    DEFAULT_MAX_TEMPLATE_PART_DEPTH,
    inlineNavigationOverlays,
    inlineTemplateParts,
    isNavigationOverlayContent,
    NAVIGATION_OVERLAY_CONTENT_BLOCK,
    stripStoredNavigationOverlayContent,
} from './templateParts';
import type { TemplatePartRecord } from './templateParts';
import { LIST_ITEM_BLOCKS, filterVisibleBlocks, stampVisibilityScopes } from './visibility';
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
    /**
     * Whether a block's Custom CSS may load resources (`url(`,
     * `image-set(`, `image(`, `src(`). Pass the host's config
     * `effects.custom_css_allow_urls` so the page matches Blade; Custom
     * CSS that loads one is dropped when `false`. Defaults to `true`.
     *
     * @since 1.15.0
     */
    customCssAllowUrls?: boolean;
    /**
     * The theme's duotone presets (`settings.color.duotone`, flat or
     * origin-keyed), so `var:preset|duotone|…` picks resolve the way the
     * Blade renderer resolves them against the active theme. Custom stop
     * lists need no presets.
     *
     * @since 1.15.0
     */
    duotonePresets?: DuotonePresets;
    /**
     * URL of the page being rendered, so navigation links / submenus
     * pointing at it get `current-menu-item` + `aria-current="page"` and
     * the ones leading to it `current-menu-ancestor`, as Blade marks them
     * from the request. Pass an absolute URL (e.g. `new URL(page.url,
     * siteUrl).href` under Inertia): with a relative one only relative
     * menu links can match. Omitted, no link is marked.
     *
     * @since 1.15.2
     */
    currentUrl?: string | null;
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
        customCssAllowUrls: {
            type: Boolean,
            default: true,
        },
        duotonePresets: {
            type: [Array, Object] as PropType<DuotonePresets>,
            default: undefined,
        },
        currentUrl: {
            type: String as PropType<string | null | undefined>,
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

            // Overlay-only pass (RN-9): a navigation that only appears
            // once synced patterns are expanded gets its `overlay` part
            // resolved here. Navigations already resolved above are
            // skipped. It runs before query inlining so a navigation
            // inside a query loop's template is resolved once and then
            // stamped per result.
            const withOverlays =
                props.templateParts === undefined || props.patterns === undefined
                    ? withPatterns
                    : inlineNavigationOverlays(withPatterns, {
                          parts: props.templateParts,
                          defaultTheme: props.defaultTheme,
                          maxDepth: props.maxTemplatePartDepth,
                      });

            const withSiteMeta = inlineSiteMeta(withOverlays, props.siteMeta);

            // Query inlining runs after the other inliners so a
            // `core/query` block reachable only through a resolved
            // template part / pattern still gets its loop expanded.
            const withQueries =
                props.queryResults === undefined
                    ? withSiteMeta
                    : inlineQueries(withSiteMeta, { queries: props.queryResults });

            // Heading anchors (#888) — stamp a unique auto-generated
            // `anchor` on every heading that lacks one, after every
            // inliner so injected headings take part, the way Blade's
            // `TocResolver` pass does.
            // Current page (#975) — mark navigation links against the
            // host-supplied URL, after every inliner so links from
            // template parts and patterns take part.
            return stampNavigationCurrent(stampHeadingAnchors(withQueries), props.currentUrl);
        });

        const flexArbitraryCss = computed(() =>
            buildArbitraryStyles(collectFlexArbitraryRules(blocks.value))
        );

        // Block Visibility (#491 · #492 · #493) — server-side
        // `TreePruner` has already dropped `hidden()` blocks from the
        // payload; here we filter defensively and stamp `_veVisScope`
        // classes + accumulate `@media` rules so screen-size rules
        // take effect at the same breakpoints as the Blade renderer.
        const visibility = computed(() =>
            stampVisibilityScopes(filterVisibleBlocks(blocks.value))
        );

        // Column responsive width (#712 · #487) — stamp the `ve-w-<hash>`
        // scope class onto every `core/column` with an explicit or
        // per-breakpoint width and emit the matching `flex-basis`/
        // `flex-grow` `!important` rules, so a column beats WP core's
        // mobile stacking rule the same way it does through Blade.
        const columnWidth = computed(() =>
            stampColumnWidthScopes(visibility.value.tree)
        );

        // Photo Grid wrapper (#714 · #594) — stamp the `has-photo-grid` +
        // hashed `photo-grid-<hash>` scope class onto every group / columns
        // / grid block with the Photo Grid panel enabled and emit the
        // matching custom-property rule, so image-bearing descendants get
        // the same aspect-ratio / object-fit / object-position sizing the
        // Blade renderer applies.
        const photoGrid = computed(() =>
            stampPhotoGridScopes(columnWidth.value.tree)
        );

        // Per-instance layout (#871) — stamp the hashed
        // `wp-container-<hash>` scope onto every group / post-content
        // block whose layout panel sets content / wide size,
        // justification, orientation, wrap or grid columns, and emit the
        // matching rules the Blade renderer pushes from
        // `LayoutSupport::containerClasses()`.
        const layout = computed(() =>
            stampLayoutScopes(photoGrid.value.tree)
        );

        // Generic responsive overrides (#886) — append the hashed
        // `ve-r-<hash>` scope to each block's `className` and emit the
        // per-breakpoint rules Blade pushes from
        // `BlockSupports::compileResponsive()`. Runs before effects so
        // the class lands ahead of `ve-fx-<hash>`, as in Blade.
        const responsive = computed(() =>
            stampResponsiveScopes(layout.value.tree)
        );

        // Block effects + Custom CSS (#881) — append the hashed
        // `ve-fx-<hash>` scope to each block's `className` and emit the
        // rules Blade pushes from `BlockSupports::compile()`.
        const effects = computed(() =>
            stampEffectsScopes(responsive.value.tree, { allowCustomCssUrls: props.customCssAllowUrls })
        );

        // Duotone (#873, F-4) — stamp the `wp-duotone-*` scope on image,
        // featured image, cover, avatar, site logo and post author blocks
        // whose media renders, and print the rules plus the hidden SVG
        // filter definitions Blade drains from `DuotoneCssAccumulator`.
        const duotone = computed(() =>
            stampDuotoneScopes(effects.value.tree, props.duotonePresets)
        );

        const hasNavigationOverlay = computed(() => treeHasNavigationOverlay(duotone.value.tree));

        // Navigation collapse breakpoint (#977) — the rules for every
        // `collapseBreakpoint` a navigation in the tree uses, each once.
        const navigationCollapseCss = computed(() => treeNavigationCollapseCss(duotone.value.tree));

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

            if (visibility.value.css !== '') {
                children.push(
                    h(
                        'style',
                        { 'data-ve-visibility': '' },
                        visibility.value.css
                    )
                );
            }

            if (columnWidth.value.css !== '') {
                children.push(
                    h(
                        'style',
                        { 'data-ve-column-width': '' },
                        columnWidth.value.css
                    )
                );
            }

            if (photoGrid.value.css !== '') {
                children.push(
                    h(
                        'style',
                        { 'data-ve-photo-grid': '' },
                        photoGrid.value.css
                    )
                );
            }

            if (layout.value.css !== '') {
                // `innerHTML` so the `>` child combinator isn't escaped to
                // `&gt;` inside the style element. The rule values are
                // whitelisted by `layoutContainer()` and carry no `<`.
                children.push(
                    h('style', {
                        'data-ve-layout': '',
                        innerHTML: layout.value.css,
                    })
                );
            }

            if (responsive.value.css !== '') {
                children.push(
                    h(
                        'style',
                        { 'data-ve-responsive': '' },
                        responsive.value.css
                    )
                );
            }

            if (effects.value.css !== '') {
                // `innerHTML` so `>` in Custom CSS isn't escaped to `&gt;`.
                // `sanitizeCustomCss()` rejects any `<`.
                children.push(
                    h('style', {
                        'data-ve-effects': '',
                        innerHTML: effects.value.css,
                    })
                );
            }

            // Navigation overlay (#804) — the drawer's breakpoint +
            // open-state rules, emitted once when any navigation block
            // has its overlay on.
            if (hasNavigationOverlay.value) {
                children.push(
                    h(
                        'style',
                        { 'data-ve-navigation-overlay': '' },
                        NAVIGATION_OVERLAY_CSS
                    )
                );
            }

            if (navigationCollapseCss.value !== '') {
                children.push(
                    h(
                        'style',
                        { 'data-ve-navigation-collapse': '' },
                        navigationCollapseCss.value
                    )
                );
            }

            if (duotone.value.css !== '') {
                // `innerHTML` so a `>` target combinator isn't escaped.
                children.push(
                    h('style', {
                        'data-ve-duotone': '',
                        innerHTML: duotone.value.css,
                    })
                );
            }

            if (duotone.value.filters !== '') {
                children.push(
                    h(
                        'svg',
                        {
                            'data-ve-duotone': '',
                            xmlns: 'http://www.w3.org/2000/svg',
                            viewBox: '0 0 0 0',
                            width: '0',
                            height: '0',
                            focusable: 'false',
                            'aria-hidden': 'true',
                            style: { visibility: 'hidden', position: 'absolute', left: '-9999px', overflow: 'hidden' },
                        },
                        [h('defs', { innerHTML: duotone.value.filters })]
                    )
                );
            }

            duotone.value.tree
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
            const serialized = serializeFlex(flex);
            if (serialized.arbitraryRules.length > 0) {
                // #949 — breakpoint keywords re-emitted after the arbitrary rules they compete with.
                rules.push(...serialized.arbitraryRules, ...keywordOverrideRules(serialized));
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

    // Only the inliner may create overlay-content blocks; a stored one
    // (e.g. reached through a synced pattern) is never rendered.
    if (name === '' || (name === NAVIGATION_OVERLAY_CONTENT_BLOCK && !isNavigationOverlayContent(block))) {
        return null;
    }

    const attributes =
        block.attributes !== null && typeof block.attributes === 'object' && !Array.isArray(block.attributes)
            ? (block.attributes as Record<string, unknown>)
            : {};

    const allInnerBlocks = Array.isArray(block.innerBlocks) ? block.innerBlocks.filter(isBlock) : [];
    const innerBlocks = allInnerBlocks.filter((child) => slotName(child) === '');
    const key = typeof block.clientId === 'string' && block.clientId !== '' ? block.clientId : `${name}-${index}`;

    const renderedChildren = innerBlocks
        .map((child, childIndex) => renderBlock(child, childIndex, endpoint, fetchOptions))
        .filter((vnode): vnode is VNode => vnode !== null);

    // Slot containers (#804) render their inner blocks into a named slot
    // on the parent renderer instead of the default slot.
    const slots: Record<string, () => VNode[]> = {};

    if (renderedChildren.length > 0) {
        slots.default = () => renderedChildren;
    }

    for (const child of allInnerBlocks) {
        const slot = slotName(child);

        if (slot === '' || slot === 'default') {
            continue;
        }

        const slotChildren = (Array.isArray(child.innerBlocks) ? child.innerBlocks.filter(isBlock) : [])
            .map((slotBlock, slotIndex) => renderBlock(slotBlock, slotIndex, endpoint, fetchOptions))
            .filter((vnode): vnode is VNode => vnode !== null);

        if (slotChildren.length === 0) {
            continue;
        }

        slots[slot] = () => slotChildren;
    }

    const renderer = getBlockRenderer(name);

    // Visibility screen-size scope class stamped by
    // `stampVisibilityScopes()`. List-item blocks take it on their own
    // `<li>` (a `<div>` wrapper inside a `<ul>` is invalid HTML, #806);
    // everything else gets the display-contents wrapper below.
    const scope = typeof attributes._veVisScope === 'string' ? attributes._veVisScope : '';
    const mergeScopeIntoRoot = scope !== '' && renderer !== undefined && LIST_ITEM_BLOCKS.has(name);
    const rendererAttributes = mergeScopeIntoRoot
        ? { ...attributes, className: classList([attrString(attributes.className), scope]) }
        : attributes;

    const element = renderer !== undefined
        ? h(
            renderer,
            {
                key,
                name,
                attributes: rendererAttributes,
                innerBlocks,
            },
            Object.keys(slots).length === 0 ? undefined : slots,
        )
        : h(DynamicBlock, {
            key,
            name,
            attributes,
            endpoint,
            fetchOptions,
        });

    // `display:contents` keeps the wrapper out of the layout in the
    // common case; the `@media` rule overrides with
    // `display:none !important` when the viewport matches.
    if (scope !== '' && !mergeScopeIntoRoot) {
        return h(
            'div',
            { key, class: scope, 'data-ve-vis-scope': '', style: { display: 'contents' } },
            [ element ],
        );
    }

    return element;
}

/**
 * Slot a block is routed into, or `''` for a regular in-place child. Only
 * the synthetic overlay-content block the template-part inliner appends
 * (carrying its private marker) is routed, so a stray `_slot` attribute
 * or a stored block with the overlay-content name is ignored.
 */
function slotName(block: Block): string {
    if (!isNavigationOverlayContent(block)) {
        return '';
    }

    const attributes = block.attributes;

    if (attributes === null || typeof attributes !== 'object' || Array.isArray(attributes)) {
        return '';
    }

    const slot = (attributes as Record<string, unknown>)._slot;

    return typeof slot === 'string' ? slot : '';
}

/**
 * Parse the incoming tree and drop stored overlay-content blocks (RN-10)
 * before any inlining runs, so only the inliner's own (marked) overlay
 * blocks ever reach the overlay slot.
 */
function normalizeTree(tree: Block[] | string | null | undefined): Block[] {
    return stripStoredNavigationOverlayContent(parseTree(tree));
}

function parseTree(tree: Block[] | string | null | undefined): Block[] {
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
