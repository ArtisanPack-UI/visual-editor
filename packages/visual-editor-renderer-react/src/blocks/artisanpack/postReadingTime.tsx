/**
 * React renderer for the `artisanpack/post-reading-time` block (#943).
 *
 * Mirrors the Blade partial and the Vue renderer. The minutes are
 * server-resolved (PostResolver stamps `_resolvedReadingTime`) and
 * combined with the saved prefix / suffix at render time. Renders
 * nothing outside a post context or for a post with no text.
 */

import type { JSX } from 'react';

import { attrInt, attrString, classList } from '../../support/attributes';
import type { BlockRendererProps } from '../../types';

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

export function PostReadingTimeBlock({ attributes }: BlockRendererProps): JSX.Element | null {
    const line = readingTimeLine(attributes);

    if (line === null) {
        return null;
    }

    const classes = classList(['wp-block-artisanpack-post-reading-time', attrString(attributes.className)]);

    return <div className={classes}>{line}</div>;
}
