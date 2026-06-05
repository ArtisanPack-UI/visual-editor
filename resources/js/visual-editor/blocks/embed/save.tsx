/**
 * Embed — save (front-end serialization).
 *
 * Ported from `@wordpress/block-library/src/embed/save.js` (v9.43.0).
 * Behaviour is byte-equivalent to upstream — the only change is the
 * namespace swap to `artisanpack/embed`. Mixed documents containing
 * `core/embed` and `artisanpack/embed` render to identical HTML.
 */

import type { ReactElement } from 'react';
import clsx from 'clsx';
import {
    RichText,
    useBlockProps,
    __experimentalGetElementClassName,
} from '@wordpress/block-editor';

interface EmbedAttributes {
    readonly url?: string;
    readonly caption?: string;
    readonly type?: string;
    readonly providerNameSlug?: string;
}

interface EmbedSaveProps {
    readonly attributes: EmbedAttributes;
}

export default function save({ attributes }: EmbedSaveProps): ReactElement | null {
    const { url, caption, type, providerNameSlug } = attributes;

    if (!url) {
        return null;
    }

    const className = clsx('wp-block-embed', {
        [`is-type-${type}`]: type,
        [`is-provider-${providerNameSlug}`]: providerNameSlug,
        [`wp-block-embed-${providerNameSlug}`]: providerNameSlug,
    });

    return (
        <figure {...useBlockProps.save({ className })}>
            <div className="wp-block-embed__wrapper">
                {`\n${url}\n` /* URL needs to be on its own line. */}
            </div>
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
