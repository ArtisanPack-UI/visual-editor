/**
 * Gallery — transforms.
 *
 * Ported from `@wordpress/block-library/src/gallery/transforms.js`
 * (v9.43.0). Extended with bidirectional block transforms for
 * `core/gallery` ↔ `artisanpack/gallery` so mixed documents round-trip
 * losslessly during the V2 rollout.
 *
 * The upstream `blocks.switchToBlockType.transformedBlock` filters that
 * help third-party blocks update from/to the new innerBlocks-based
 * gallery are preserved verbatim, but registered against the
 * `artisanpack/gallery` block name as well as `core/gallery`.
 */

import { createBlobURL } from '@wordpress/blob';
import { createBlock } from '@wordpress/blocks';
import { addFilter } from '@wordpress/hooks';

import {
    LINK_DESTINATION_ATTACHMENT,
    LINK_DESTINATION_NONE,
    LINK_DESTINATION_MEDIA,
} from './constants';
import metadata from './block.json';

const { name } = metadata;

interface GalleryImageData {
    readonly url?: string;
    readonly id?: number | string | null;
    readonly alt?: string;
    readonly caption?: string;
}

interface GalleryBlock {
    name: string;
    attributes: {
        images?: GalleryImageData[];
        ids?: (number | string | null)[];
        sizeSlug?: string;
        linkDestination?: string;
        [key: string]: unknown;
    };
    innerBlocks: Array<{
        readonly attributes: GalleryImageData & { readonly [key: string]: unknown };
        readonly [key: string]: unknown;
    }>;
}

interface ImageAttributesForBlockTransform {
    readonly align?: string;
    readonly sizeSlug?: string;
    readonly url?: string;
    readonly width?: unknown;
    readonly height?: unknown;
    [key: string]: unknown;
}

interface ShortcodeArgs {
    readonly named: {
        readonly ids?: string;
        readonly columns?: string | number;
        readonly link?: string;
        readonly orderby?: string;
        readonly size?: string;
    };
}

const parseShortcodeIds = (ids: string | undefined): number[] => {
    if (!ids) {
        return [];
    }

    return ids.split(',').map((id) => parseInt(id, 10));
};

/**
 * Upgrades third-party `* → gallery` transforms so they hand back an
 * innerBlocks-based gallery instead of the deprecated `images` array.
 */
function updateThirdPartyTransformToGallery(block: GalleryBlock): GalleryBlock {
    if (
        (block.name === 'core/gallery' || block.name === name) &&
        (block.attributes?.images?.length ?? 0) > 0
    ) {
        const innerBlocks = (block.attributes.images ?? []).map(
            ({ url, id, alt }) => {
                return createBlock('core/image', {
                    url,
                    id: id ? parseInt(String(id), 10) : null,
                    alt,
                    sizeSlug: block.attributes.sizeSlug,
                    linkDestination: block.attributes.linkDestination,
                });
            }
        ) as unknown as GalleryBlock['innerBlocks'];

        delete block.attributes.ids;
        delete block.attributes.images;
        block.innerBlocks = innerBlocks;
    }

    return block;
}
addFilter(
    'blocks.switchToBlockType.transformedBlock',
    'artisanpack/gallery/update-third-party-transform-to',
    updateThirdPartyTransformToGallery
);

/**
 * Downgrades `gallery → *` transforms for third-party blocks so they
 * receive the legacy `images` + `ids` attribute pair they expect.
 */
function updateThirdPartyTransformFromGallery(
    toBlock: GalleryBlock,
    fromBlocks: GalleryBlock | GalleryBlock[]
): GalleryBlock {
    const from = Array.isArray(fromBlocks) ? fromBlocks : [fromBlocks];
    const galleryBlock = from.find(
        (transformedBlock) =>
            (transformedBlock.name === 'core/gallery' ||
                transformedBlock.name === name) &&
            transformedBlock.innerBlocks.length > 0 &&
            !(transformedBlock.attributes.images?.length ?? 0) &&
            !toBlock.name.includes('core/') &&
            !toBlock.name.includes('artisanpack/')
    );

    if (galleryBlock) {
        const images = galleryBlock.innerBlocks.map(
            ({ attributes: { url, id, alt } }) => ({
                url,
                id: id ? parseInt(String(id), 10) : null,
                alt,
            })
        );
        const ids = images.map(({ id }) => id);
        galleryBlock.attributes.images = images;
        galleryBlock.attributes.ids = ids;
    }

    return toBlock;
}
addFilter(
    'blocks.switchToBlockType.transformedBlock',
    'artisanpack/gallery/update-third-party-transform-from',
    updateThirdPartyTransformFromGallery
);

