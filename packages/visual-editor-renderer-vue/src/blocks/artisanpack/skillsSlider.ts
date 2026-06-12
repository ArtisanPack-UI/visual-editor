/**
 * Vue renderer for the `artisanpack/skills-slider` block (#503).
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

const SAFE_HEX = /^#(?:[0-9a-fA-F]{3,4}|[0-9a-fA-F]{6}|[0-9a-fA-F]{8})$/;
const SAFE_FN = /^(?:rgb|rgba|hsl|hsla)\(\s*[0-9.,%\s/]+\)$/;

function safeColor(value: string): string {
    if (value === '') {
        return '';
    }
    if (SAFE_HEX.test(value) || SAFE_FN.test(value)) {
        return value;
    }
    return '';
}

export const SkillsSliderBlock = defineComponent({
    name: 'SkillsSliderBlock',
    props: blockRendererProps,
    setup(props) {
        return () => {
            const level = clampInt(
                attrFloat(props.attributes.skillLevel, 50),
                1,
                100,
                50
            );
            const height = clampInt(
                attrFloat(props.attributes.barHeight, 5),
                1,
                100,
                5
            );
            const barColor = safeColor(attrString(props.attributes.barColor));
            const trackColor = safeColor(attrString(props.attributes.trackColor));
            const ariaLabel = attrString(props.attributes.ariaLabel);
            const className = attrString(props.attributes.className);

            const classes = classList(['ap-skills-slider', className]);

            const trackStyle: Record<string, string> = { height: `${height}px` };
            if (trackColor !== '') {
                trackStyle.backgroundColor = trackColor;
            }

            const barStyle: Record<string, string> = {
                width: `${level}%`,
                height: `${height}px`,
            };
            if (barColor !== '') {
                barStyle.backgroundColor = barColor;
            }

            const trackAttrs: Record<string, unknown> = {
                class: 'ap-skills-slider__track',
                style: trackStyle,
                role: 'progressbar',
                'aria-valuemin': '0',
                'aria-valuemax': '100',
                'aria-valuenow': String(level),
            };
            trackAttrs['aria-label'] = ariaLabel !== '' ? ariaLabel : 'Skill level';

            return h('div', { class: classes }, [
                h(
                    'div',
                    trackAttrs,
                    [h('div', { class: 'ap-skills-slider__bar', style: barStyle })]
                ),
            ]);
        };
    },
});
