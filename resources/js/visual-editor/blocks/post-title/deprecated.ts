/**
 * Post Title — deprecation chain.
 *
 * Ported from `@wordpress/block-library/src/post-title/deprecated.js`
 * (v9.43.0). `core/post-title` is server-rendered, so every entry's `save`
 * returns `null` and the deprecations exist only to migrate legacy
 * attribute shapes (text-align → block support, custom font family →
 * `style.typography`). The shared `_shared/migrate-*` helpers are the same
 * ones the content cluster (I1) vendored. Phase I5 entity cluster (#413).
 */

import migrateFontFamily from '../_shared/migrate-font-family';
import migrateTextAlign from '../_shared/migrate-text-align';

interface LegacyAttributes {
    readonly textAlign?: string;
    readonly className?: string;
    readonly style?: { readonly typography?: { readonly fontFamily?: string } };
    readonly [ key: string ]: unknown;
}

const v2 = {
    attributes: {
        textAlign: { type: 'string' },
        level: { type: 'number', default: 2 },
        levelOptions: { type: 'array' },
        isLink: { type: 'boolean', default: false },
        rel: { type: 'string', attribute: 'rel', default: '' },
        linkTarget: { type: 'string', default: '_self' },
    },
    supports: {
        anchor: true,
        align: [ 'wide', 'full' ],
        html: false,
        color: {
            gradients: true,
            link: true,
            __experimentalDefaultControls: { background: true, text: true, link: true },
        },
        spacing: { padding: true, margin: true },
        typography: {
            fontSize: true,
            lineHeight: true,
            __experimentalFontFamily: true,
            __experimentalTextTransform: true,
            __experimentalTextDecoration: true,
            __experimentalFontStyle: true,
            __experimentalFontWeight: true,
            __experimentalLetterSpacing: true,
            __experimentalDefaultControls: { fontSize: true },
        },
        interactivity: { clientNavigation: true },
        __experimentalBorder: {
            radius: true,
            color: true,
            width: true,
            style: true,
            __experimentalDefaultControls: { radius: true, color: true, width: true, style: true },
        },
    },
    migrate: ( attributes: LegacyAttributes ) => migrateTextAlign( attributes ),
    isEligible( attributes: LegacyAttributes ): boolean {
        return (
            !! attributes.textAlign ||
            !! attributes.className?.match( /\bhas-text-align-(left|center|right)\b/ )
        );
    },
    save: (): null => null,
};

const v1 = {
    attributes: {
        textAlign: { type: 'string' },
        level: { type: 'number', default: 2 },
        isLink: { type: 'boolean', default: false },
        rel: { type: 'string', attribute: 'rel', default: '' },
        linkTarget: { type: 'string', default: '_self' },
    },
    supports: {
        align: [ 'wide', 'full' ],
        html: false,
        color: { gradients: true, link: true },
        spacing: { margin: true },
        typography: {
            fontSize: true,
            lineHeight: true,
            __experimentalFontFamily: true,
            __experimentalFontWeight: true,
            __experimentalFontStyle: true,
            __experimentalTextTransform: true,
        },
    },
    save: (): null => null,
    migrate: ( attributes: LegacyAttributes ) => migrateFontFamily( attributes ),
    isEligible( { style }: LegacyAttributes ): boolean {
        return !! style?.typography?.fontFamily;
    },
};

const deprecated = [ v2, v1 ];

export default deprecated;
