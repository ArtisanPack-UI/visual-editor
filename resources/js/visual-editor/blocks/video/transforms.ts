/**
 * Video — transforms.
 *
 * Ported from `@wordpress/block-library/src/video/transforms.js` (v9.43.0).
 * Extended with bidirectional block transforms for `core/video` ↔
 * `artisanpack/video` so mixed documents round-trip losslessly during
 * the V2 rollout.
 */

import { createBlobURL, isBlobURL } from '@wordpress/blob';
import { createBlock } from '@wordpress/blocks';

import metadata from './block.json';

const { name } = metadata;

interface VideoAttributes {
    readonly src?: string;
    readonly blob?: string;
    readonly poster?: string;
    readonly autoplay?: boolean;
    readonly controls?: boolean;
    readonly loop?: boolean;
    readonly muted?: boolean;
    readonly preload?: string;
    readonly playsInline?: boolean;
    readonly id?: number;
    readonly caption?: string;
    readonly [key: string]: unknown;
}

interface ShortcodeArgs {
    readonly named: {
        readonly src?: string;
        readonly mp4?: string;
        readonly m4v?: string;
        readonly webm?: string;
        readonly ogv?: string;
        readonly flv?: string;
        readonly poster?: string;
        readonly loop?: string;
        readonly autoplay?: string;
        readonly preload?: string;
    };
}

interface RawNode {
    readonly nodeName: string;
    readonly children: ArrayLike<unknown>;
    readonly firstChild: {
        readonly nodeName: string;
        hasAttribute(name: string): boolean;
        getAttribute(name: string): string | null;
    };
}

const transforms = {
    from: [
        {
            type: 'files',
            isMatch(files: readonly File[]): boolean {
                return (
                    files.length === 1 &&
                    files[0].type.indexOf('video/') === 0
                );
            },
            transform(files: readonly File[]) {
                const file = files[0];
                // We don't need to upload the media directly here.
                // It's already done as part of the `componentDidMount`
                // in the video block.
                return createBlock(name, {
                    blob: createBlobURL(file),
                });
            },
        },
        {
            type: 'shortcode',
            tag: 'video',
            attributes: {
                src: {
                    type: 'string',
                    shortcode: ({
                        named: { src, mp4, m4v, webm, ogv, flv },
                    }: ShortcodeArgs) =>
                        src || mp4 || m4v || webm || ogv || flv,
                },
                poster: {
                    type: 'string',
                    shortcode: ({ named: { poster } }: ShortcodeArgs) => poster,
                },
                loop: {
                    type: 'string',
                    shortcode: ({ named: { loop } }: ShortcodeArgs) => loop,
                },
                autoplay: {
                    type: 'string',
                    shortcode: ({ named: { autoplay } }: ShortcodeArgs) =>
                        autoplay,
                },
                preload: {
                    type: 'string',
                    shortcode: ({ named: { preload } }: ShortcodeArgs) =>
                        preload,
                },
            },
        },
        {
            type: 'raw',
            isMatch: (node: RawNode): boolean =>
                node.nodeName === 'P' &&
                node.children.length === 1 &&
                node.firstChild.nodeName === 'VIDEO',
            transform: (node: RawNode) => {
                const videoElement = node.firstChild;
                const attributes: VideoAttributes = {
                    autoplay: videoElement.hasAttribute('autoplay')
                        ? true
                        : undefined,
                    controls: videoElement.hasAttribute('controls')
                        ? undefined
                        : false,
                    loop: videoElement.hasAttribute('loop')
                        ? true
                        : undefined,
                    muted: videoElement.hasAttribute('muted')
                        ? true
                        : undefined,
                    preload:
                        videoElement.getAttribute('preload') || undefined,
                    playsInline: videoElement.hasAttribute('playsinline')
                        ? true
                        : undefined,
                    poster:
                        videoElement.getAttribute('poster') || undefined,
                    src: videoElement.getAttribute('src') || undefined,
                };
                if (isBlobURL(attributes.src)) {
                    (attributes as { blob?: string; src?: string }).blob =
                        attributes.src;
                    delete (attributes as { src?: string }).src;
                }
                return createBlock(name, attributes);
            },
        },
        {
            type: 'block',
            blocks: ['core/video'],
            transform: (attributes: VideoAttributes) =>
                createBlock(name, attributes),
        },
    ],
    to: [
        {
            type: 'block',
            blocks: ['core/video'],
            transform: (attributes: VideoAttributes) =>
                createBlock('core/video', attributes),
        },
    ],
};

export default transforms;
