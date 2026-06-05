/**
 * Pullquote — saved markup.
 *
 * Ported from `@wordpress/block-library/src/pullquote/save.js` (v9.43.0).
 */

import type { ReactElement } from 'react';
import clsx from 'clsx';
import { RichText, useBlockProps } from '@wordpress/block-editor';

interface PullquoteSaveAttributes {
    readonly textAlign?: string;
    readonly citation?: string;
    readonly value?: string;
}

interface PullquoteSaveProps {
    readonly attributes: PullquoteSaveAttributes;
}

export default function PullquoteSave({
    attributes,
}: PullquoteSaveProps): ReactElement {
    const { textAlign, citation, value } = attributes;
    const shouldShowCitation = !RichText.isEmpty(citation);

    return (
        <figure
            {...useBlockProps.save({
                className: clsx({
                    [`has-text-align-${textAlign}`]: textAlign,
                }),
            })}
        >
            <blockquote>
                <RichText.Content tagName="p" value={value} />
                {shouldShowCitation && (
                    <RichText.Content tagName="cite" value={citation} />
                )}
            </blockquote>
        </figure>
    );
}
