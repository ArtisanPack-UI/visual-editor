/**
 * React renderer for the `artisanpack/marquee` block (#500).
 *
 * Mirrors the Blade partial and the Vue renderer so every environment
 * emits identical markup. The animation runs from a shared
 * `ap-marquee-scroll` keyframe — host apps are responsible for
 * shipping the matching `.ap-marquee` stylesheet on the frontend.
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

export function MarqueeBlock({ attributes }: BlockRendererProps): JSX.Element {
    const width = clampInt(attrFloat(attributes.marqueeWidth, 100), 1, 100, 100);
    const speed = clampInt(attrFloat(attributes.marqueeSpeed, 5), 1, 100, 5);
    const content = attrString(attributes.marqueeContent);
    const className = attrString(attributes.className);

    const classes = classList(['ap-marquee', className]);
    const wrapperStyle: CSSProperties = { width: `${width}%` };
    const textStyle: CSSProperties = {
        animation: `ap-marquee-scroll ${speed}s linear infinite`,
    };

    return (
        <div className={classes} style={wrapperStyle}>
            <p
                className="ap-marquee__text"
                style={textStyle}
                dangerouslySetInnerHTML={{ __html: content }}
            />
        </div>
    );
}
