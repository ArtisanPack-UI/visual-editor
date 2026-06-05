/**
 * Term Description — deprecation chain.
 *
 * Ported from `@wordpress/block-library/src/term-description/deprecated.js`
 * (v9.43.0). Single v1 entry migrating the legacy `textAlign` attribute to
 * the stabilized block support. Server-rendered, so `save` returns `null`.
 * Phase I-Block-Fork — post navigation / metadata family (#520).
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
    },
    supports: {
        anchor: true,
        align: [ 'wide', 'full' ],
        html: false,
        color: {
            link: true,
            __experimentalDefaultControls: { background: true, text: true },
        },
        spacing: { margin: true, padding: true },
        typography: {
            fontSize: true,
            lineHeight: true,
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
