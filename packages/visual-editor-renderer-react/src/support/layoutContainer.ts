/**
 * Per-instance layout scope — React renderer (#871).
 *
 * Mirrors `LayoutSupport::container()` on the Blade renderer. The shared
 * `is-layout-{type}` class only carries a layout's defaults; the values an
 * author sets in the layout panel (content / wide size, justification,
 * orientation, wrap, grid columns) and the block's own Block spacing (M-15)
 * become a rule scoped to one wrapper through a hashed
 * `wp-container-<12-char-sha1>` class, the way WordPress's
 * `wp_get_layout_style()` does. Every block whose block.json declares
 * `supports.layout` gets one (F-3), except columns, gallery, post-template
 * and query pagination, which lay themselves out. The rules are pushed into a
 * per-tree accumulator that `BlockTree` flushes as one
 * `<style data-ve-layout>` block.
 *
 * The scope is hashed from the rule body with {@link sha1Hex} exactly as
 * Blade hashes it, so the token matches byte-for-byte (kept honest by the
 * #704 markup-parity check). Identical layouts share one rule.
 *
 * The mirror lives at the matching path in the Vue renderer.
 *
 * @package @artisanpack-ui/visual-editor-renderer-react
 * @since 1.15.0
 */

import { attrRecord, attrString, layoutClass, layoutPair, phpTrim } from './attributes';
import { presetSlug } from './presetReference';
import { safeCssValue } from './cssValue';
import { flexClassNames } from './flex-serializer';
import { sha1Hex } from './sha1';
import type { Block } from '../types';

/** Mirrors `LayoutSupport::DEFAULT_MINIMUM_COLUMN_WIDTH`. */
export const DEFAULT_MINIMUM_COLUMN_WIDTH = '12rem';

/** Mirrors `LayoutSupport::MAX_COLUMN_COUNT`. */
export const MAX_COLUMN_COUNT = 64;

/** Mirrors `LayoutSupport::CONSTRAINED_CHILD_SELECTOR`. */
const CONSTRAINED_CHILD_SELECTOR = ' > :where(:not(.alignleft):not(.alignright):not(.alignfull))';

/** Mirrors `LayoutSupport::DEFAULT_BLOCK_GAP_FALLBACK`. */
export const DEFAULT_BLOCK_GAP_FALLBACK = '0.5em';

/** Mirrors `LayoutSupport::JUSTIFICATION_CLASSES`. */
const JUSTIFICATION_CLASSES: ReadonlyArray<string> = ['left', 'center', 'right', 'space-between', 'stretch'];

/** Mirrors `LayoutSupport::ORIENTATION_CLASSES`. */
const ORIENTATION_CLASSES: ReadonlyArray<string> = ['horizontal', 'vertical'];

/**
 * How a block's wrapper resolves its layout scope. Matches the Blade
 * partials that call `LayoutSupport::containerClasses()`.
 */
interface LayoutScopeBlock {
    /** Wrapper slug without the `wp-block-` prefix. */
    slug: string;
    /**
     * The layout class the wrapper renders with: `'flex'` for blocks whose
     * block.json disallows switching away from flex, `'panel'` for the
     * group / column default that the Flex panel can flip to flex, or
     * `'stored'` for the stored `layout.type` with a flow default.
     */
    type: 'flex' | 'panel' | 'stored';
    /** Class the layout element carries instead of `wp-block-{slug}`. */
    wrapperClass?: string;
    /** Whether the block allows vertical alignment (navigation doesn't). */
    noVerticalAlignment?: boolean;
}

const GROUP: LayoutScopeBlock = { slug: 'group', type: 'panel' };
const POST_CONTENT: LayoutScopeBlock = { slug: 'post-content', type: 'stored' };
const BUTTONS: LayoutScopeBlock = { slug: 'buttons', type: 'flex' };
const COLUMN: LayoutScopeBlock = { slug: 'column', type: 'panel' };
const COVER: LayoutScopeBlock = { slug: 'cover', type: 'stored', wrapperClass: 'wp-block-cover__inner-container' };
const NAVIGATION: LayoutScopeBlock = { slug: 'navigation', type: 'flex', noVerticalAlignment: true };
const QUERY: LayoutScopeBlock = { slug: 'query', type: 'stored' };
const QUOTE: LayoutScopeBlock = { slug: 'quote', type: 'stored' };
const DETAILS: LayoutScopeBlock = { slug: 'details', type: 'stored' };
const COMMENTS_PAGINATION: LayoutScopeBlock = { slug: 'comments-pagination', type: 'flex' };
const QUERY_PAGINATION: LayoutScopeBlock = { slug: 'query-pagination', type: 'flex' };

