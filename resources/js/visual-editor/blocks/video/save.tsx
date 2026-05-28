/**
 * Video — save (front-end serialization).
 *
 * Ported from `@wordpress/block-library/src/video/save.js` (v9.43.0).
 * Behaviour is byte-equivalent to upstream — the only change is the
 * namespace swap to `artisanpack/video`. Mixed documents containing
 * `core/video` and `artisanpack/video` render to identical HTML.
 */

import type { ReactElement } from 'react';
import {
    RichText,
    useBlockProps,
    __experimentalGetElementClassName,
} from '@wordpress/block-editor';

import Tracks, { type VideoTrack } from './tracks';

interface VideoAttributes {
    readonly autoplay?: boolean;
    readonly caption?: string;
    readonly controls?: boolean;
    readonly loop?: boolean;
    readonly muted?: boolean;
    readonly poster?: string;
    readonly preload?: string;
    readonly src?: string;
    readonly playsInline?: boolean;
    readonly tracks?: readonly VideoTrack[];
}

interface VideoSaveProps {
    readonly attributes: VideoAttributes;
}

export default function save({ attributes }: VideoSaveProps): ReactElement {
    const {
        autoplay,
        caption,
        controls,
        loop,
        muted,
        poster,
        preload,
        src,
        playsInline,
        tracks,
    } = attributes;

    return (
        <figure {...useBlockProps.save()}>
            {src && (
                <video
                    autoPlay={autoplay}
                    controls={controls}
                    loop={loop}
                    muted={muted}
                    poster={poster}
                    preload={preload !== 'metadata' ? preload : undefined}
                    src={src}
                    playsInline={playsInline}
                >
                    <Tracks tracks={tracks} />
                </video>
            )}
            {!RichText.isEmpty(caption ?? '') && (
                <RichText.Content
                    className={__experimentalGetElementClassName('caption')}
                    tagName="figcaption"
                    value={caption}
                />
            )}
        </figure>
    );
}
