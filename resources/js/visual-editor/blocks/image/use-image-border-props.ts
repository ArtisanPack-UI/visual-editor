/**
 * The Image block's border, painted on the `<img>` in the canvas (#1052).
 *
 * block.json skips border serialization, so the wrapper gets nothing and the
 * `<img>` has to apply it, as `save()` does. A gradient border stays on the
 * wrapper's `::before`, as on the front end.
 */

import type { CSSProperties } from 'react';
import { __experimentalUseBorderProps as useBorderProps } from '@wordpress/block-editor';

import { resolveGradientBorder } from '../../gradient-borders/resolver';
import type { GradientBorderAttributes } from '../../gradient-borders/types';

export interface ImageBorderProps {
    readonly className?: string;
    readonly style?: CSSProperties;
    readonly hasCustomBorder: boolean;
}

export function useImageBorderProps(attributes: Record<string, unknown>): ImageBorderProps {
    const borderProps = (useBorderProps as any)(attributes) as Omit<ImageBorderProps, 'hasCustomBorder'>;

    if (resolveGradientBorder(attributes as GradientBorderAttributes) !== null) {
        return { hasCustomBorder: false };
    }

    return {
        className: borderProps.className || undefined,
        style: borderProps.style,
        hasCustomBorder:
            !!borderProps.className || Object.keys(borderProps.style ?? {}).length > 0,
    };
}
