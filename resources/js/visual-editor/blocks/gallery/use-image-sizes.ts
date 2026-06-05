/**
 * Gallery — derive the available resolution options for the inspector.
 *
 * Ported from `@wordpress/block-library/src/gallery/use-image-sizes.js`
 * (v9.43.0).
 */

import { useMemo } from '@wordpress/element';

interface AttachmentRecord {
    readonly id?: number | string;
    readonly sizes?: { readonly [slug: string]: { readonly url?: string } };
    readonly media_details?: {
        readonly sizes?: { readonly [slug: string]: { readonly source_url?: string } };
    };
}

interface EditorSettings {
    readonly imageSizes: readonly { readonly name: string; readonly slug: string }[];
}

type GetSettings = () => EditorSettings;

export interface ImageSizeOption {
    readonly value: string;
    readonly label: string;
}

export default function useImageSizes(
    images: readonly AttachmentRecord[] | undefined,
    isSelected: boolean,
    getSettings: GetSettings
): readonly ImageSizeOption[] | undefined {
    return useMemo(getImageSizing, [images, isSelected, getSettings]);

    function getImageSizing(): readonly ImageSizeOption[] | undefined {
        if (!images || images.length === 0) {
            return;
        }
        const { imageSizes } = getSettings();
        let resizedImages: Record<string, Record<string, string | undefined>> = {};

        if (isSelected) {
            resizedImages = images.reduce<
                Record<string, Record<string, string | undefined>>
            >((currentResizedImages, img) => {
                if (!img.id) {
                    return currentResizedImages;
                }

                const sizes = imageSizes.reduce<Record<string, string | undefined>>(
                    (currentSizes, size) => {
                        const defaultUrl = img.sizes?.[size.slug]?.url;
                        const mediaDetailsUrl =
                            img.media_details?.sizes?.[size.slug]?.source_url;
                        return {
                            ...currentSizes,
                            [size.slug]: defaultUrl || mediaDetailsUrl,
                        };
                    },
                    {}
                );
                return {
                    ...currentResizedImages,
                    [parseInt(String(img.id), 10)]: sizes,
                };
            }, {});
        }
        const resizedImageSizes = Object.values(resizedImages);
        return imageSizes
            .filter(({ slug }) =>
                resizedImageSizes.some((sizes) => sizes[slug])
            )
            .map(({ name, slug }) => ({ value: slug, label: name }));
    }
}
