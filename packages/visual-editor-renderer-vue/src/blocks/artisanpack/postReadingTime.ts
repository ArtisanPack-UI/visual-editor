/**
 * Vue renderer for the `artisanpack/post-reading-time` block (#943).
 *
 * Mirrors the Blade partial and the React renderer. Renders nothing
 * outside a post context or for a post with no text.
 */

import { defineComponent, h } from 'vue';

import { attrInt, attrString, classList } from '../../support/attributes';
import { blockRendererProps } from '../shared';

/**
 * The visible "{prefix}{minutes} {suffix}" line, or `null` for no minutes.
 */
export function readingTimeLine(attributes: Record<string, unknown>): string | null {
    const minutes = Math.max(0, attrInt(attributes._resolvedReadingTime, 0));

    if (minutes === 0) {
        return null;
    }

    const prefix = attrString(attributes.prefix);
    const suffix = (typeof attributes.suffix === 'string' ? attributes.suffix : 'min read').trim();

    return `${prefix}${minutes}${suffix !== '' ? ` ${suffix}` : ''}`;
}

export const PostReadingTimeBlock = defineComponent({
    name: 'PostReadingTimeBlock',
    props: blockRendererProps,
    setup(props) {
        return () => {
            const line = readingTimeLine(props.attributes);

            if (line === null) {
                return null;
            }

            const classes = classList([
                'wp-block-artisanpack-post-reading-time',
                attrString(props.attributes.className),
            ]);

            return h('div', { class: classes }, line);
        };
    },
});
