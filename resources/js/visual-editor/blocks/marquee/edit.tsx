/**
 * Marquee — editor-side render.
 *
 * Renders the same DOM shape `save.tsx` produces so Gutenberg's save-
 * vs-edit validator passes on reload. The editor preview deliberately
 * omits the inline `animation` declaration the renderers emit — the
 * text stays still so authors can edit the RichText without it sliding
 * out from under their cursor. `canvas-styles.ts` ships a matching
 * `transform: none` override on `.ap-marquee__text` so the keyframe's
 * translate-off-screen base rule doesn't hide the editor's copy.
 */

import type { CSSProperties, ReactElement } from 'react';
import {
    InspectorControls,
    RichText,
    useBlockProps,
} from '@wordpress/block-editor';
import { PanelBody, RangeControl } from '@wordpress/components';
import { __ } from '@wordpress/i18n';

import { TEXT_DOMAIN } from '../../vendor/i18n';

import {
    clampPercent,
    clampSpeed,
    MARQUEE_SPEED_DEFAULT,
    MARQUEE_WIDTH_DEFAULT,
} from './clamp';

interface MarqueeAttributes {
    readonly marqueeContent: string;
    readonly marqueeWidth: number;
    readonly marqueeSpeed: number;
}

interface MarqueeEditProps {
    readonly attributes: MarqueeAttributes;
    readonly setAttributes: (next: Partial<MarqueeAttributes>) => void;
}

export default function MarqueeEdit({
    attributes,
    setAttributes,
}: MarqueeEditProps): ReactElement {
    const { marqueeContent, marqueeWidth, marqueeSpeed } = attributes;

    const safeWidth = clampPercent(marqueeWidth);
    const safeSpeed = clampSpeed(marqueeSpeed);

    const blockProps = useBlockProps({
        className: 'ap-marquee',
        style: { width: `${safeWidth}%` } as CSSProperties,
    });

    return (
        <>
            <InspectorControls>
                <PanelBody title={__('Marquee settings', TEXT_DOMAIN)} initialOpen>
                    <RangeControl
                        label={__('Width', TEXT_DOMAIN)}
                        help={__('Percentage of the container width.', TEXT_DOMAIN)}
                        value={safeWidth}
                        onChange={(value) =>
                            setAttributes({ marqueeWidth: clampPercent(value) })
                        }
                        min={1}
                        max={100}
                        initialPosition={MARQUEE_WIDTH_DEFAULT}
                        allowReset
                        resetFallbackValue={MARQUEE_WIDTH_DEFAULT}
                        __nextHasNoMarginBottom
                    />
                    <RangeControl
                        label={__('Speed', TEXT_DOMAIN)}
                        help={__(
                            'Seconds per scroll loop. Higher values are slower.',
                            TEXT_DOMAIN
                        )}
                        value={safeSpeed}
                        onChange={(value) =>
                            setAttributes({ marqueeSpeed: clampSpeed(value) })
                        }
                        min={1}
                        max={100}
                        initialPosition={MARQUEE_SPEED_DEFAULT}
                        allowReset
                        resetFallbackValue={MARQUEE_SPEED_DEFAULT}
                        __nextHasNoMarginBottom
                    />
                </PanelBody>
            </InspectorControls>
            <div {...blockProps}>
                <RichText
                    tagName="p"
                    className="ap-marquee__text"
                    value={marqueeContent}
                    onChange={(next: string) =>
                        setAttributes({ marqueeContent: next })
                    }
                    allowedFormats={['core/bold', 'core/italic']}
                    placeholder={__('Marquee text here…', TEXT_DOMAIN)}
                />
            </div>
        </>
    );
}
