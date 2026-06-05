/**
 * Heading — saved markup.
 *
 * Ported from `@wordpress/block-library/src/heading/save.js` (v9.43.0).
 * Output is byte-equivalent to upstream so documents authored against
 * `core/heading` round-trip losslessly through the `core/heading` ↔
 * `artisanpack/heading` transforms.
 */

import type { ReactElement } from 'react';
import { RichText, useBlockProps } from '@wordpress/block-editor';

interface HeadingSaveAttributes {
    readonly content: string;
    readonly level: number;
}

interface HeadingSaveProps {
    readonly attributes: HeadingSaveAttributes;
}

// Clamp the level attribute to a valid heading range before constructing
// the tag — guards against malformed legacy attributes (level=0, level=7)
// that would otherwise produce invalid `<h0>` / `<h7>` markup.
function clampHeadingLevel(level: number | undefined): 1 | 2 | 3 | 4 | 5 | 6 {
    const numeric = Number(level);
    if (!Number.isFinite(numeric)) {
        return 2;
    }
    return Math.max(1, Math.min(6, Math.trunc(numeric))) as 1 | 2 | 3 | 4 | 5 | 6;
}

export default function HeadingSave({ attributes }: HeadingSaveProps): ReactElement {
    const { content, level } = attributes;
    const safeLevel = clampHeadingLevel(level);
    const TagName = `h${safeLevel}` as 'h1' | 'h2' | 'h3' | 'h4' | 'h5' | 'h6';

    return (
        <TagName {...useBlockProps.save()}>
            <RichText.Content value={content} />
        </TagName>
    );
}
