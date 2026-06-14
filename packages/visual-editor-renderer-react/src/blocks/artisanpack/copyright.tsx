/**
 * React renderer for the `artisanpack/copyright` block (#500).
 *
 * Mirrors the Blade partial at
 * `packages/visual-editor-renderer-blade/resources/views/blocks/artisanpack/copyright.blade.php`
 * and the Vue renderer so every environment emits identical markup. The
 * current year is read at render time (not stamped server-side) so the
 * line stays accurate whenever the page is rendered.
 */

import type { JSX } from 'react';

import { attrString, classList } from '../../support/attributes';
import type { BlockRendererProps } from '../../types';

type CopyrightType = 'icon-text' | 'icon-only' | 'text-only';

const VALID_TYPES: ReadonlyArray<CopyrightType> = [
    'icon-text',
    'icon-only',
    'text-only',
];

function normalizeType(value: unknown): CopyrightType {
    const raw = attrString(value, 'icon-text');
    return (VALID_TYPES as ReadonlyArray<string>).includes(raw)
        ? (raw as CopyrightType)
        : 'icon-text';
}

function buildLine(type: CopyrightType, text: string, year: number): string {
    const trimmed = text.trim();
    if (type === 'icon-only') {
        return `© ${year}`;
    }
    if (type === 'text-only') {
        return trimmed === '' ? `${year}` : `${trimmed} ${year}`;
    }
    return trimmed === '' ? `© ${year}` : `© ${trimmed} ${year}`;
}

export function CopyrightBlock({ attributes }: BlockRendererProps): JSX.Element {
    const copyrightType = normalizeType(attributes.copyrightType);
    const copyrightText = attrString(attributes.copyrightText, 'Copyright');
    const className = attrString(attributes.className);

    const classes = classList(['ap-copyright', className]);
    const year = new Date().getUTCFullYear();
    const line = buildLine(copyrightType, copyrightText, year);

    return <p className={classes}>{line}</p>;
}
