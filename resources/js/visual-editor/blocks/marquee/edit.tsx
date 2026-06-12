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

interface MarqueeAttributes {
    readonly marqueeContent: string;
    readonly marqueeWidth: number;
    readonly marqueeSpeed: number;
}

interface MarqueeEditProps {
    readonly attributes: MarqueeAttributes;
    readonly setAttributes: (next: Partial<MarqueeAttributes>) => void;
}

function clampPercent(value: number | null | undefined, fallback: number): number {
    if (typeof value !== 'number' || !Number.isFinite(value)) {
        return fallback;
    }
    return Math.min(100, Math.max(1, Math.round(value)));
}

function clampSpeed(value: number | null | undefined, fallback: number): number {
    if (typeof value !== 'number' || !Number.isFinite(value)) {
        return fallback;
    }
    return Math.min(100, Math.max(1, Math.round(value)));
}

export default function MarqueeEdit({
    attributes,
    setAttributes,
}: MarqueeEditProps): ReactElement {
    const { marqueeContent, marqueeWidth, marqueeSpeed } = attributes;

    const safeWidth = clampPercent(marqueeWidth, 100);
    const safeSpeed = clampSpeed(marqueeSpeed, 5);

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
                            setAttributes({ marqueeWidth: clampPercent(value, 100) })
                        }
                        min={1}
                        max={100}
                        initialPosition={100}
                        allowReset
                        resetFallbackValue={100}
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
                            setAttributes({ marqueeSpeed: clampSpeed(value, 5) })
                        }
                        min={1}
                        max={100}
                        initialPosition={5}
                        allowReset
                        resetFallbackValue={5}
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
