/**
 * Post Navigation Link — edit component.
 *
 * Server-rendered display block: the real markup is produced by the
 * Blade / React / Vue renderers from stamped `_resolvedAdjacent*`
 * attributes. The fork previews through a thin wrapper around
 * `createEntityPlaceholderEdit`:
 *
 *  1. Stamped `_resolvedPrevTitle` / `_resolvedNextTitle` attribute,
 *     decorated with the configured arrow glyph (front-end / saved-tree
 *     path).
 *  2. `artisanpack/postPreview.adjacent` query-loop context (#520) —
 *     the resolved adjacent title from the per-post envelope.
 *  3. The block's own `label` attribute, when set — so authors get
 *     instant feedback while editing it.
 *  4. A neutral "Previous post" / "Next post" placeholder decorated
 *     with the configured arrow glyph so the canvas shows the styled
 *     shape even with no adjacent post resolved.
 *
 * `InspectorControls` (#532) surfaces the `type`, `arrow`, and
 * `showTitle` attributes through a Settings sidebar panel so authors
 * can flip the direction (and the other display options) without
 * dropping into HTML edit mode. Matches upstream `core/post-navigation-link`'s
 * Settings layout, with the additional `arrow` glyph control the fork
 * exposes on top of upstream.
 *
 * Phase I-Block-Fork — post navigation / metadata family (#520).
 */

import type { ReactElement } from 'react';
import { InspectorControls, useBlockProps } from '@wordpress/block-editor';
import {
    PanelBody,
    SelectControl,
    ToggleControl,
    // eslint-disable-next-line @typescript-eslint/no-explicit-any
    __experimentalToggleGroupControl as ToggleGroupControl,
    // eslint-disable-next-line @typescript-eslint/no-explicit-any
    __experimentalToggleGroupControlOption as ToggleGroupControlOption,
} from '@wordpress/components';
import { __ } from '@wordpress/i18n';

import {
    createEntityPlaceholderEdit,
    PREVIEW_CONTEXT_KEY,
    type EntityPreviewValue,
} from '../_shared/entity-placeholder-edit';
import type { QueryPreviewPost } from '../../editor/use-query-preview';
import { TEXT_DOMAIN } from '../../vendor/i18n';

interface NavigationAttributes {
    readonly type?: string;
    readonly label?: string;
    readonly arrow?: string;
    readonly showTitle?: boolean;
    readonly _resolvedPrevTitle?: string;
    readonly _resolvedNextTitle?: string;
    readonly _resolvedAdjacentTitle?: string;
}

interface PostNavigationLinkEditProps {
    readonly attributes?: NavigationAttributes;
    readonly setAttributes?: ( attrs: Partial<NavigationAttributes> ) => void;
    readonly context?: unknown;
}

function arrowFor( type: string, arrow: string ): string {
    if ( arrow === 'arrow' ) {
        return type === 'previous' ? '←' : '→';
    }

    if ( arrow === 'chevron' ) {
        return type === 'previous' ? '«' : '»';
    }

    return '';
}

function decoratePlaceholderText(
    attributes: NavigationAttributes,
    baseText: string,
): string {
    const type = attributes.type === 'previous' ? 'previous' : 'next';
    const arrow = typeof attributes.arrow === 'string' ? attributes.arrow : 'none';
    const glyph = arrowFor( type, arrow );

    if ( glyph === '' ) {
        return baseText;
    }

    return type === 'previous' ? `${ glyph } ${ baseText }` : `${ baseText } ${ glyph }`;
}

function readQueryPreviewAdjacent(
    context: unknown,
    direction: 'previous' | 'next',
): string {
    if ( context === null || typeof context !== 'object' ) {
        return '';
    }

    const preview = ( context as Record<string, unknown> )[ PREVIEW_CONTEXT_KEY ];

    if ( preview === null || typeof preview !== 'object' ) {
        return '';
    }

    const adjacent = ( preview as QueryPreviewPost ).adjacent;

    if ( adjacent === null || adjacent === undefined ) {
        return '';
    }

    const entry = adjacent[ direction ];

    if ( entry === null || entry === undefined ) {
        return '';
    }

    return typeof entry.title === 'string' ? entry.title : '';
}

const PlaceholderEdit = createEntityPlaceholderEdit( {
    label: 'Post Navigation Link',
    resolvedKey: '_resolvedAdjacentTitle',
    kind: 'text',
} );

