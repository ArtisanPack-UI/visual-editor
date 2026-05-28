/**
 * Gallery — diff inner image blocks to find newly-inserted ones.
 *
 * Ported from `@wordpress/block-library/src/gallery/use-get-new-images.js`
 * (v9.43.0).
 */

import { useMemo, useState } from '@wordpress/element';

export interface GalleryImage {
    readonly clientId?: string;
    readonly id?: number | string;
    readonly url?: string;
    readonly attributes: Record<string, unknown>;
    readonly fromSavedContent?: boolean;
}

export default function useGetNewImages(
    images: readonly GalleryImage[],
    imageData: readonly Record<string, unknown>[]
): readonly GalleryImage[] | null {
    const [currentImages, setCurrentImages] = useState<GalleryImage[]>([]);

    return useMemo(getNewImages, [images, imageData]);

    function getNewImages(): readonly GalleryImage[] | null {
        let imagesUpdated = false;

        // First check for deletions.
        const newCurrentImages = currentImages.filter((currentImg) =>
            images.find((img) => currentImg.clientId === img.clientId)
        );

        if (newCurrentImages.length < currentImages.length) {
            imagesUpdated = true;
        }

        // Hydrate from saved content.
        images.forEach((image) => {
            if (
                image.fromSavedContent &&
                !newCurrentImages.find(
                    (currentImage) => currentImage.id === image.id
                )
            ) {
                imagesUpdated = true;
                newCurrentImages.push(image);
            }
        });

        // Look for newly-added inner blocks with media data available.
        const newImages = images.filter(
            (image) =>
                !newCurrentImages.find(
                    (currentImage) =>
                        image.clientId &&
                        currentImage.clientId === image.clientId
                ) &&
                imageData?.find(
                    (img) => (img as { id?: number | string }).id === image.id
                ) &&
                !image.fromSavedContent
        );

        if (imagesUpdated || newImages?.length > 0) {
            setCurrentImages([...newCurrentImages, ...newImages]);
        }

        return newImages.length > 0 ? newImages : null;
    }
}
