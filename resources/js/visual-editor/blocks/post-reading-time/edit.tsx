/**
 * Post Reading Time — edit component (#943).
 *
 * Server-rendered display block: `PostResolver` stamps
 * `_resolvedReadingTime` (whole minutes) and the Blade / React / Vue
 * renderers print "{prefix}{minutes} {suffix}". The canvas previews
 * the stamped value, else the query loop's preview post
 * (`readingTime`, #483), else a sample line so the block can be styled
 * in a template. The Settings panel edits the prefix and suffix.
 */

import type { ReactElement } from 'react';
import { InspectorControls } from '@wordpress/block-editor';
import { PanelBody, TextControl } from '@wordpress/components';
import { __ } from '@wordpress/i18n';

import { TEXT_DOMAIN } from '../../vendor/i18n';
import {
    createEntityPlaceholderEdit,
    PREVIEW_CONTEXT_KEY,
} from '../_shared/entity-placeholder-edit';
import type { QueryPreviewPost } from '../../editor/use-query-preview';

/** The suffix the renderers print when the block sets none. */
const DEFAULT_SUFFIX = 'min read';

interface PostReadingTimeAttributes {
    readonly prefix?: string;
    readonly suffix?: string;
    readonly _resolvedReadingTime?: number;
}

/**
 * The renderers' "{prefix}{minutes} {suffix}" line for `minutes`, or null
 * when there are none.
 */
function readingTimeLine(
    minutes: unknown,
    attributes: PostReadingTimeAttributes | undefined,
): string {
    if ( typeof minutes !== 'number' || ! Number.isFinite( minutes ) || minutes <= 0 ) {
        return '';
    }

    const prefix = typeof attributes?.prefix === 'string' ? attributes.prefix : '';
    const suffix = ( typeof attributes?.suffix === 'string' ? attributes.suffix : DEFAULT_SUFFIX ).trim();

    return `${ prefix }${ Math.floor( minutes ) }${ suffix !== '' ? ` ${ suffix }` : '' }`;
}

function readPreviewMinutes( context: unknown ): unknown {
    if ( context === null || typeof context !== 'object' ) {
        return undefined;
    }

    const preview = ( context as Record<string, unknown> )[ PREVIEW_CONTEXT_KEY ];

    return preview !== null && typeof preview === 'object'
        ? ( preview as QueryPreviewPost ).readingTime
        : undefined;
}

const PlaceholderEdit = createEntityPlaceholderEdit( {
    label: () => __( 'Reading Time', TEXT_DOMAIN ),
    resolvedKey: '_resolvedReadingTimeLabel',
    kind: 'text',
} );

interface PostReadingTimeEditProps {
    readonly attributes?: PostReadingTimeAttributes;
    readonly setAttributes?: ( attrs: Partial<PostReadingTimeAttributes> ) => void;
    readonly context?: unknown;
}

export default function PostReadingTimeEdit( props: PostReadingTimeEditProps ): ReactElement {
    const { attributes, setAttributes } = props;

    // Stamped value, then the query loop's preview post, then a sample
    // line (6 minutes) so the block can be styled in a template.
    const label =
        readingTimeLine( attributes?._resolvedReadingTime, attributes ) ||
        readingTimeLine( readPreviewMinutes( props.context ), attributes ) ||
        readingTimeLine( 6, attributes );
    const preview = PlaceholderEdit( {
        ...props,
        attributes: {
            ...attributes,
            _resolvedReadingTimeLabel: label,
        },
    } );

    return (
        <>
            { setAttributes !== undefined && (
                <InspectorControls>
                    <PanelBody title={ __( 'Settings', TEXT_DOMAIN ) }>
                        <TextControl
                            // @ts-expect-error - upstream prop
                            __next40pxDefaultSize
                            __nextHasNoMarginBottom
                            label={ __( 'Prefix', TEXT_DOMAIN ) }
                            value={ attributes?.prefix ?? '' }
                            onChange={ ( value: string ) => setAttributes( { prefix: value } ) }
                        />
                        <TextControl
                            // @ts-expect-error - upstream prop
                            __next40pxDefaultSize
                            __nextHasNoMarginBottom
                            label={ __( 'Suffix', TEXT_DOMAIN ) }
                            help={ __( 'Printed after the minutes. Leave as is for "min read".', TEXT_DOMAIN ) }
                            value={ attributes?.suffix ?? DEFAULT_SUFFIX }
                            onChange={ ( value: string ) =>
                                setAttributes( { suffix: value === DEFAULT_SUFFIX ? undefined : value } )
                            }
                        />
                    </PanelBody>
                </InspectorControls>
            ) }
            { preview }
        </>
    );
}
