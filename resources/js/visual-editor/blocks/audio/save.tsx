/**
 * Audio — save (front-end serialization).
 *
 * Ported from `@wordpress/block-library/src/audio/save.js` (v9.43.0).
 * Behaviour is byte-equivalent to upstream — the only change is the
 * namespace swap to `artisanpack/audio`. Mixed documents containing
 * `core/audio` and `artisanpack/audio` render to identical HTML.
 */

import type { ReactElement } from 'react';
import {
    RichText,
    useBlockProps,
    __experimentalGetElementClassName,
} from '@wordpress/block-editor';

interface AudioAttributes {
    readonly src?: string;
    readonly caption?: string;
    readonly autoplay?: boolean;
    readonly loop?: boolean;
    readonly preload?: string;
}

interface AudioSaveProps {
    readonly attributes: AudioAttributes;
}

export default function save({ attributes }: AudioSaveProps): ReactElement | null {
    const { autoplay, caption, loop, preload, src } = attributes;

    if (!src) {
        return null;
    }

    return (
        <figure {...useBlockProps.save()}>
            <audio
                controls="controls"
                src={src}
                autoPlay={autoplay}
                loop={loop}
                preload={preload}
            />
            {!RichText.isEmpty(caption ?? '') && (
                <RichText.Content
                    tagName="figcaption"
                    value={caption}
                    className={__experimentalGetElementClassName('caption')}
                />
            )}
        </figure>
    );
}
