/**
 * File — transforms.
 *
 * Ported from `@wordpress/block-library/src/file/transforms.js` (v9.43.0).
 * Extended with bidirectional block transforms for `core/file` ↔
 * `artisanpack/file` so mixed documents round-trip losslessly during
 * the V2 rollout.
 *
 * The upstream `to` transforms (file → audio/video/image) gate on a media
 * mime-type lookup via `@wordpress/core-data`. They are preserved verbatim
 * and continue targeting `core/audio`, `core/video`, `core/image` so the
 * existing renderer parity tables don't shift; the new `core/file` ↔
 * `artisanpack/file` block transform is appended to both sides.
 */

import { createBlobURL } from '@wordpress/blob';
import { createBlock } from '@wordpress/blocks';
import { select } from '@wordpress/data';
import { store as coreStore } from '@wordpress/core-data';
import { getFilename } from '@wordpress/url';

import metadata from './block.json';

const { name } = metadata;

interface FileAttributes {
    readonly href?: string;
    readonly fileName?: string;
    readonly textLinkHref?: string;
    readonly id?: number;
    readonly anchor?: string;
    readonly blob?: string;
    readonly [key: string]: unknown;
}

interface MediaSourceAttributes {
    readonly src?: string;
    readonly url?: string;
    readonly caption?: string;
    readonly id?: number;
    readonly anchor?: string;
}

interface MediaRecord {
    readonly mime_type: string;
}

const transforms = {
    from: [
        {
            type: 'files',
            isMatch(files: readonly File[]): boolean {
                return files.length > 0;
            },
            // We define a lower priority (higher number) than the default of 10. This
            // ensures that the File block is only created as a fallback.
            priority: 15,
            transform: (files: readonly File[]) => {
                const blocks: unknown[] = [];

                files.forEach((file) => {
                    const blobURL = createBlobURL(file);

                    // File will be uploaded in componentDidMount()
                    if (file.type.startsWith('video/')) {
                        blocks.push(
                            createBlock('core/video', {
                                blob: blobURL,
                            })
                        );
                    } else if (file.type.startsWith('image/')) {
                        blocks.push(
                            createBlock('core/image', {
                                blob: blobURL,
                            })
                        );
                    } else if (file.type.startsWith('audio/')) {
                        blocks.push(
                            createBlock('core/audio', {
                                blob: blobURL,
                            })
                        );
                    } else {
                        blocks.push(
                            createBlock(name, {
                                blob: blobURL,
                                fileName: file.name,
                            })
                        );
                    }
                });

                return blocks;
            },
        },
        {
            type: 'block',
            blocks: ['core/audio'],
            transform: (attributes: MediaSourceAttributes) => {
                return createBlock(name, {
                    href: attributes.src,
                    fileName: attributes.caption,
                    textLinkHref: attributes.src,
                    id: attributes.id,
                    anchor: attributes.anchor,
                });
            },
        },
        {
            type: 'block',
            blocks: ['core/video'],
            transform: (attributes: MediaSourceAttributes) => {
                return createBlock(name, {
                    href: attributes.src,
                    fileName: attributes.caption,
                    textLinkHref: attributes.src,
                    id: attributes.id,
                    anchor: attributes.anchor,
                });
            },
        },
        {
            type: 'block',
            blocks: ['core/image'],
            transform: (attributes: MediaSourceAttributes) => {
                return createBlock(name, {
                    href: attributes.url,
                    fileName:
                        attributes.caption || getFilename(attributes.url ?? ''),
                    textLinkHref: attributes.url,
                    id: attributes.id,
                    anchor: attributes.anchor,
                });
            },
        },
        {
            type: 'block',
            blocks: ['core/file'],
            transform: (attributes: FileAttributes) =>
                createBlock(name, attributes),
        },
    ],
    to: [
        {
            type: 'block',
            blocks: ['core/audio'],
            isMatch: ({ id }: { id?: number }) => {
                if (!id) {
                    return false;
                }
                const { getEntityRecord } = select(coreStore);
                const media = getEntityRecord(
                    'postType',
                    'attachment',
                    id
                ) as MediaRecord | undefined;
                return !!media && media.mime_type.includes('audio');
            },
            transform: (attributes: FileAttributes) => {
                return createBlock('core/audio', {
                    src: attributes.href,
                    caption: attributes.fileName,
                    id: attributes.id,
                    anchor: attributes.anchor,
                });
            },
        },
        {
            type: 'block',
            blocks: ['core/video'],
            isMatch: ({ id }: { id?: number }) => {
                if (!id) {
                    return false;
                }
                const { getEntityRecord } = select(coreStore);
                const media = getEntityRecord(
                    'postType',
                    'attachment',
                    id
                ) as MediaRecord | undefined;
                return !!media && media.mime_type.includes('video');
            },
            transform: (attributes: FileAttributes) => {
                return createBlock('core/video', {
                    src: attributes.href,
                    caption: attributes.fileName,
                    id: attributes.id,
                    anchor: attributes.anchor,
                });
            },
        },
        {
            type: 'block',
            blocks: ['core/image'],
            isMatch: ({ id }: { id?: number }) => {
                if (!id) {
                    return false;
                }
                const { getEntityRecord } = select(coreStore);
                const media = getEntityRecord(
                    'postType',
                    'attachment',
                    id
                ) as MediaRecord | undefined;
                return !!media && media.mime_type.includes('image');
            },
            transform: (attributes: FileAttributes) => {
                return createBlock('core/image', {
                    url: attributes.href,
                    caption: attributes.fileName,
                    id: attributes.id,
                    anchor: attributes.anchor,
                });
            },
        },
        {
            type: 'block',
            blocks: ['core/file'],
            transform: (attributes: FileAttributes) =>
                createBlock('core/file', attributes),
        },
    ],
};

export default transforms;