const LAYOUT_SCOPE_BLOCKS: Readonly<Record<string, LayoutScopeBlock>> = {
    'core/group': GROUP,
    'artisanpack/group': GROUP,
    'core/post-content': POST_CONTENT,
    'artisanpack/post-content': POST_CONTENT,
    'core/buttons': BUTTONS,
    'artisanpack/buttons': BUTTONS,
    'core/column': COLUMN,
    'artisanpack/column': COLUMN,
    'core/cover': COVER,
    'artisanpack/cover': COVER,
    'core/navigation': NAVIGATION,
    'artisanpack/navigation': NAVIGATION,
    'core/query': QUERY,
    'artisanpack/query': QUERY,
    'core/quote': QUOTE,
    'artisanpack/quote': QUOTE,
    'core/details': DETAILS,
    'artisanpack/details': DETAILS,
    'artisanpack/comments-pagination': COMMENTS_PAGINATION,
    'core/query-pagination': QUERY_PAGINATION,
    'artisanpack/query-pagination': QUERY_PAGINATION,
};

type LayoutRule = [suffix: string, declarations: string[]];

export interface LayoutContainer {
    /** The `wp-container-<hash>` scope class. */
    className: string;
    /** The scoped rules for the accumulator. */
    css: string;
}

/**
 * Resolve the scope class and CSS for a block's layout, or `null` when the
 * layout declares nothing beyond its type's defaults. Mirrors
 * `LayoutSupport::container()`.
 */
export function layoutContainer(
    attributes: Record<string, unknown>,
    blockSlug: string,
    resolvedLayoutClass: string,
    wrapperClass: string | null = null,
    gapFallback: string = DEFAULT_BLOCK_GAP_FALLBACK
): LayoutContainer | null {
    const layout = attrRecord(attributes.layout);
    const type = resolvedLayoutClass.startsWith('is-layout-')
        ? resolvedLayoutClass.slice('is-layout-'.length)
        : '';

    let rules: LayoutRule[] = [];

    if (type === 'constrained') {
        rules = constrainedRules(layout);
    } else if (type === 'flex') {
        rules = flexRules(layout);
    } else if (type === 'grid') {
        rules = gridRules(layout);
    }

    rules = withGapRules(rules, type, attributes, gapFallback);

    if (rules.length === 0) {
        return null;
    }

    const body = rules.map(([suffix, declarations]) => `${suffix}{${declarations.join(';')};}`).join('');
    const hashKey = `${wrapperClass === null ? '' : `${wrapperClass}|`}${blockSlug}|${resolvedLayoutClass}|${body}`;
    const scope = `wp-container-${sha1Hex(hashKey).slice(0, 12)}`;
    const element = wrapperClass ?? `wp-block-${blockSlug}`;
    const selector = `.${element}.wp-block-${blockSlug}-${resolvedLayoutClass}.${scope}`;

    return {
        className: scope,
        css: rules.map(([suffix, declarations]) => `${selector}${suffix}{${declarations.join(';')};}`).join(''),
    };
}

/**
 * The `is-{orientation}`, `is-content-justification-{value}` and `is-nowrap`
 * modifier classes WordPress adds to a layout wrapper (F-3). Mirrors
 * `LayoutSupport::modifierClasses()`.
 */
export function layoutModifierClasses(
    attributes: Record<string, unknown>,
    orientation = true,
    justification = true
): string[] {
    const layout = attrRecord(attributes.layout);
    const classes: string[] = [];

    if (orientation && typeof layout.orientation === 'string' && ORIENTATION_CLASSES.includes(layout.orientation)) {
        classes.push(`is-${layout.orientation}`);
    }

    if (
        justification &&
        typeof layout.justifyContent === 'string' &&
        JUSTIFICATION_CLASSES.includes(layout.justifyContent)
    ) {
        classes.push(`is-content-justification-${layout.justifyContent}`);
    }

    if (layout.flexWrap === 'nowrap') {
        classes.push('is-nowrap');
    }

    return classes;
}

