/**
 * Skills Slider — editor-side render.
 *
 * Renders the same DOM shape `save.tsx` produces so Gutenberg's save-
 * vs-edit validator passes on reload. The block is static — no inner
 * blocks, no resolver round-trip — so the editor preview is identical
 * to the persisted markup.
 */

import type { CSSProperties, ReactElement } from 'react';
import {
    InspectorControls,
    PanelColorSettings,
    useBlockProps,
} from '@wordpress/block-editor';
import { PanelBody, RangeControl, TextControl } from '@wordpress/components';
import { __ } from '@wordpress/i18n';

import { TEXT_DOMAIN } from '../../vendor/i18n';

import {
    BAR_HEIGHT_DEFAULT,
    SKILL_LEVEL_DEFAULT,
    clampBarHeight,
    clampSkillLevel,
} from './clamp';

interface SkillsSliderAttributes {
    readonly skillLevel: number;
    readonly barHeight: number;
    readonly barColor: string;
    readonly trackColor: string;
    readonly ariaLabel: string;
}

interface SkillsSliderEditProps {
    readonly attributes: SkillsSliderAttributes;
    readonly setAttributes: (next: Partial<SkillsSliderAttributes>) => void;
}

export default function SkillsSliderEdit({
    attributes,
    setAttributes,
}: SkillsSliderEditProps): ReactElement {
    const { skillLevel, barHeight, barColor, trackColor, ariaLabel } = attributes;

    const safeLevel = clampSkillLevel(skillLevel);
    const safeHeight = clampBarHeight(barHeight);

    const blockProps = useBlockProps({ className: 'ap-skills-slider' });

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
        <>
            <InspectorControls>
                <PanelBody title={__('Skills slider settings', TEXT_DOMAIN)} initialOpen>
                    <RangeControl
                        label={__('Skill level', TEXT_DOMAIN)}
                        help={__('Percentage filled (1–100).', TEXT_DOMAIN)}
                        value={safeLevel}
                        onChange={(value) =>
                            setAttributes({ skillLevel: clampSkillLevel(value) })
                        }
                        min={1}
                        max={100}
                        initialPosition={SKILL_LEVEL_DEFAULT}
                        allowReset
                        resetFallbackValue={SKILL_LEVEL_DEFAULT}
                        __nextHasNoMarginBottom
                    />
                    <RangeControl
                        label={__('Bar height', TEXT_DOMAIN)}
                        help={__('Bar height in pixels (1–100).', TEXT_DOMAIN)}
                        value={safeHeight}
                        onChange={(value) =>
                            setAttributes({ barHeight: clampBarHeight(value) })
                        }
                        min={1}
                        max={100}
                        initialPosition={BAR_HEIGHT_DEFAULT}
                        allowReset
                        resetFallbackValue={BAR_HEIGHT_DEFAULT}
                        __nextHasNoMarginBottom
                    />
                    <TextControl
                        label={__('Accessible label', TEXT_DOMAIN)}
                        help={__(
                            'Optional. Read by assistive tech in place of "skill level".',
                            TEXT_DOMAIN
                        )}
                        value={ariaLabel}
                        onChange={(value: string) =>
                            setAttributes({ ariaLabel: value })
                        }
                        __nextHasNoMarginBottom
                    />
                </PanelBody>
                <PanelColorSettings
                    title={__('Colour', TEXT_DOMAIN)}
                    initialOpen={false}
                    colorSettings={[
                        {
                            value: barColor,
                            onChange: (value: string | undefined) =>
                                setAttributes({ barColor: value ?? '' }),
                            label: __('Bar colour', TEXT_DOMAIN),
                        },
                        {
                            value: trackColor,
                            onChange: (value: string | undefined) =>
                                setAttributes({ trackColor: value ?? '' }),
                            label: __('Track colour', TEXT_DOMAIN),
                        },
                    ]}
                />
            </InspectorControls>
            <div {...blockProps}>
                <div
                    className="ap-skills-slider__track"
                    style={trackStyle}
                    role="progressbar"
                    aria-valuemin={0}
                    aria-valuemax={100}
                    aria-valuenow={safeLevel}
                    aria-label={ariaLabel || __('Skill level', TEXT_DOMAIN)}
                >
                    <div className="ap-skills-slider__bar" style={barStyle} />
                </div>
            </div>
        </>
    );
}
