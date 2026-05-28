/**
 * Verse — saved markup.
 *
 * Ported from `@wordpress/block-library/src/verse/save.js` (v9.43.0).
 */

import type { ReactElement } from 'react';
import { RichText, useBlockProps } from '@wordpress/block-editor';

interface VerseSaveAttributes {
    readonly content: string;
}

interface VerseSaveProps {
    readonly attributes: VerseSaveAttributes;
}

export default function VerseSave({ attributes }: VerseSaveProps): ReactElement {
    const { content } = attributes;
    return (
        <pre {...useBlockProps.save()}>
            <RichText.Content value={content} />
        </pre>
    );
}
