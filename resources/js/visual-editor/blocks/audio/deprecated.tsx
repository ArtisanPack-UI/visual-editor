/**
 * Audio — deprecation chain.
 *
 * Ported from `@wordpress/block-library/src/audio/deprecated.js`
 * (v9.43.0). Upstream ships a single deprecation entry (the pre-rich-text
 * caption serialization). It is kept verbatim under the new namespace so
 * legacy `core/audio` markup deserializes cleanly after a `core/audio` →
 * `artisanpack/audio` round-trip via the block transforms in
 * `transforms.ts`.
 */

import type { ReactElement } from 'react';
import { RichText } from '@wordpress/block-editor';

interface DeprecatedV1Attributes {
    readonly src?: string;
    readonly caption?: string;
    readonly autoplay?: boolean;
    readonly loop?: boolean;
    readonly preload?: string;
}

const v1 = {
    attributes: {
        src: {
            type: 'string',
            source: 'attribute',
            selector: 'audio',
            attribute: 'src',
        },
        caption: {
            type: 'string',
            source: 'html',
            selector: 'figcaption',
        },
        id: {
            type: 'number',
        },
        autoplay: {
            type: 'boolean',
            source: 'attribute',
            selector: 'audio',
            attribute: 'autoplay',
        },
        loop: {
            type: 'boolean',
            source: 'attribute',
            selector: 'audio',
            attribute: 'loop',
        },
        preload: {
            type: 'string',
            source: 'attribute',
            selector: 'audio',
            attribute: 'preload',
        },
    },
    supports: {
        align: true,
    },
    save({ attributes }: { attributes: DeprecatedV1Attributes }): ReactElement {
        const { autoplay, caption, loop, preload, src } = attributes;

        return (
            <figure>
                <audio
                    controls="controls"
                    src={src}
                    autoPlay={autoplay}
                    loop={loop}
                    preload={preload}
                />
                {!RichText.isEmpty(caption ?? '') && (
                    <RichText.Content tagName="figcaption" value={caption} />
                )}
            </figure>
        );
    },
};

export default [v1];
