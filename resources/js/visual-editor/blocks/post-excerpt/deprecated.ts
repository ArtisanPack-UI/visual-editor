/**
 * Post Excerpt — deprecation chain.
 *
 * Ported from `@wordpress/block-library/src/post-excerpt/deprecated.js`
 * (v9.43.0). Single v1 entry migrating the legacy `textAlign` attribute to
 * the stabilized block support. Server-rendered, so `save` returns `null`.
 * Phase I5 entity cluster (#413).
 */

import migrateTextAlign from '../_shared/migrate-text-align';

interface LegacyAttributes {
    readonly textAlign?: string;
    readonly className?: string;
    readonly [ key: string ]: unknown;
}

const v1 = {
    attributes: {
        textAlign: { type: 'string' },
        moreText: { type: 'string', role: 'content' },
        showMoreOnNewLine: { type: 'boolean', default: true },
        excerptLength: { type: 'number', default: 55 },
    },
    supports: {
        anchor: true,
        html: false,
        color: {
            gradients: true,
            link: true,
            __experimentalDefaultControls: { background: true, text: true, link: true },
        },
        spacing: { margin: true, padding: true },
        typography: {
            fontSize: true,
            lineHeight: true,
            textColumns: true,
            __experimentalFontFamily: true,
            __experimentalFontWeight: true,
            __experimentalFontStyle: true,
            __experimentalTextTransform: true,
            __experimentalTextDecoration: true,
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
    save: (): null => null,
    migrate: ( attributes: LegacyAttributes ) => migrateTextAlign( attributes ),
    isEligible( attributes: LegacyAttributes ): boolean {
        return (
            !! attributes.textAlign ||
            !! attributes.className?.match( /\bhas-text-align-(left|center|right)\b/ )
        );
    },
};

const deprecated = [ v1 ];

export default deprecated;
