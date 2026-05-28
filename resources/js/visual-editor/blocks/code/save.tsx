/**
 * Code — saved markup.
 *
 * Ported from `@wordpress/block-library/src/code/save.js` (v9.43.0).
 * Save shape is byte-equivalent to upstream `core/code`.
 */

import type { ReactElement } from 'react';
import { RichText, useBlockProps } from '@wordpress/block-editor';

import { escape } from './utils';

interface ContentValue {
    toHTMLString?: (opts: { preserveWhiteSpace: boolean }) => string;
}

interface CodeSaveAttributes {
    readonly content: string | ContentValue;
}

interface CodeSaveProps {
    readonly attributes: CodeSaveAttributes;
}

export default function CodeSave({ attributes }: CodeSaveProps): ReactElement {
    return (
        <pre {...useBlockProps.save()}>
            <RichText.Content
                tagName="code"
                value={escape(
                    typeof attributes.content === 'string'
                        ? attributes.content
                        : attributes.content.toHTMLString?.({
                              preserveWhiteSpace: true,
                          }) ?? ''
                )}
            />
        </pre>
    );
}
