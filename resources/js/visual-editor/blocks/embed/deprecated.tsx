/**
 * Embed — deprecation chain.
 *
 * Ported from `@wordpress/block-library/src/embed/deprecated.js`
 * (v9.43.0). Upstream ships two deprecation entries: v2 (pre
 * `wp-element-caption` classname) and v1 (pre figure-wrapping
 * markup). Both are kept verbatim under the new namespace so legacy
 * `core/embed` markup deserializes cleanly after a `core/embed` →
 * `artisanpack/embed` round-trip via the block transforms in
 * `transforms.ts`.
 */

import type { ReactElement } from 'react';
import clsx from 'clsx';
import { RichText, useBlockProps } from '@wordpress/block-editor';

import metadata from './block.json';

const { attributes: blockAttributes } = metadata;

interface DeprecatedAttributes {
    readonly url?: string;
    readonly caption?: string;
    readonly type?: string;
    readonly providerNameSlug?: string;
}

// In #41140 support was added to global styles for caption elements which
// added a `wp-element-caption` classname to the embed figcaption element.
const v2 = {
    attributes: blockAttributes,
    save({ attributes }: { attributes: DeprecatedAttributes }): ReactElement | null {
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
                    <RichText.Content tagName="figcaption" value={caption} />
                )}
            </figure>
        );
    },
};

const v1 = {
    attributes: blockAttributes,
    save({
        attributes: { url, caption, type, providerNameSlug },
    }: {
        attributes: DeprecatedAttributes;
    }): ReactElement | null {
        if (!url) {
            return null;
        }

        const embedClassName = clsx('wp-block-embed', {
            [`is-type-${type}`]: type,
            [`is-provider-${providerNameSlug}`]: providerNameSlug,
        });

        return (
            <figure className={embedClassName}>
                {`\n${url}\n` /* URL needs to be on its own line. */}
                {!RichText.isEmpty(caption ?? '') && (
                    <RichText.Content tagName="figcaption" value={caption} />
                )}
            </figure>
        );
    },
};

const deprecated = [v2, v1];

export default deprecated;