/**
 * The layout classes a wrapper carries, in the Blade partials' order: the
 * shared modifier and its per-block compound, the `wp-container-*` scope
 * stamped by {@link stampLayoutScopes}, then the layout modifier classes
 * (F-3).
 */
export function layoutWrapperClasses(
    attributes: Record<string, unknown>,
    blockSlug: string,
    resolvedLayoutClass: string,
    orientation = true,
    justification = true
): string[] {
    const scope = attrString(attributes._veLayoutScope);

    return [
        ...layoutPair(blockSlug, resolvedLayoutClass),
        ...(scope !== '' ? [scope] : []),
        ...layoutModifierClasses(attributes, orientation, justification),
    ];
}

/**
 * The layout class a group or column wrapper renders with: the stored type,
 * or flex when the #595 Flex panel is active at the base breakpoint and no
 * type is stored. Mirrors `group.blade.php` / `column.blade.php`.
 */
export function panelLayoutClass(attrs: Record<string, unknown>): string {
    const rawType = attrRecord(attrs.layout).type;
    const storedType = typeof rawType === 'string' ? phpTrim(rawType) : '';

    if (storedType === '' && flexClassNames(attrs.artisanpackFlex).includes('ap-flex')) {
        return 'is-layout-flex';
    }

    return layoutClass(attrs);
}

/**
 * A `blockGap` value that is safe to print, with a preset reference
 * expanded, or `null` (F-10). Mirrors `LayoutSupport::blockGapValue()`.
 */
export function blockGapValue(value: unknown): string | null {
    let raw: unknown = value;

    if (typeof raw === 'number' && Number.isFinite(raw)) {
        raw = String(raw);
    }

    if (typeof raw !== 'string') {
        return null;
    }

    let gap = phpTrim(raw);

    if (gap.startsWith('var:preset|')) {
        gap = `var(--wp--preset--${gap.slice('var:preset|'.length).split('|').map(presetSlug).join('--')})`;
    }

    return cssLength(gap);
}

/**
 * Walk a tree and stamp a `_veLayoutScope` class onto every layout block
 * whose layout needs a per-instance rule. The class is joined onto the
 * wrapper by the block renderer; the accumulated CSS is emitted once as a
 * `<style data-ve-layout>` block.
 */
export function stampLayoutScopes(tree: Block[]): { tree: Block[]; css: string } {
    const seen = new Set<string>();
    const css: string[] = [];

    function walk(nodes: Block[]): Block[] {
        return nodes.map((block) => {
            if (block === null || typeof block !== 'object') {
                return block;
            }

            const innerBlocks = Array.isArray(block.innerBlocks)
                ? walk(block.innerBlocks as Block[])
                : block.innerBlocks;

            const name = typeof block.name === 'string' ? block.name.trim() : '';
            // `_veLayoutScope` is a renderer-owned side channel. Strip any
            // value that arrived on the input tree so an author-crafted
            // block can't inject class tokens through it.
            const attrs = stripLayoutScope(attributesOf(block));
            const scopeBlock = Object.prototype.hasOwnProperty.call(LAYOUT_SCOPE_BLOCKS, name)
                ? LAYOUT_SCOPE_BLOCKS[name]
                : undefined;

            if (scopeBlock === undefined || attrs === null) {
                return withAttributes(block, attrs, innerBlocks);
            }

            const container = layoutContainer(
                scopeAttributes(attrs, scopeBlock),
                scopeBlock.slug,
                wrapperLayoutClass(attrs, scopeBlock),
                scopeBlock.wrapperClass ?? null
            );

            if (container === null) {
                return withAttributes(block, attrs, innerBlocks);
            }

            if (!seen.has(container.className)) {
                seen.add(container.className);
                css.push(container.css);
            }

            return {
                ...block,
                attributes: { ...attrs, _veLayoutScope: container.className },
                innerBlocks,
            } as Block;
        });
    }

    return { tree: walk(tree), css: css.join('') };
}

/**
 * The layout class the wrapper renders with, mirroring each Blade partial.
 */
