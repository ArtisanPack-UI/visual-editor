/**
 * Navigation block-gap filter (#814).
 *
 * `core/navigation` declares `supports.spacing.blockGap`, but upstream
 * never serializes that value inline — it reaches the canvas only through
 * the flex layout support's per-instance `wp-container-*` rule, which
 * Gutenberg hands to `useStyleOverride`. Those overrides are emitted by
 * `EditorStyles`, which only mounts inside the `BlockCanvas` iframe. The
 * site editor renders its block list inline (#418), so the rule never
 * reached the DOM and navigation items stayed flush whatever Block
 * spacing value was picked.
 *
 * This filter writes the resolved gap straight onto the navigation
 * wrapper's inline style instead, which works on both canvas surfaces.
 * Inside the iframe it duplicates the upstream container rule with the
 * same value, so the two never disagree. The unset default lives in
 * `blocks/navigation/navigation.css`.
 *
 * Registered under `editor.BlockListBlock` and idempotent across the
 * post-editor and site-editor bootstrap paths via a page-global sentinel.
 */

import { __experimentalGetGapCSSValue as getGapCSSValue } from '@wordpress/block-editor';
import { addFilter } from '@wordpress/hooks';
import { createElement, useMemo, type ComponentType } from 'react';

const FILTER_HOOK = 'editor.BlockListBlock';
const FILTER_NAMESPACE = 'artisanpack-ui/visual-editor/navigation-block-gap';

/**
 * Fallback for a missing axis in the per-axis object form. Mirrors the
 * unset default in `navigation.css` so a half-set gap keeps the other
 * axis at the theme / upstream default rather than collapsing to `0`.
 */
export const NAVIGATION_GAP_FALLBACK = 'var(--wp--style--block-gap, 0.5em)';

const REGISTERED_KEY = Symbol.for(
    'artisanpack-ui.visual-editor.navigation-block-gap.registered'
);

interface GlobalSentinelHost {
    [REGISTERED_KEY]?: boolean;
}

interface BlockListBlockProps {
    name?: string;
    attributes?: Record<string, unknown>;
    wrapperProps?: Record<string, unknown>;
    [key: string]: unknown;
}

/**
 * Resolve a navigation block's `style.spacing.blockGap` to a CSS `gap`
 * value, expanding `var:preset|spacing|*` references. Returns `null`
 * when no gap is set.
 */
export function resolveNavigationGap(
    attributes: Record<string, unknown> | undefined
): string | null {
    const style = attributes?.style as { spacing?: { blockGap?: unknown } } | undefined;
    const blockGap = style?.spacing?.blockGap;

    if (typeof blockGap === 'string') {
        return blockGap.trim() === '' ? null : getGapCSSValue(blockGap, NAVIGATION_GAP_FALLBACK);
    }

    if (blockGap === null || typeof blockGap !== 'object') {
        return null;
    }

    const { top, left } = blockGap as { top?: unknown; left?: unknown };
    const hasTop = typeof top === 'string' && top.trim() !== '';
    const hasLeft = typeof left === 'string' && left.trim() !== '';

    if (!hasTop && !hasLeft) {
        return null;
    }

    return getGapCSSValue(
        { top: hasTop ? top : undefined, left: hasLeft ? left : undefined },
        NAVIGATION_GAP_FALLBACK
    );
}

function withNavigationBlockGap(
    BlockListBlock: ComponentType<BlockListBlockProps>
): ComponentType<BlockListBlockProps> {
    return function NavigationBlockGapWrapper(props: BlockListBlockProps): JSX.Element {
        const isNavigation = props.name === 'core/navigation';
        const gap = isNavigation ? resolveNavigationGap(props.attributes) : null;
        const { wrapperProps } = props;

        const nextWrapperProps = useMemo(() => {
            if (gap === null) {
                return wrapperProps;
            }

            const existingStyle = (wrapperProps?.style ?? {}) as Record<string, unknown>;

            return { ...wrapperProps, style: { ...existingStyle, gap } };
        }, [gap, wrapperProps]);

        if (gap === null) {
            return createElement(BlockListBlock, props);
        }

        return createElement(BlockListBlock, { ...props, wrapperProps: nextWrapperProps });
    };
}

export function registerNavigationBlockGap(): void {
    const host = globalThis as unknown as GlobalSentinelHost;

    if (host[REGISTERED_KEY] === true) {
        return;
    }

    addFilter(FILTER_HOOK, FILTER_NAMESPACE, withNavigationBlockGap);
    host[REGISTERED_KEY] = true;
}
