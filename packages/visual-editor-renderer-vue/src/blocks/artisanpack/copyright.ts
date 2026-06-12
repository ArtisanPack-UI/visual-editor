/**
 * Vue renderer for the `artisanpack/copyright` block (#500).
 *
 * Mirrors the Blade partial and the React renderer so every environment
 * emits identical markup. The current year is read at render time (not
 * stamped server-side) so the line stays accurate.
 */

import { defineComponent, h } from 'vue';

import { attrString, classList } from '../../support/attributes';
import { blockRendererProps } from '../shared';

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

export const CopyrightBlock = defineComponent({
    name: 'CopyrightBlock',
    props: blockRendererProps,
    setup(props) {
        return () => {
            const copyrightType = normalizeType(props.attributes.copyrightType);
            const copyrightText = attrString(props.attributes.copyrightText, 'Copyright');
            const className = attrString(props.attributes.className);

            const classes = classList(['ap-copyright', className]);
            const year = new Date().getUTCFullYear();
            const line = buildLine(copyrightType, copyrightText, year);

            return h('p', { class: classes }, line);
        };
    },
});