function wrapperLayoutClass(attrs: Record<string, unknown>, scopeBlock: LayoutScopeBlock): string {
    if (scopeBlock.type === 'flex') {
        return 'is-layout-flex';
    }

    if (scopeBlock.type === 'panel') {
        return panelLayoutClass(attrs);
    }

    return layoutClass(attrs);
}

/**
 * The attributes the scope is built from. Navigation drops the vertical
 * alignment its block.json doesn't allow, and its legacy top-level
 * `orientation` / `itemsJustification` attributes fill in for the layout
 * values they predate, mirroring `navigation.blade.php`.
 */
function scopeAttributes(attrs: Record<string, unknown>, scopeBlock: LayoutScopeBlock): Record<string, unknown> {
    if (scopeBlock.noVerticalAlignment !== true) {
        return attrs;
    }

    const layout = { ...attrRecord(attrs.layout) };
    delete layout.verticalAlignment;

    if (layout.orientation === undefined || layout.orientation === null) {
        if (typeof attrs.orientation === 'string') {
            layout.orientation = attrs.orientation;
        }
    }

    if (layout.justifyContent === undefined || layout.justifyContent === null) {
        const legacy = attrs.itemsJustification;

        if (typeof legacy === 'string' && legacy !== '') {
            layout.justifyContent = legacy;
        }
    }

    return { ...attrs, layout };
}

/**
 * Add the block's own `style.spacing.blockGap` to its layout rules (M-15).
 * Mirrors `LayoutSupport::withGapRules()`.
 */
function withGapRules(
    rules: LayoutRule[],
    type: string,
    attributes: Record<string, unknown>,
    gapFallback: string
): LayoutRule[] {
    const gap = attrRecord(attrRecord(attributes.style).spacing).blockGap;
    const isAxes = gap !== null && typeof gap === 'object';
    const axes = isAxes ? attrRecord(gap) : {};

    if (type === 'flex' || type === 'grid') {
        let value: string | null;

        if (isAxes) {
            let row = blockGapValue(axes.top);
            let column = blockGapValue(axes.left);

            if (row === null && column === null) {
                return rules;
            }

            row ??= gapFallback;
            column ??= gapFallback;
            value = row === column ? row : `${row} ${column}`;
        } else {
            value = typeof gap === 'string' ? blockGapValue(gap) : null;
        }

        if (value === null) {
            return rules;
        }

        const index = rules.findIndex(([suffix]) => suffix === '');

        if (index !== -1) {
            return rules.map((rule, i): LayoutRule => (i === index ? [rule[0], [...rule[1], `gap:${value}`]] : rule));
        }

        return [...rules, ['', [`gap:${value}`]]];
    }

    if (type === 'flow' || type === 'constrained') {
        const value = isAxes ? blockGapValue(axes.top) : typeof gap === 'string' ? blockGapValue(gap) : null;

        if (value !== null) {
            return [...rules, [' > * + *', [`margin-block-start:${value}`, 'margin-block-end:0']]];
        }
    }

    return rules;
}

function constrainedRules(layout: Record<string, unknown>): LayoutRule[] {
    const contentSize = cssLength(layout.contentSize);
    const wideSize = cssLength(layout.wideSize);
    const justify = layout.justifyContent;

    const marginLeft = justify === 'left' ? '0 !important' : 'auto !important';
    const marginRight = justify === 'right' ? '0 !important' : 'auto !important';

    if (contentSize === null && wideSize === null) {
        if (justify === 'left') {
            return [[CONSTRAINED_CHILD_SELECTOR, ['margin-left:0 !important']]];
        }

        if (justify === 'right') {
            return [[CONSTRAINED_CHILD_SELECTOR, ['margin-right:0 !important']]];
        }

        return [];
    }

    return [
        [
            CONSTRAINED_CHILD_SELECTOR,
            [`max-width:${contentSize ?? wideSize}`, `margin-left:${marginLeft}`, `margin-right:${marginRight}`],
        ],
        [' > .alignwide', [`max-width:${wideSize ?? contentSize}`]],
    ];
}

