/**
 * Per-instance layout scope — Vue renderer (#871).
 *
 * Mirrors `LayoutSupport::container()` on the Blade renderer. The shared
 * `is-layout-{type}` class only carries a layout's defaults; the values an
 * author sets in the layout panel (content / wide size, justification,
 * orientation, wrap, grid columns) become a rule scoped to one wrapper
 * through a hashed `wp-container-<12-char-sha1>` class, the way WordPress's
 * `wp_get_layout_style()` does. The rules are pushed into a per-tree
 * accumulator that `BlockTree` flushes as one `<style data-ve-layout>` block.
 *
 * The scope is hashed from the rule body with {@link sha1Hex} exactly as
 * Blade hashes it, so the token matches byte-for-byte (kept honest by the
 * #704 markup-parity check). Identical layouts share one rule.
 *
 * The mirror lives at the matching path in the React renderer.
 *
 * @package @artisanpack-ui/visual-editor-renderer-vue
 * @since 1.15.0
 */

import { attrRecord, layoutClass, phpTrim } from './attributes';
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

/**
 * Block names whose wrapper carries a per-instance layout scope, keyed to
 * the wrapper slug. Matches the Blade partials that call
 * `LayoutSupport::containerClasses()`.
 */
const LAYOUT_SCOPE_BLOCKS: Readonly<Record<string, string>> = {
    'core/group': 'group',
    'artisanpack/group': 'group',
    'core/post-content': 'post-content',
    'artisanpack/post-content': 'post-content',
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
    resolvedLayoutClass: string
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

    if (rules.length === 0) {
        return null;
    }

    const body = rules.map(([suffix, declarations]) => `${suffix}{${declarations.join(';')};}`).join('');
    const scope = `wp-container-${sha1Hex(`${blockSlug}|${resolvedLayoutClass}|${body}`).slice(0, 12)}`;
    const selector = `.wp-block-${blockSlug}.wp-block-${blockSlug}-${resolvedLayoutClass}.${scope}`;

    return {
        className: scope,
        css: rules.map(([suffix, declarations]) => `${selector}${suffix}{${declarations.join(';')};}`).join(''),
    };
}

/**
 * Walk a tree and stamp a `_veLayoutScope` class onto every group /
 * post-content block whose layout needs a per-instance rule. The class is
 * joined onto the wrapper by the block renderer; the accumulated CSS is
 * emitted once as a `<style data-ve-layout>` block.
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
            const blockSlug = LAYOUT_SCOPE_BLOCKS[name];

            if (blockSlug === undefined || attrs === null) {
                return withAttributes(block, attrs, innerBlocks);
            }

            const container = layoutContainer(attrs, blockSlug, wrapperLayoutClass(attrs, blockSlug));

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
 * The layout class the wrapper renders with. A group whose Flex Layout
 * panel is active at the base breakpoint and stores no `layout.type`
 * renders `is-layout-flex` (#595 / #711), mirroring `group.blade.php`.
 */
function wrapperLayoutClass(attrs: Record<string, unknown>, blockSlug: string): string {
    if (blockSlug === 'group') {
        const rawType = attrRecord(attrs.layout).type;
        const storedType = typeof rawType === 'string' ? phpTrim(rawType) : '';

        if (storedType === '' && flexClassNames(attrs.artisanpackFlex).includes('ap-flex')) {
            return 'is-layout-flex';
        }
    }

    return layoutClass(attrs);
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
function cssLength(value: unknown): string | null {
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
