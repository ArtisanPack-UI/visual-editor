/**
 * Gallery — fetch attachment media records for inner image blocks.
 *
 * Ported from `@wordpress/block-library/src/gallery/use-get-media.js`
 * (v9.43.0).
 */

import { useSelect } from '@wordpress/data';
import { store as coreStore } from '@wordpress/core-data';

const EMPTY_IMAGE_MEDIA: readonly unknown[] = [];

interface InnerImageBlock {
    readonly attributes: {
        readonly id?: number | string;
        readonly [key: string]: unknown;
    };
    readonly [key: string]: unknown;
}

export default function useGetMedia(
    innerBlockImages: readonly InnerImageBlock[]
): readonly Record<string, unknown>[] {
    return useSelect(
        (select) => {
            const imageIds = innerBlockImages
                .map((imageBlock) => imageBlock.attributes.id)
                .filter((id): id is number | string => id !== undefined);

            if (imageIds.length === 0) {
                return EMPTY_IMAGE_MEDIA;
            }

            return (
                (
                    select(coreStore) as unknown as {
                        getEntityRecords: (
                            kind: string,
                            name: string,
                            query: Record<string, unknown>
                        ) => readonly Record<string, unknown>[] | null;
                    }
                ).getEntityRecords('postType', 'attachment', {
                    include: imageIds.join(','),
                    per_page: -1,
                    orderby: 'include',
                }) ?? EMPTY_IMAGE_MEDIA
            );
        },
        [innerBlockImages]
    ) as readonly Record<string, unknown>[];
}
