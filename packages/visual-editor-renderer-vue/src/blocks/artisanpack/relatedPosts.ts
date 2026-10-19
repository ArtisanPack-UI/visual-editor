/**
 * Vue renderer for the `artisanpack/related-posts` block (#501).
 *
 * Mirrors the Blade partial and the React renderer. Server-side
 * `QueryInliner` resolves N related posts for the host entry and
 * clones the saved inner-block tree once per result with `_resolved*`
 * stamps applied through `PostResolver`. When zero results matched the
 * resolver, this renderer emits nothing so the surrounding layout
 * collapses cleanly.
 *
 * #964 — when the related items sit directly under the block (one
 * synthetic `core/post-template-item` per result), a grid
 * `displayLayout` makes the wrapper Post Template's grid
 * (`is-layout-grid` + `columns-N`), so post-template.css supplies the
 * tracks, the Block spacing gap and the responsive collapse. A nested
 * Post Template owns the layout itself, so the wrapper stays plain.
 */

import { defineComponent, h, type VNode } from 'vue';

import {
    attrInt,
    attrRecord,
    attrString,
    classList,
    layoutPair,
} from '../../support/attributes';
import { applyBlockGap } from '../../support/blockGap';
import type { Block } from '../../types';
import { blockRendererProps } from '../shared';

/**
 * Whether the block lays its items out itself: a grid `displayLayout`
 * and no nested Post Template. Returns the clamped column count, or
 * `null` for the plain (list) wrapper. Mirrors `related-posts.blade.php`.
 */
export function relatedPostsGridColumns(
    attributes: Record<string, unknown>,
    innerBlocks: ReadonlyArray<Block>
): number | null {
    const hasPostTemplate = innerBlocks.some(
        (block) =>
            block.name === 'core/post-template' ||
            block.name === 'artisanpack/post-template'
    );
    const displayLayout = attrRecord(attributes.displayLayout);

    if (hasPostTemplate || displayLayout.type !== 'grid') {
        return null;
    }

    const columns = attrInt(displayLayout.columns ?? attributes.numColumns, 3);

    return Math.max(1, Math.min(12, columns));
}

export const RelatedPostsBlock = defineComponent({
    name: 'RelatedPostsBlock',
    props: blockRendererProps,
    setup(props, { slots }) {
        return (): VNode | null => {
            const items = attrInt(props.attributes._resolvedItems, 0);

            if (items <= 0) {
                return null;
            }

            const className = attrString(props.attributes.className);
            const columns = relatedPostsGridColumns(
                props.attributes,
                props.innerBlocks ?? []
            );
            const attrs: Record<string, unknown> = {
                class: classList([
                    'ap-related-posts',
                    ...(columns === null
                        ? []
                        : [
                              ...layoutPair('artisanpack-related-posts', 'is-layout-grid'),
                              `columns-${columns}`,
                          ]),
                    className,
                ]),
            };
            const gapStyle = applyBlockGap(props.attributes);

            // Only set `style` when there is a gap: Vue's SSR prints
            // `style=""` for an empty object, which React omits.
            if (Object.keys(gapStyle).length > 0) {
                attrs.style = gapStyle;
            }

            return h('div', attrs, slots.default?.());
        };
    },
});
