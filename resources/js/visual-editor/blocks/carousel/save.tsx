/**
 * Carousel — save component (#1127).
 *
 * Dynamic block: the renderers walk the persisted slides and build the
 * region, labels and controls at render time. `save()` only persists the
 * wrapper and the track that hosts the slides, so Gutenberg's validator
 * accepts the stored markup (#747). Editor-only chrome — the preview
 * controls and the attribute-driven modifier classes — stays out, so
 * changing a setting never invalidates the saved markup.
 */

/* eslint-disable @typescript-eslint/no-explicit-any */
import type { ReactElement } from 'react';
import { useBlockProps, useInnerBlocksProps } from '@wordpress/block-editor';

export default function CarouselSave(): ReactElement {
    const blockProps = (useBlockProps.save as any)({ className: 'ap-carousel' });
    const innerBlocksProps = (useInnerBlocksProps.save as any)({
        className: 'wp-block-artisanpack-carousel__track',
    });

    return (
        <div {...blockProps}>
            <div {...innerBlocksProps} />
        </div>
    );
}
