/**
 * Marquee — saved markup.
 *
 * Persists the same DOM shape the renderers emit so Gutenberg's
 * save-vs-edit validator passes on reload and the `source: 'html',
 * selector: 'p'` parser round-trips `marqueeContent` correctly. The
 * frontend renderers (Blade, React, Vue) output the serialized HTML
 * verbatim; the host application is responsible for shipping the
 * matching `.ap-marquee` stylesheet (it ships in `marquee.css`).
 */

import type { CSSProperties, ReactElement } from 'react';
import { RichText, useBlockProps } from '@wordpress/block-editor';

interface MarqueeAttributes {
    readonly marqueeContent: string;
    readonly marqueeWidth: number;
    readonly marqueeSpeed: number;
}

interface MarqueeSaveProps {
    readonly attributes: MarqueeAttributes;
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

export default function MarqueeSave({
    attributes,
}: MarqueeSaveProps): ReactElement {
    const { marqueeContent, marqueeWidth, marqueeSpeed } = attributes;

    const safeWidth = clampPercent(marqueeWidth, 100);
    const safeSpeed = clampSpeed(marqueeSpeed, 5);

    const blockProps = useBlockProps.save({
        className: 'ap-marquee',
        style: { width: `${safeWidth}%` } as CSSProperties,
    });

    const textStyle: CSSProperties = {
        animation: `ap-marquee-scroll ${safeSpeed}s linear infinite`,
    };

    return (
        <div {...blockProps}>
            <RichText.Content
                tagName="p"
                className="ap-marquee__text"
                value={marqueeContent}
                style={textStyle}
            />
        </div>
    );
}
