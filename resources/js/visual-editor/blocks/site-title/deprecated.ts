/**
 * Site Title — deprecation chain.
 *
 * Ported from `@wordpress/block-library/src/site-title/deprecated.js`
 * (v9.43.0). v2 migrates legacy `textAlign`; v1 migrates a custom font
 * family. Server-rendered, so every `save` returns `null`. Phase I5 entity
 * cluster (#413).
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
        level: { type: 'number', default: 1 },
        levelOptions: { type: 'array', default: [ 0, 1, 2, 3, 4, 5, 6 ] },
        isLink: { type: 'boolean', default: true, role: 'content' },
        linkTarget: { type: 'string', default: '_self', role: 'content' },
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
        spacing: {
            padding: true,
            margin: true,
            __experimentalDefaultControls: { margin: false, padding: false },
        },
        typography: {
            fontSize: true,
            lineHeight: true,
            __experimentalFontFamily: true,
            __experimentalTextTransform: true,
            __experimentalTextDecoration: true,
            __experimentalFontStyle: true,
            __experimentalFontWeight: true,
            __experimentalLetterSpacing: true,
            __experimentalWritingMode: true,
            __experimentalDefaultControls: { fontSize: true },
        },
        interactivity: { clientNavigation: true },
        __experimentalBorder: { radius: true, color: true, width: true, style: true },
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
        level: { type: 'number', default: 1 },
        textAlign: { type: 'string' },
        isLink: { type: 'boolean', default: true },
        linkTarget: { type: 'string', default: '_self' },
    },
    supports: {
        align: [ 'wide', 'full' ],
        html: false,
        color: { gradients: true, link: true },
        spacing: { padding: true, margin: true },
        typography: {
            fontSize: true,
            lineHeight: true,
            __experimentalFontFamily: true,
            __experimentalTextTransform: true,
            __experimentalFontStyle: true,
            __experimentalFontWeight: true,
            __experimentalLetterSpacing: true,
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
