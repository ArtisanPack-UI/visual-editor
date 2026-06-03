/**
 * Post Navigation Link — deprecation chain.
 *
 * Ported from `@wordpress/block-library/src/post-navigation-link/deprecated.js`
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
        type: { type: 'string', default: 'next' },
        label: { type: 'string', role: 'content' },
        showTitle: { type: 'boolean', default: false },
        linkLabel: { type: 'boolean', default: false },
        arrow: { type: 'string', default: 'none' },
        taxonomy: { type: 'string', default: '' },
    },
    supports: {
        anchor: true,
        reusable: false,
        html: false,
        color: { link: true },
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