const transforms = {
    from: [
        {
            type: 'block',
            isMultiBlock: true,
            blocks: ['core/image'],
            transform: (
                attributes: readonly ImageAttributesForBlockTransform[]
            ) => {
                let { align, sizeSlug } = attributes[0];
                align = attributes.every((attribute) => attribute.align === align)
                    ? align
                    : undefined;
                sizeSlug = attributes.every(
                    (attribute) => attribute.sizeSlug === sizeSlug
                )
                    ? sizeSlug
                    : undefined;

                const validImages = attributes.filter(({ url }) => url);

                const innerBlocks = validImages.map((image) => {
                    const next = {
                        ...image,
                        width: undefined,
                        height: undefined,
                    };
                    return createBlock('core/image', next);
                });

                return createBlock(name, { align, sizeSlug }, innerBlocks);
            },
        },
        {
            type: 'shortcode',
            tag: 'gallery',
            transform({
                named: { ids, columns = 3, link, orderby, size },
            }: ShortcodeArgs) {
                const imageIds = parseShortcodeIds(ids);

                let linkTo: string = LINK_DESTINATION_NONE;
                if (link === 'post') {
                    linkTo = LINK_DESTINATION_ATTACHMENT;
                } else if (link === 'file') {
                    linkTo = LINK_DESTINATION_MEDIA;
                }

                const galleryBlock = createBlock(
                    name,
                    {
                        columns: parseInt(String(columns), 10),
                        linkTo,
                        randomOrder: orderby === 'rand',
                        ...(size && { sizeSlug: size }),
                    },
                    imageIds.map((imageId) =>
                        createBlock('core/image', {
                            id: imageId,
                            ...(size && { sizeSlug: size }),
                        })
                    )
                );

                return galleryBlock;
            },
            isMatch({ named }: ShortcodeArgs) {
                return undefined !== named.ids;
            },
        },
        {
            // Multi-file drop on an insertion point. Priority 1 overrides
            // the image block's own files transform so multiple images
            // drag-dropped outside of an existing gallery create one.
            type: 'files',
            priority: 1,
            isMatch(files: readonly File[]): boolean {
                return (
                    files.length !== 1 &&
                    files.every((file) => file.type.indexOf('image/') === 0)
                );
            },
            transform(files: readonly File[]) {
                const innerBlocks = files.map((file) =>
                    createBlock('core/image', {
                        blob: createBlobURL(file),
                    })
                );

                return createBlock(name, {}, innerBlocks);
            },
        },
        {
            type: 'block',
            blocks: ['core/gallery'],
            transform: (
                attributes: Record<string, unknown>,
                innerBlocks: unknown[]
            ) => createBlock(name, attributes, innerBlocks),
        },
    ],
    to: [
        {
            type: 'block',
            blocks: ['core/image'],
            transform: (
                { align }: { align?: string },
                innerBlocks: ReadonlyArray<{
                    attributes: Record<string, unknown>;
                }>
            ) => {
                if (innerBlocks.length > 0) {
                    return innerBlocks.map(({ attributes }) => {
                        const {
                            url,
                            alt,
                            caption,
                            title,
                            href,
                            rel,
                            linkClass,
                            id,
                            sizeSlug: imageSizeSlug,
                            linkDestination,
                            linkTarget,
                            anchor,
                            className,
                        } = attributes as Record<string, unknown>;
                        return createBlock('core/image', {
                            align,
                            url,
                            alt,
                            caption,
                            title,
                            href,
                            rel,
                            linkClass,
                            id,
                            sizeSlug: imageSizeSlug,
                            linkDestination,
                            linkTarget,
                            anchor,
                            className,
                        });
                    });
                }
                return createBlock('core/image', { align });
            },
        },
        {
            type: 'block',
            blocks: ['core/gallery'],
            transform: (
                attributes: Record<string, unknown>,
                innerBlocks: unknown[]
            ) => createBlock('core/gallery', attributes, innerBlocks),
        },
    ],
};

export default transforms;