/**
 * Empty block wrapper rendered when no adjacent post is resolved and no
 * custom `label` is set. Mirrors the server-side renderers, which emit
 * no markup for a post with no neighbor in the chosen direction —
 * showing a neutral "Previous post" / "Next post" placeholder in the
 * canvas would misrepresent the front-end output. Lives in its own
 * component so `useBlockProps` stays under React's rules-of-hooks
 * (called unconditionally per render).
 */
function EmptyPostNavigationLink(): ReactElement {
    // eslint-disable-next-line @typescript-eslint/no-explicit-any
    const blockProps = ( useBlockProps as any )();

    return <div { ...blockProps } />;
}

export default function PostNavigationLinkEdit(
    props: PostNavigationLinkEditProps,
): ReactElement {
    const attributes = props.attributes ?? {};
    const setAttributes = props.setAttributes;
    const type = attributes.type === 'previous' ? 'previous' : 'next';
    const label = typeof attributes.label === 'string' ? attributes.label : '';
    const arrow = typeof attributes.arrow === 'string' ? attributes.arrow : 'none';
    const showTitle = Boolean( attributes.showTitle );

    const stampedKey: keyof NavigationAttributes =
        type === 'previous' ? '_resolvedPrevTitle' : '_resolvedNextTitle';
    const stamped =
        typeof attributes[ stampedKey ] === 'string' ? ( attributes[ stampedKey ] as string ) : '';

    // Priority order (highest first):
    //   1. Stamped `_resolvedPrevTitle` / `_resolvedNextTitle`
    //   2. `artisanpack/postPreview.adjacent[direction].title`
    //   3. The block's own `label` attribute
    //
    // When none of these resolve the editor preview renders nothing —
    // matching the server-side renderers, which emit empty markup for
    // posts with no neighbor in the chosen direction. The block wrapper
    // (`useBlockProps`) still mounts so the Settings panel binds and
    // the empty block stays selectable via the list view.
    let text = stamped;

    if ( text === '' ) {
        text = readQueryPreviewAdjacent( props.context, type );
    }

    if ( text === '' && label !== '' ) {
        text = label;
    }

    const decorated = text === '' ? '' : decoratePlaceholderText( attributes, text );

    const synthesizedAttributes: NavigationAttributes & EntityPreviewValue = {
        ...attributes,
        _resolvedAdjacentTitle: decorated,
    };

    return (
        <>
            { setAttributes !== undefined && (
                <InspectorControls>
                    <PanelBody title={ __( 'Settings', TEXT_DOMAIN ) }>
                        <ToggleGroupControl
                            label={ __( 'Direction', TEXT_DOMAIN ) }
                            value={ type }
                            isBlock
                            __next40pxDefaultSize
                            __nextHasNoMarginBottom
                            onChange={ ( value: string | number ) =>
                                setAttributes( {
                                    type: value === 'previous' ? 'previous' : 'next',
                                } )
                            }
                        >
                            <ToggleGroupControlOption
                                value="previous"
                                label={ __( 'Previous', TEXT_DOMAIN ) }
                            />
                            <ToggleGroupControlOption
                                value="next"
                                label={ __( 'Next', TEXT_DOMAIN ) }
                            />
                        </ToggleGroupControl>
                        <ToggleControl
                            // @ts-expect-error - upstream prop
                            __nextHasNoMarginBottom
                            label={ __( 'Display the title as a link', TEXT_DOMAIN ) }
                            checked={ showTitle }
                            onChange={ ( value: boolean ) =>
                                setAttributes( { showTitle: value } )
                            }
                        />
                        <SelectControl
                            // @ts-expect-error - upstream prop
                            __next40pxDefaultSize
                            __nextHasNoMarginBottom
                            label={ __( 'Arrow', TEXT_DOMAIN ) }
                            value={ arrow }
                            options={ [
                                { label: __( 'None', TEXT_DOMAIN ), value: 'none' },
                                { label: __( 'Arrow', TEXT_DOMAIN ), value: 'arrow' },
                                { label: __( 'Chevron', TEXT_DOMAIN ), value: 'chevron' },
                            ] }
                            onChange={ ( value: string ) =>
                                setAttributes( { arrow: value } )
                            }
                        />
                    </PanelBody>
                </InspectorControls>
            ) }
            { decorated === '' ? (
                <EmptyPostNavigationLink />
            ) : (
                <PlaceholderEdit { ...props } attributes={ synthesizedAttributes } />
            ) }
        </>
    );
}
