/**
 * Quote — deprecation chain.
 *
 * Full port of `@wordpress/block-library/src/quote/deprecated.js` (v9.43.0).
 * v4 → v0 cover historical save shapes; v3+v2+v1+v0 also migrate the legacy
 * `value` attribute into core/paragraph inner blocks.
 */

import clsx from 'clsx';
import {
    InnerBlocks,
    RichText,
    useBlockProps,
} from '@wordpress/block-editor';
import { createBlock, parseWithAttributeSchema } from '@wordpress/blocks';

interface LegacyQuoteAttributes {
    value?: string;
    citation?: string;
    align?: string;
    textAlign?: string;
    style?: number | Record<string, unknown>;
    className?: string;
    [key: string]: unknown;
}

const TEXT_ALIGN_OPTIONS = ['left', 'right', 'center'] as const;

export const migrateToQuoteV2 = (
    attributes: LegacyQuoteAttributes
): [LegacyQuoteAttributes, unknown[]] => {
    const { value, ...restAttributes } = attributes;

    return [
        { ...restAttributes },
        value
            ? parseWithAttributeSchema(value, {
                  type: 'array',
                  source: 'query',
                  selector: 'p',
                  query: {
                      content: {
                          type: 'string',
                          source: 'html',
                      },
                  },
              }).map(({ content }: { content: string }) =>
                  createBlock('core/paragraph', { content })
              )
            : [createBlock('core/paragraph')],
    ];
};

const migrateTextAlign = (
    attributes: LegacyQuoteAttributes,
    innerBlocks: unknown[]
): [LegacyQuoteAttributes, unknown[]] => {
    const { align, ...rest } = attributes;
    const migratedAttributes = TEXT_ALIGN_OPTIONS.includes(
        align as 'left' | 'right' | 'center'
    )
        ? { ...rest, textAlign: align }
        : attributes;
    return [migratedAttributes, innerBlocks];
};

const migrateLargeStyle = (
    attributes: LegacyQuoteAttributes,
    innerBlocks: unknown[]
): [LegacyQuoteAttributes, unknown[]] => {
    return [
        {
            ...attributes,
            className: attributes.className
                ? `${attributes.className} is-style-large`
                : 'is-style-large',
        },
        innerBlocks,
    ];
};

const v4 = {
    attributes: {
        value: {
            type: 'string',
            source: 'html',
            selector: 'blockquote',
            multiline: 'p',
            default: '',
            role: 'content',
        },
        citation: {
            type: 'string',
            source: 'html',
            selector: 'cite',
            default: '',
            role: 'content',
        },
        align: { type: 'string' },
    },
    supports: {
        anchor: true,
        html: false,
        __experimentalOnEnter: true,
        __experimentalOnMerge: true,
    },
    isEligible: ({ align }: LegacyQuoteAttributes) =>
        TEXT_ALIGN_OPTIONS.includes(align as 'left' | 'right' | 'center'),
    save({ attributes }: { attributes: LegacyQuoteAttributes }) {
        const { align, citation } = attributes;
        const className = clsx({
            [`has-text-align-${align}`]: align,
        });
        return (
            <blockquote {...useBlockProps.save({ className })}>
                <InnerBlocks.Content />
                {!RichText.isEmpty(citation) && (
                    <RichText.Content tagName="cite" value={citation} />
                )}
            </blockquote>
        );
    },
    migrate: migrateTextAlign,
};

const v3 = {
    attributes: {
        value: {
            type: 'string',
            source: 'html',
            selector: 'blockquote',
            multiline: 'p',
            default: '',
            role: 'content',
        },
        citation: {
            type: 'string',
            source: 'html',
            selector: 'cite',
            default: '',
            role: 'content',
        },
        align: { type: 'string' },
    },
    supports: { anchor: true },
    save({ attributes }: { attributes: LegacyQuoteAttributes }) {
        const { align, value, citation } = attributes;
        const className = clsx({
            [`has-text-align-${align}`]: align,
        });
        return (
            <blockquote {...useBlockProps.save({ className })}>
                <RichText.Content multiline value={value} />
                {!RichText.isEmpty(citation) && (
                    <RichText.Content tagName="cite" value={citation} />
                )}
            </blockquote>
        );
    },
    migrate(attributes: LegacyQuoteAttributes) {
        return migrateTextAlign(...migrateToQuoteV2(attributes));
    },
};

const v2 = {
    attributes: {
        value: {
            type: 'string',
            source: 'html',
            selector: 'blockquote',
            multiline: 'p',
            default: '',
        },
        citation: {
            type: 'string',
            source: 'html',
            selector: 'cite',
            default: '',
        },
        align: { type: 'string' },
    },
    migrate(attributes: LegacyQuoteAttributes) {
        return migrateTextAlign(...migrateToQuoteV2(attributes));
    },
    save({ attributes }: { attributes: LegacyQuoteAttributes }) {
        const { align, value, citation } = attributes;
        return (
            <blockquote style={{ textAlign: align ? align : undefined }}>
                <RichText.Content multiline value={value} />
                {!RichText.isEmpty(citation) && (
                    <RichText.Content tagName="cite" value={citation} />
                )}
            </blockquote>
        );
    },
};

const v1 = {
    attributes: {
        value: {
            type: 'string',
            source: 'html',
            selector: 'blockquote',
            multiline: 'p',
            default: '',
        },
        citation: {
            type: 'string',
            source: 'html',
            selector: 'cite',
            default: '',
        },
        align: { type: 'string' },
        style: { type: 'number', default: 1 },
    },
    migrate(attributes: LegacyQuoteAttributes) {
        if (attributes.style === 2) {
            const { style, ...restAttributes } = attributes;
            return migrateTextAlign(
                ...migrateLargeStyle(...migrateToQuoteV2(restAttributes))
            );
        }
        return migrateTextAlign(...migrateToQuoteV2(attributes));
    },
    save({ attributes }: { attributes: LegacyQuoteAttributes }) {
        const { align, value, citation, style } = attributes;
        return (
            <blockquote
                className={style === 2 ? 'is-large' : ''}
                style={{ textAlign: align ? align : undefined }}
            >
                <RichText.Content multiline value={value} />
                {!RichText.isEmpty(citation) && (
                    <RichText.Content tagName="cite" value={citation} />
                )}
            </blockquote>
        );
    },
};

const v0 = {
    attributes: {
        value: {
            type: 'string',
            source: 'html',
            selector: 'blockquote',
            multiline: 'p',
            default: '',
        },
        citation: {
            type: 'string',
            source: 'html',
            selector: 'footer',
            default: '',
        },
        align: { type: 'string' },
        style: { type: 'number', default: 1 },
    },
    migrate(attributes: LegacyQuoteAttributes) {
        if (!isNaN(parseInt(String(attributes.style)))) {
            const { style, ...restAttributes } = attributes;
            return migrateTextAlign(...migrateToQuoteV2(restAttributes));
        }
        return migrateTextAlign(...migrateToQuoteV2(attributes));
    },
    save({ attributes }: { attributes: LegacyQuoteAttributes }) {
        const { align, value, citation, style } = attributes;
        return (
            <blockquote
                className={`blocks-quote-style-${style}`}
                style={{ textAlign: align ? align : undefined }}
            >
                <RichText.Content multiline value={value} />
                {!RichText.isEmpty(citation) && (
                    <RichText.Content tagName="footer" value={citation} />
                )}
            </blockquote>
        );
    },
};

const deprecated = [v4, v3, v2, v1, v0];

export default deprecated;
