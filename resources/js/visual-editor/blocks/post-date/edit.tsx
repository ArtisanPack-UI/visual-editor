/**
 * Post Date — edit component.
 *
 * Server-rendered display block: the real markup is produced by the
 * Blade / React / Vue renderers from stamped `_resolved*` attributes.
 * The fork previews through `createEntityPlaceholderEdit`:
 *
 *  1. Stamped `_resolvedDateFormatted` attribute.
 *  2. `artisanpack/postPreview` query-loop context (#483) — prefers
 *     the server-formatted `dateFormatted`, falls back to the ISO
 *     date portion of `publishedAt`.
 *  3. Live page entity date from the core-data shim, using
 *     `_preview.dateFormatted` (mirrors the front-end format) and
 *     falling back to the ISO `date` portion (#481).
 *  4. Clearly-labelled placeholder.
 *
 * A `format` (a PHP date format string) replaces the server's default
 * `F j, Y` in steps 2 and 3, and the Settings panel picks it (#941).
 *
 * Phase I5 entity cluster (#413), live-preview path #481.
 */

import type { ReactElement } from 'react';
import { InspectorControls } from '@wordpress/block-editor';
import { PanelBody, SelectControl, TextControl } from '@wordpress/components';
import { useState } from '@wordpress/element';
import { __, sprintf } from '@wordpress/i18n';

import { TEXT_DOMAIN } from '../../vendor/i18n';
import { createEntityPlaceholderEdit } from '../_shared/entity-placeholder-edit';
import { phpDateFormat } from '../_shared/php-date-format';

/**
 * Formats offered in the Date format select. The empty value is the
 * server default (`F j, Y`); any other format is "Custom".
 */
const FORMAT_PRESETS: ReadonlyArray<string> = [ 'M j', 'M j, Y', 'j F Y', 'l, F j, Y', 'Y-m-d', 'm/d/Y', 'd/m/Y' ];

const DEFAULT_FORMAT = 'F j, Y';

const CUSTOM_OPTION = '__custom';

interface PostDateAttributes {
    readonly format?: string;
}

function readFormat( attributes: PostDateAttributes | undefined ): string {
    const format = attributes?.format;

    return typeof format === 'string' && format.trim() !== '' ? format : '';
}

function documentLocale(): string {
    return typeof document !== 'undefined' && document.documentElement?.lang
        ? document.documentElement.lang
        : 'en-US';
}

const PlaceholderEdit = createEntityPlaceholderEdit( {
    label: () => __( 'Post Date', TEXT_DOMAIN ),
    resolvedKey: '_resolvedDateFormatted',
    kind: 'text',
    fromQueryPreview: ( post, attributes ) => {
        const format = readFormat( attributes );

        if ( format !== '' && typeof post.publishedAt === 'string' && post.publishedAt !== '' ) {
            return { text: phpDateFormat( post.publishedAt, format, documentLocale() ) };
        }

        if ( typeof post.dateFormatted === 'string' && post.dateFormatted !== '' ) {
            return { text: post.dateFormatted };
        }

        // Fallback when the server-formatted date isn't on the payload:
        // strip the time portion off the ISO `publishedAt` so the editor
        // still shows something post-shaped instead of the placeholder.
        if ( typeof post.publishedAt === 'string' && post.publishedAt !== '' ) {
            const datePart = post.publishedAt.slice( 0, 10 );
            return datePart !== '' ? { text: datePart } : null;
        }

        return null;
    },
    fromPostEntity: ( entity, attributes ) => {
        const format = readFormat( attributes );
        const iso = entity.date;

        if ( format !== '' && typeof iso === 'string' && iso !== '' ) {
            return { text: phpDateFormat( iso, format, documentLocale() ) };
        }

        const preview = entity._preview;
        const formatted = preview?.dateFormatted;

        if ( typeof formatted === 'string' && formatted !== '' ) {
            return { text: formatted };
        }

        // Mirror the query-preview ISO fallback so a missing
        // `_preview.dateFormatted` still surfaces the date instead of
        // collapsing back to the placeholder.
        if ( typeof iso === 'string' && iso !== '' ) {
            const datePart = iso.slice( 0, 10 );
            return datePart !== '' ? { text: datePart } : null;
        }

        return null;
    },
} );

interface PostDateEditProps {
    readonly attributes?: PostDateAttributes;
    readonly setAttributes?: ( attrs: Partial<PostDateAttributes> ) => void;
    readonly context?: unknown;
}

export default function PostDateEdit( props: PostDateEditProps ): ReactElement {
    const { attributes, setAttributes } = props;
    const format = readFormat( attributes );
    const [ isCustom, setIsCustom ] = useState<boolean>(
        format !== '' && ! FORMAT_PRESETS.includes( format )
    );
    const sample = new Date().toISOString();
    const locale = documentLocale();

    const options = [
        {
            /* translators: %s: today's date in the default format. */
            label: sprintf( __( 'Default (%s)', TEXT_DOMAIN ), phpDateFormat( sample, DEFAULT_FORMAT, locale ) ),
            value: '',
        },
        ...FORMAT_PRESETS.map( ( preset ) => ( {
            label: phpDateFormat( sample, preset, locale ),
            value: preset,
        } ) ),
        { label: __( 'Custom', TEXT_DOMAIN ), value: CUSTOM_OPTION },
    ];

    return (
        <>
            { setAttributes !== undefined && (
                <InspectorControls>
                    <PanelBody title={ __( 'Settings', TEXT_DOMAIN ) }>
                        <SelectControl
                            // @ts-expect-error - upstream prop
                            __next40pxDefaultSize
                            __nextHasNoMarginBottom
                            label={ __( 'Date format', TEXT_DOMAIN ) }
                            value={ isCustom ? CUSTOM_OPTION : format }
                            options={ options }
                            onChange={ ( value: string ) => {
                                if ( value === CUSTOM_OPTION ) {
                                    setIsCustom( true );
                                    setAttributes( { format: format !== '' ? format : DEFAULT_FORMAT } );
                                    return;
                                }

                                setIsCustom( false );
                                setAttributes( { format: value === '' ? undefined : value } );
                            } }
                        />
                        { isCustom && (
                            <TextControl
                                // @ts-expect-error - upstream prop
                                __next40pxDefaultSize
                                __nextHasNoMarginBottom
                                label={ __( 'Custom format', TEXT_DOMAIN ) }
                                help={ __( 'A PHP date format, such as "M j, Y".', TEXT_DOMAIN ) }
                                value={ format }
                                onChange={ ( value: string ) =>
                                    setAttributes( { format: value === '' ? undefined : value } )
                                }
                            />
                        ) }
                    </PanelBody>
                </InspectorControls>
            ) }
            { PlaceholderEdit( props ) }
        </>
    );
}
