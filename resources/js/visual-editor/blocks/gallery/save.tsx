/**
 * Gallery — save (front-end serialization).
 *
 * Ported from `@wordpress/block-library/src/gallery/save.js` (v9.43.0).
 * Behaviour is byte-equivalent to upstream — the only change is the
 * namespace swap to `artisanpack/gallery`. Mixed documents containing
 * `core/gallery` and `artisanpack/gallery` render to identical HTML.
 */

import type { ReactElement } from 'react';
import clsx from 'clsx';
import {
    RichText,
    useBlockProps,
    useInnerBlocksProps,
    __experimentalGetElementClassName,
} from '@wordpress/block-editor';

interface GalleryAttributes {
    readonly caption?: string;
    readonly columns?: number;
    readonly imageCrop?: boolean;
}

interface GallerySaveProps {
    readonly attributes: GalleryAttributes;
}

export default function saveWithInnerBlocks({
    attributes,
}: GallerySaveProps): ReactElement {
    const { caption, columns, imageCrop } = attributes;

    const className = clsx('has-nested-images', {
        [`columns-${columns}`]: columns !== undefined,
        'columns-default': columns === undefined,
        'is-cropped': imageCrop,
    });
    const blockProps = useBlockProps.save({ className });
    const innerBlocksProps = useInnerBlocksProps.save(blockProps);

    return (
        <figure {...innerBlocksProps}>
            {innerBlocksProps.children}
            {!RichText.isEmpty(caption ?? '') && (
                <RichText.Content
                    tagName="figcaption"
                    className={clsx(
                        'blocks-gallery-caption',
                        __experimentalGetElementClassName('caption')
                    )}
                    value={caption}
                />
            )}
        </figure>
    );
}
