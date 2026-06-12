/**
 * React renderer for the `artisanpack/skills-slider` block (#503).
 *
 * Mirrors the Blade partial and the Vue renderer so every environment
 * emits identical markup.
 */

import type { CSSProperties, JSX } from 'react';

import { attrFloat, attrString, classList } from '../../support/attributes';
import type { BlockRendererProps } from '../../types';

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

export function SkillsSliderBlock({ attributes }: BlockRendererProps): JSX.Element {
    const level = clampInt(attrFloat(attributes.skillLevel, 50), 1, 100, 50);
    const height = clampInt(attrFloat(attributes.barHeight, 5), 1, 100, 5);
    const barColor = safeColor(attrString(attributes.barColor));
    const trackColor = safeColor(attrString(attributes.trackColor));
    const ariaLabel = attrString(attributes.ariaLabel);
    const className = attrString(attributes.className);

    const classes = classList(['ap-skills-slider', className]);

    const trackStyle: CSSProperties = { height: `${height}px` };
    if (trackColor !== '') {
        trackStyle.backgroundColor = trackColor;
    }

    const barStyle: CSSProperties = { width: `${level}%`, height: `${height}px` };
    if (barColor !== '') {
        barStyle.backgroundColor = barColor;
    }

    return (
        <div className={classes}>
            <div
                className="ap-skills-slider__track"
                style={trackStyle}
                role="progressbar"
                aria-valuemin={0}
                aria-valuemax={100}
                aria-valuenow={level}
                aria-label={ariaLabel !== '' ? ariaLabel : 'Skill level'}
            >
                <div className="ap-skills-slider__bar" style={barStyle} />
            </div>
        </div>
    );
}
