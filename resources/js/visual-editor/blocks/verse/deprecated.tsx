/**
 * Verse — deprecation chain.
 *
 * Full port of `@wordpress/block-library/src/verse/deprecated.js` (v9.43.0).
 * v3 → v1 covered.
 */

import clsx from 'clsx';
import { RichText, useBlockProps } from '@wordpress/block-editor';

import { migrateFontFamily } from '../_shared/migrate-font-family';
import { migrateTextAlignAttributeToBlockSupport as migrateTextAlign } from '../_shared/migrate-text-align';

interface LegacyVerseAttributes {
    content?: string;
    textAlign?: string;
    style?: {
        typography?: {
            fontFamily?: string;
        } & Record<string, unknown>;
    } & Record<string, unknown>;
    fontFamily?: string;
    className?: string;
    [key: string]: unknown;
}

const v1 = {
    attributes: {
        content: {
            type: 'string',
            source: 'html',
            selector: 'pre',
            default: '',
        },
        textAlign: { type: 'string' },
    },
    save({ attributes }: { attributes: LegacyVerseAttributes }) {
        const { textAlign, content } = attributes;
        return (
            <RichText.Content
                tagName="pre"
                style={{ textAlign }}
                value={content}
            />
        );
    },
    migrate: migrateTextAlign,
};

const v2 = {
    attributes: {
        content: {
            type: 'string',
            source: 'html',
            selector: 'pre',
            default: '',
            __unstablePreserveWhiteSpace: true,
            role: 'content',
        },
        textAlign: { type: 'string' },
    },
    supports: {
        anchor: true,
        color: { gradients: true, link: true },
        typography: { fontSize: true, __experimentalFontFamily: true },
        spacing: { padding: true },
    },
    save({ attributes }: { attributes: LegacyVerseAttributes }) {
        const { textAlign, content } = attributes;
        const className = clsx({
            [`has-text-align-${textAlign}`]: textAlign,
        });
        return (
            <pre {...useBlockProps.save({ className })}>
                <RichText.Content value={content} />
            </pre>
        );
    },
    migrate(attributes: LegacyVerseAttributes) {
        return migrateTextAlign(migrateFontFamily(attributes));
    },
    isEligible({ style, textAlign }: LegacyVerseAttributes) {
        return !!style?.typography?.fontFamily || !!textAlign;
    },
};

const v3 = {
    attributes: {
        content: {
            type: 'rich-text',
            source: 'rich-text',
            selector: 'pre',
            __unstablePreserveWhiteSpace: true,
            role: 'content',
        },
        textAlign: { type: 'string' },
    },
    supports: {
        anchor: true,
        background: { backgroundImage: true, backgroundSize: true },
        color: { gradients: true, link: true },
        dimensions: { minHeight: true },
        typography: {
            fontSize: true,
            __experimentalFontFamily: true,
            lineHeight: true,
            __experimentalFontStyle: true,
            __experimentalFontWeight: true,
            __experimentalLetterSpacing: true,
            __experimentalTextTransform: true,
            __experimentalTextDecoration: true,
            __experimentalWritingMode: true,
        },
        spacing: { margin: true, padding: true },
        __experimentalBorder: {
            radius: true,
            width: true,
            color: true,
            style: true,
        },
        interactivity: { clientNavigation: true },
    },
    save({ attributes }: { attributes: LegacyVerseAttributes }) {
        const { textAlign, content } = attributes;
        const className = clsx({
            [`has-text-align-${textAlign}`]: textAlign,
        });
        return (
            <pre {...useBlockProps.save({ className })}>
                <RichText.Content value={content} />
            </pre>
        );
    },
    migrate: migrateTextAlign,
    isEligible(attributes: LegacyVerseAttributes) {
        return (
            !!attributes.textAlign ||
            !!attributes.className?.match(/\bhas-text-align-(left|center|right)\b/)
        );
    },
};

const deprecated = [v3, v2, v1];

export default deprecated;
