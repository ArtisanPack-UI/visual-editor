/**
 * Details — save component.
 *
 * Ported from `@wordpress/block-library/src/details/save.js` (v9.43.0).
 * Adds an explicit `wp-block-details` class so the saved markup is
 * byte-equivalent regardless of namespace.
 */

import type { ReactElement } from 'react';
import clsx from 'clsx';
import {
    RichText,
    useBlockProps,
    InnerBlocks,
} from '@wordpress/block-editor';

interface DetailsSaveAttributes {
    readonly name?: string;
    readonly showContent?: boolean;
    readonly summary?: string;
}

export default function detailsSave({
    attributes,
}: {
    attributes: DetailsSaveAttributes;
}): ReactElement {
    const { name, showContent } = attributes;
    const summary = attributes.summary ? attributes.summary : 'Details';
    // eslint-disable-next-line @typescript-eslint/no-explicit-any
    const blockProps = (useBlockProps.save as any)({
        className: clsx('wp-block-details'),
    });

    return (
        <details
            {...blockProps}
            name={name || undefined}
            open={showContent}
        >
            <summary>
                {/* eslint-disable-next-line @typescript-eslint/no-explicit-any */}
                <RichText.Content value={summary as any} />
            </summary>
            <InnerBlocks.Content />
        </details>
    );
}
