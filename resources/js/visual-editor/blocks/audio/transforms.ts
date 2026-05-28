/**
 * Audio — transforms.
 *
 * Ported from `@wordpress/block-library/src/audio/transforms.js` (v9.43.0).
 * Extended with bidirectional block transforms for `core/audio` ↔
 * `artisanpack/audio` so mixed documents round-trip losslessly during
 * the V2 rollout.
 */

import { createBlobURL } from '@wordpress/blob';
import { createBlock } from '@wordpress/blocks';

import metadata from './block.json';

const { name } = metadata;

interface AudioAttributes {
    readonly src?: string;
    readonly caption?: string;
    readonly id?: number;
    readonly autoplay?: boolean;
    readonly loop?: boolean;
    readonly preload?: string;
    readonly blob?: string;
    readonly [key: string]: unknown;
}

interface ShortcodeArgs {
    readonly named: {
        readonly src?: string;
        readonly mp3?: string;
        readonly m4a?: string;
        readonly ogg?: string;
        readonly wav?: string;
        readonly wma?: string;
        readonly loop?: string;
        readonly autoplay?: string;
        readonly preload?: string;
    };
}

const transforms = {
    from: [
        {
            type: 'files',
            isMatch(files: readonly File[]): boolean {
                return (
                    files.length === 1 &&
                    files[0].type.indexOf('audio/') === 0
                );
            },
            transform(files: readonly File[]) {
                const file = files[0];
                return createBlock(name, {
                    blob: createBlobURL(file),
                });
            },
        },
        {
            type: 'shortcode',
            tag: 'audio',
            attributes: {
                src: {
                    type: 'string',
                    shortcode: ({
                        named: { src, mp3, m4a, ogg, wav, wma },
                    }: ShortcodeArgs) => src || mp3 || m4a || ogg || wav || wma,
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
            type: 'block',
            blocks: ['core/audio'],
            transform: (attributes: AudioAttributes) =>
                createBlock(name, attributes),
        },
    ],
    to: [
        {
            type: 'block',
            blocks: ['core/audio'],
            transform: (attributes: AudioAttributes) =>
                createBlock('core/audio', attributes),
        },
    ],
};

export default transforms;
