/**
 * Skills Slider — saved markup.
 *
 * Static block. The frontend renderers (Blade, React, Vue) emit the
 * same DOM shape so authors can ship the persisted markup directly
 * if they aren't running a renderer.
 */

import type { CSSProperties, ReactElement } from 'react';
import { useBlockProps } from '@wordpress/block-editor';

import { clampBarHeight, clampSkillLevel } from './clamp';

interface SkillsSliderAttributes {
    readonly skillLevel: number;
    readonly barHeight: number;
    readonly barColor: string;
    readonly trackColor: string;
    readonly ariaLabel: string;
}

interface SkillsSliderSaveProps {
    readonly attributes: SkillsSliderAttributes;
}

export default function SkillsSliderSave({
    attributes,
}: SkillsSliderSaveProps): ReactElement {
    const { skillLevel, barHeight, barColor, trackColor, ariaLabel } = attributes;

    const safeLevel = clampSkillLevel(skillLevel);
    const safeHeight = clampBarHeight(barHeight);

    const blockProps = useBlockProps.save({ className: 'ap-skills-slider' });

    const trackStyle: CSSProperties = {
        height: `${safeHeight}px`,
        backgroundColor: trackColor || undefined,
    };

    const barStyle: CSSProperties = {
        width: `${safeLevel}%`,
        height: `${safeHeight}px`,
        backgroundColor: barColor || undefined,
    };

    return (
        <div {...blockProps}>
            <div
                className="ap-skills-slider__track"
                style={trackStyle}
                role="progressbar"
                aria-valuemin={0}
                aria-valuemax={100}
                aria-valuenow={safeLevel}
                aria-label={ariaLabel || 'Skill level'}
            >
                <div className="ap-skills-slider__bar" style={barStyle} />
            </div>
        </div>
    );
}