function flexRules(layout: Record<string, unknown>): LayoutRule[] {
    const isVertical = layout.orientation === 'vertical';

    const justifyOptions: Record<string, string> = { left: 'flex-start', right: 'flex-end', center: 'center' };
    const verticalOptions: Record<string, string> = { top: 'flex-start', center: 'center', bottom: 'flex-end' };

    if (isVertical) {
        justifyOptions.stretch = 'stretch';
        verticalOptions['space-between'] = 'space-between';
    } else {
        justifyOptions['space-between'] = 'space-between';
        verticalOptions.stretch = 'stretch';
    }

    const justify = option(layout.justifyContent, justifyOptions);
    const vertical = option(layout.verticalAlignment, verticalOptions);

    const declarations: string[] = [];

    if (layout.flexWrap === 'nowrap') {
        declarations.push('flex-wrap:nowrap');
    }

    if (isVertical) {
        declarations.push('flex-direction:column', `align-items:${justify ?? 'flex-start'}`);

        if (vertical !== null) {
            declarations.push(`justify-content:${vertical}`);
        }
    } else {
        if (justify !== null) {
            declarations.push(`justify-content:${justify}`);
        }

        if (vertical !== null) {
            declarations.push(`align-items:${vertical}`);
        }
    }

    return declarations.length === 0 ? [] : [['', declarations]];
}

function gridRules(layout: Record<string, unknown>): LayoutRule[] {
    const count = columnCount(layout.columnCount);
    const minimum = cssLength(layout.minimumColumnWidth);

    if (count !== null && minimum !== null) {
        const track = `max(min(${minimum}, 100%), (100% - (var(--wp--style--block-gap, 0.5em) * ${count - 1})) / ${count})`;

        return [['', [`grid-template-columns:repeat(auto-fill, minmax(${track}, 1fr))`, 'container-type:inline-size']]];
    }

    if (count !== null) {
        return [['', [`grid-template-columns:repeat(${count}, minmax(0, 1fr))`]]];
    }

    const width = minimum ?? DEFAULT_MINIMUM_COLUMN_WIDTH;

    return [['', [`grid-template-columns:repeat(auto-fill, minmax(min(${width}, 100%), 1fr))`, 'container-type:inline-size']]];
}

/**
 * A stored length that is safe to splice into a `<style>` block, or
 * `null`. Mirrors `LayoutSupport::cssLength()`.
 */
export function cssLength(value: unknown): string | null {
    if (typeof value !== 'string') {
        return null;
    }

    const safe = safeCssValue(phpTrim(value));

    if (safe === null) {
        return null;
    }

    let depth = 0;

    for (const char of safe) {
        if (char === '(') {
            depth++;
        } else if (char === ')' && --depth < 0) {
            return null;
        }
    }

    return depth === 0 ? safe : null;
}

/**
 * A positive whole column count up to {@link MAX_COLUMN_COUNT}, or `null`.
 * Mirrors `LayoutSupport::columnCount()`.
 */
function columnCount(value: unknown): number | null {
    let count: unknown = value;

    if (typeof value === 'string' && /^[0-9]+$/.test(phpTrim(value))) {
        count = Number(phpTrim(value));
    }

    if (typeof count !== 'number' || !Number.isInteger(count) || count < 1 || count > MAX_COLUMN_COUNT) {
        return null;
    }

    return count;
}

function option(value: unknown, options: Record<string, string>): string | null {
    return typeof value === 'string' && Object.prototype.hasOwnProperty.call(options, value) ? options[value] : null;
}

function attributesOf(block: Block): Record<string, unknown> | null {
    const attrs = (block as { attributes?: unknown }).attributes;

    if (attrs === null || typeof attrs !== 'object' || Array.isArray(attrs)) {
        return null;
    }

    return attrs as Record<string, unknown>;
}

function stripLayoutScope(attrs: Record<string, unknown> | null): Record<string, unknown> | null {
    if (attrs === null || !('_veLayoutScope' in attrs)) {
        return attrs;
    }

    const rest: Record<string, unknown> = { ...attrs };
    delete rest._veLayoutScope;

    return rest;
}

function withAttributes(
    block: Block,
    attrs: Record<string, unknown> | null,
    innerBlocks: Block['innerBlocks']
): Block {
    if (attrs === null) {
        return { ...block, innerBlocks } as Block;
    }

    return { ...block, attributes: attrs, innerBlocks } as Block;
}
