/**
 * Video — deprecation chain.
 *
 * Ported from `@wordpress/block-library/src/video/deprecated.js`
 * (v9.43.0). Upstream ships a single deprecation entry (the pre
 * `wp-element-caption` figcaption className). It is preserved verbatim
 * under the new namespace so legacy `core/video` markup deserializes
 * cleanly after a `core/video` → `artisanpack/video` round-trip via the
 * block transforms in `transforms.ts`.
 */

import type { ReactElement } from 'react';
import { RichText, useBlockProps } from '@wordpress/block-editor';

import metadata from './block.json';
import Tracks, { type VideoTrack } from './tracks';

const { attributes: blockAttributes } = metadata;

interface DeprecatedV1Attributes {
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

const v1 = {
    attributes: blockAttributes,
    save({ attributes }: { attributes: DeprecatedV1Attributes }): ReactElement {
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
                    <RichText.Content tagName="figcaption" value={caption} />
                )}
            </figure>
        );
    },
};

const deprecated = [v1];

export default deprecated;
