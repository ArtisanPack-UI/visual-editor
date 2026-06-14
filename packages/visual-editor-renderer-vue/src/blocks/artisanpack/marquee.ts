/**
 * Vue renderer for the `artisanpack/marquee` block (#500).
 *
 * Mirrors the Blade partial and the React renderer so every
 * environment emits identical markup.
 */

import { defineComponent, h } from 'vue';

import { attrFloat, attrString, classList } from '../../support/attributes';
import { blockRendererProps } from '../shared';

function clampInt(value: number, min: number, max: number, fallback: number): number {
    if (!Number.isFinite(value)) {
        return fallback;
    }
    return Math.min(max, Math.max(min, Math.round(value)));
}

export const MarqueeBlock = defineComponent({
    name: 'MarqueeBlock',
    props: blockRendererProps,
    setup(props) {
        return () => {
            const width = clampInt(
                attrFloat(props.attributes.marqueeWidth, 100),
                1,
                100,
                100
            );
            const speed = clampInt(
                attrFloat(props.attributes.marqueeSpeed, 5),
                1,
                100,
                5
            );
            const content = attrString(props.attributes.marqueeContent);
            const className = attrString(props.attributes.className);

            const classes = classList(['ap-marquee', className]);
            const wrapperStyle = { width: `${width}%` };
            const textStyle = {
                animation: `ap-marquee-scroll ${speed}s linear infinite`,
            };

            return h('div', { class: classes, style: wrapperStyle }, [
                h('p', {
                    class: 'ap-marquee__text',
                    style: textStyle,
                    innerHTML: content,
                }),
            ]);
        };
    },
});
