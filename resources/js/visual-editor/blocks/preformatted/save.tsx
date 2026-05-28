/**
 * Preformatted — saved markup.
 *
 * Ported from `@wordpress/block-library/src/preformatted/save.js` (v9.43.0).
 */

import type { ReactElement } from 'react';
import { RichText, useBlockProps } from '@wordpress/block-editor';

interface PreformattedSaveAttributes {
    readonly content: string;
}

interface PreformattedSaveProps {
    readonly attributes: PreformattedSaveAttributes;
}

export default function PreformattedSave({
    attributes,
}: PreformattedSaveProps): ReactElement {
    const { content } = attributes;

    return (
        <pre {...useBlockProps.save()}>
            <RichText.Content value={content} />
        </pre>
    );
}
