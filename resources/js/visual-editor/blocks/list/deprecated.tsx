/**
 * List — deprecation chain.
 *
 * Full port of `@wordpress/block-library/src/list/deprecated.js` (v9.43.0).
 * v3 → v0 cover historical save shapes. v0 + v1 migrate the legacy
 * `values` HTML attribute into inner `artisanpack/list-item` blocks via
 * `migrateToListV2`. v2 migrates the legacy `type` attribute (A/a/I/i)
 * into the modern `list-style-type` inline-style values.
 */

import { RichText, InnerBlocks, useBlockProps } from '@wordpress/block-editor';
import { createBlock } from '@wordpress/blocks';

import { migrateFontFamily } from '../_shared/migrate-font-family';

interface LegacyListAttributes {
    ordered?: boolean;
    values?: string;
    type?: string;
    start?: number;
    reversed?: boolean;
    placeholder?: string;
    style?: { typography?: { fontFamily?: string } };
    [key: string]: unknown;
}

const LIST_STYLES: Record<string, string> = {
    A: 'upper-alpha',
    a: 'lower-alpha',
    I: 'upper-roman',
    i: 'lower-roman',
};

function migrateTypeToInlineStyle(attributes: LegacyListAttributes): LegacyListAttributes {
    const { type } = attributes;
    if (type && LIST_STYLES[type]) {
        return { ...attributes, type: LIST_STYLES[type] };
    }
    return attributes;
}

/**
 * Synthesizes inner `artisanpack/list-item` blocks from the legacy `values`
 * HTML string. Upstream uses `rawHandler` which involves the paste pipeline;
 * we do a lightweight DOM parse to avoid pulling that dependency.
 */
function migrateToListV2(
    attributes: LegacyListAttributes
): [LegacyListAttributes, unknown[]] {
    const { values, start, reversed, ordered, type, ...rest } = attributes;
    const tagName = ordered ? 'ol' : 'ul';

    const container =
        typeof document !== 'undefined'
            ? document.createElement('div')
            : null;
    let innerBlocks: unknown[] = [];

    if (container) {
        container.innerHTML = `<${tagName}>${values ?? ''}</${tagName}>`;
        const list = container.firstChild as HTMLElement | null;
        if (list) {
            innerBlocks = Array.from(list.children).map((item) =>
                createBlock('artisanpack/list-item', { content: item.innerHTML })
            );
        }
    }

    return [
        {
            ...rest,
            ordered,
            type: type && LIST_STYLES[type] ? LIST_STYLES[type] : type,
            ...(start !== undefined && { start }),
            ...(reversed !== undefined && { reversed }),
        },
        innerBlocks,
    ];
}

const baseAttributes = {
    ordered: { type: 'boolean', default: false, role: 'content' },
    values: {
        type: 'string',
        source: 'html',
        selector: 'ol,ul',
        multiline: 'li',
        __unstableMultilineWrapperTags: ['ol', 'ul'],
        default: '',
        role: 'content',
    },
    type: { type: 'string' },
    start: { type: 'number' },
    reversed: { type: 'boolean' },
    placeholder: { type: 'string' },
} as const;

const v0 = {
    attributes: baseAttributes,
    supports: {
        anchor: true,
        className: false,
        typography: { fontSize: true, __experimentalFontFamily: true },
        color: { gradients: true, link: true },
        __unstablePasteTextInline: true,
        __experimentalSelector: 'ol,ul',
        __experimentalSlashInserter: true,
    },
    save({ attributes }: { attributes: LegacyListAttributes }) {
        const { ordered, values, type, reversed, start } = attributes;
        const TagName = ordered ? 'ol' : 'ul';
        return (
            <TagName {...useBlockProps.save({ type, reversed, start })}>
                <RichText.Content value={values} multiline="li" />
            </TagName>
        );
    },
    migrate: migrateFontFamily,
    isEligible({ style }: LegacyListAttributes) {
        return !!style?.typography?.fontFamily;
    },
};

const v1 = {
    attributes: baseAttributes,
    supports: {
        anchor: true,
        className: false,
        typography: { fontSize: true, lineHeight: true },
        color: { gradients: true, link: true },
        __unstablePasteTextInline: true,
        __experimentalSelector: 'ol,ul',
        __experimentalSlashInserter: true,
    },
    save({ attributes }: { attributes: LegacyListAttributes }) {
        const { ordered, values, type, reversed, start } = attributes;
        const TagName = ordered ? 'ol' : 'ul';
        return (
            <TagName {...useBlockProps.save({ type, reversed, start })}>
                <RichText.Content value={values} multiline="li" />
            </TagName>
        );
    },
    migrate: migrateToListV2,
};

const v2 = {
    attributes: baseAttributes,
    supports: {
        anchor: true,
        className: false,
        typography: { fontSize: true, lineHeight: true },
        color: { gradients: true, link: true },
        spacing: { margin: true, padding: true },
        __unstablePasteTextInline: true,
        __experimentalSelector: 'ol,ul',
        __experimentalSlashInserter: true,
    },
    isEligible({ type }: LegacyListAttributes) {
        return !!type;
    },
    save({ attributes }: { attributes: LegacyListAttributes }) {
        const { ordered, type, reversed, start } = attributes;
        const TagName = ordered ? 'ol' : 'ul';
        return (
            <TagName {...useBlockProps.save({ type, reversed, start })}>
                <InnerBlocks.Content />
            </TagName>
        );
    },
    migrate: migrateTypeToInlineStyle,
};

const v3 = {
    attributes: baseAttributes,
    supports: {
        anchor: true,
        className: false,
        typography: { fontSize: true, lineHeight: true },
        color: { gradients: true, link: true },
        spacing: { margin: true, padding: true },
        __unstablePasteTextInline: true,
        __experimentalSelector: 'ol,ul',
        __experimentalOnMerge: 'true',
        __experimentalSlashInserter: true,
    },
    save({ attributes }: { attributes: LegacyListAttributes }) {
        const { ordered, type, reversed, start } = attributes;
        const TagName = ordered ? 'ol' : 'ul';
        return (
            <TagName
                {...useBlockProps.save({
                    reversed,
                    start,
                    style: {
                        listStyleType:
                            ordered && type !== 'decimal' ? type : undefined,
                    },
                })}
            >
                <InnerBlocks.Content />
            </TagName>
        );
    },
};

const deprecated = [v3, v2, v1, v0];

export default deprecated;
