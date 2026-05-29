/**
 * Post Date — deprecation chain.
 *
 * Ported from `@wordpress/block-library/src/post-date/deprecated.js`
 * (v9.43.0). Server-rendered, so every `save` returns `null`. The chain
 * covers: v4 legacy `textAlign`; v3 binding-arg rename (`key` → `field`);
 * v2 legacy `displayType` → `core/post-data` binding; v1 custom font
 * family. Phase I5 entity cluster (#413).
 */

import clsx from 'clsx';

import migrateFontFamily from '../_shared/migrate-font-family';
import migrateTextAlign from '../_shared/migrate-text-align';

// The binding migrations reshape deeply-nested attribute metadata; a
// permissive type keeps the ports readable without re-deriving upstream's
// untyped shapes.
// eslint-disable-next-line @typescript-eslint/no-explicit-any
type LegacyAttributes = Record<string, any>;

const sharedSupports = {
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
};

const v4 = {
    attributes: {
        datetime: { type: 'string', role: 'content' },
        textAlign: { type: 'string' },
        format: { type: 'string' },
        isLink: { type: 'boolean', default: false, role: 'content' },
    },
    supports: { anchor: true, ...sharedSupports },
    save: (): null => null,
    migrate: ( attributes: LegacyAttributes ) => migrateTextAlign( attributes ),
    isEligible( attributes: LegacyAttributes ): boolean {
        return (
            !! attributes.textAlign ||
            !! attributes.className?.match( /\bhas-text-align-(left|center|right)\b/ )
        );
    },
};

const v3 = {
    attributes: {
        datetime: { type: 'string', role: 'content' },
        textAlign: { type: 'string' },
        format: { type: 'string' },
        isLink: { type: 'boolean', default: false, role: 'content' },
    },
    supports: sharedSupports,
    save: (): null => null,
    migrate( attributes: LegacyAttributes ) {
        // Change the block bindings source argument name from "key" to "field".
        const { metadata, ...otherAttributes } = attributes;
        const { bindings, ...otherMetadata } = metadata;
        const { datetime, ...otherBindings } = bindings;
        const { source, args } = datetime;
        const { key, ...otherArgs } = args;

        return migrateTextAlign( {
            ...otherAttributes,
            metadata: {
                ...otherMetadata,
                bindings: {
                    ...otherBindings,
                    datetime: {
                        source,
                        args: { field: key, ...otherArgs },
                    },
                },
            },
        } );
    },
    isEligible( attributes: LegacyAttributes ): boolean {
        return (
            attributes?.metadata?.bindings?.datetime?.source === 'core/post-data' &&
            !! attributes?.metadata?.bindings?.datetime?.args?.key
        );
    },
};

const v2 = {
    attributes: {
        textAlign: { type: 'string' },
        format: { type: 'string' },
        isLink: { type: 'boolean', default: false, role: 'content' },
        displayType: { type: 'string', default: 'date' },
    },
    supports: sharedSupports,
    save: (): null => null,
    migrate( attributes: LegacyAttributes ) {
        const { displayType, metadata, ...otherAttributes } = attributes;
        let { className } = attributes;

        if ( displayType === 'date' || displayType === 'modified' ) {
            if ( displayType === 'modified' ) {
                className = clsx( className, 'wp-block-post-date__modified-date' );
            }

            return migrateTextAlign( {
                ...otherAttributes,
                className,
                metadata: {
                    ...metadata,
                    bindings: {
                        datetime: {
                            source: 'core/post-data',
                            args: { field: displayType },
                        },
                    },
                },
            } );
        }

        return undefined;
    },
    isEligible( attributes: LegacyAttributes ): boolean {
        // If there's neither an explicit `datetime` attribute nor a block
        // binding for that attribute, we're dealing with an old version.
        return ! attributes.datetime && ! attributes?.metadata?.bindings?.datetime;
    },
};

const v1 = {
    attributes: {
        textAlign: { type: 'string' },
        format: { type: 'string' },
        isLink: { type: 'boolean', default: false },
    },
    supports: {
        html: false,
        color: { gradients: true, link: true },
        typography: {
            fontSize: true,
            lineHeight: true,
            __experimentalFontFamily: true,
            __experimentalFontWeight: true,
            __experimentalFontStyle: true,
            __experimentalTextTransform: true,
            __experimentalLetterSpacing: true,
        },
    },
    save: (): null => null,
    migrate( attributes: LegacyAttributes ) {
        return migrateTextAlign( migrateFontFamily( attributes ) );
    },
    isEligible( { style }: LegacyAttributes ): boolean {
        return !! style?.typography?.fontFamily;
    },
};

const deprecated = [ v4, v3, v2, v1 ];

export default deprecated;
