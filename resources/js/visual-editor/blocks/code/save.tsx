/**
 * Code — saved markup.
 *
 * Ported from `@wordpress/block-library/src/code/save.js` (v9.43.0).
 * Save shape is byte-equivalent to upstream `core/code` until a language is
 * set (#1044), so existing blocks need no deprecation.
 */

import type { ReactElement } from 'react';
import { RichText, useBlockProps } from '@wordpress/block-editor';

import { escape } from './utils';

interface ContentValue {
    toHTMLString?: (opts: { preserveWhiteSpace: boolean }) => string;
}

interface CodeSaveAttributes {
    readonly content: string | ContentValue | null | undefined;
    readonly language?: string;
}

interface CodeSaveProps {
    readonly attributes: CodeSaveAttributes;
}

function resolveContent(
    content: string | ContentValue | null | undefined
): string {
    if (typeof content === 'string') {
        return content;
    }
    // Guard the optional rich-text value: legacy serialized attributes can
    // arrive null/undefined for empty code blocks; the `toHTMLString` call
    // chain would otherwise blow up on the first paint.
    return content?.toHTMLString?.({ preserveWhiteSpace: true }) ?? '';
}

export default function CodeSave({ attributes }: CodeSaveProps): ReactElement {
    return (
        <pre {...useBlockProps.save()}>
            <RichText.Content
                tagName="code"
                className={attributes.language ? `language-${attributes.language}` : undefined}
                value={escape(resolveContent(attributes.content))}
            />
        </pre>
    );
}
