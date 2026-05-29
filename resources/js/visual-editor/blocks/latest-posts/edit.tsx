/**
 * Latest Posts — edit component.
 *
 * `core/latest-posts` is server-rendered. Upstream's `edit.js` builds a
 * live client-side preview by querying `@wordpress/core-data` for posts,
 * but this package resolves posts on the server (see
 * `src/Blocks/Core/LatestPostsBlock.php`) and the `@wordpress/core-data`
 * shim does not expose a post entity. The fork therefore previews through
 * the package's `<ServerSideRender>` seam — the same approach the
 * taxonomy/archive dynamic blocks use — and ports the inspector controls
 * to `PanelBody` (dropping the block-library-internal ToolsPanel dropdown
 * hook). Phase I4 widgets cluster (#412).
 */

import type { ReactElement } from 'react';
import { InspectorControls, useBlockProps } from '@wordpress/block-editor';
import {
    PanelBody,
    RadioControl,
    RangeControl,
    SelectControl,
    ToggleControl,
} from '@wordpress/components';
import { __ } from '@wordpress/i18n';

import { ServerSideRender } from '../../editor/server-side-render';
import { TEXT_DOMAIN } from '../../vendor/i18n';
import {
    MAX_EXCERPT_LENGTH,
    MAX_POSTS_COLUMNS,
    MIN_EXCERPT_LENGTH,
} from './constants';

interface LatestPostsAttributes {
    readonly postsToShow?: number;
    readonly order?: string;
    readonly orderBy?: string;
    readonly displayPostContent?: boolean;
    readonly displayPostContentRadio?: string;
    readonly excerptLength?: number;
    readonly displayAuthor?: boolean;
    readonly displayPostDate?: boolean;
    readonly displayFeaturedImage?: boolean;
    readonly addLinkToFeaturedImage?: boolean;
    readonly postLayout?: string;
    readonly columns?: number;
    readonly [key: string]: unknown;
}

interface LatestPostsEditProps {
    readonly attributes: LatestPostsAttributes;
    readonly setAttributes: ( attrs: Partial<LatestPostsAttributes> ) => void;
}

export default function LatestPostsEdit( {
    attributes,
    setAttributes,
}: LatestPostsEditProps ): ReactElement {
    const {
        postsToShow = 5,
        order = 'desc',
        orderBy = 'date',
        displayPostContent = false,
        displayPostContentRadio = 'excerpt',
        excerptLength = 55,
        displayAuthor = false,
        displayPostDate = false,
        displayFeaturedImage = false,
        addLinkToFeaturedImage = false,
        postLayout = 'list',
        columns = 3,
    } = attributes;

    // eslint-disable-next-line @typescript-eslint/no-explicit-any
    const blockProps = ( useBlockProps as any )();

    return (
        <>
            <InspectorControls>
                <PanelBody title={ __( 'Post content', TEXT_DOMAIN ) }>
                    <ToggleControl
                        // @ts-expect-error - upstream prop
                        __nextHasNoMarginBottom
                        label={ __( 'Post content', TEXT_DOMAIN ) }
                        checked={ displayPostContent }
                        onChange={ ( value ) =>
                            setAttributes( { displayPostContent: value } )
                        }
                    />
                    { displayPostContent && (
                        <RadioControl
                            label={ __( 'Show', TEXT_DOMAIN ) }
                            selected={ displayPostContentRadio }
                            options={ [
                                {
                                    label: __( 'Excerpt', TEXT_DOMAIN ),
                                    value: 'excerpt',
                                },
                                {
                                    label: __( 'Full post', TEXT_DOMAIN ),
                                    value: 'full_post',
                                },
                            ] }
                            onChange={ ( value ) =>
                                setAttributes( {
                                    displayPostContentRadio: value,
                                } )
                            }
                        />
                    ) }
                    { displayPostContent &&
                        displayPostContentRadio === 'excerpt' && (
                            <RangeControl
                                // @ts-expect-error - upstream prop
                                __nextHasNoMarginBottom
                                __next40pxDefaultSize
                                label={ __(
                                    'Max number of words in excerpt',
                                    TEXT_DOMAIN
                                ) }
                                value={ excerptLength }
                                onChange={ ( value?: number ) =>
                                    setAttributes( {
                                        excerptLength: value ?? 55,
                                    } )
                                }
                                min={ MIN_EXCERPT_LENGTH }
                                max={ MAX_EXCERPT_LENGTH }
                            />
                        ) }
                </PanelBody>

                <PanelBody title={ __( 'Post meta', TEXT_DOMAIN ) }>
                    <ToggleControl
                        // @ts-expect-error - upstream prop
                        __nextHasNoMarginBottom
                        label={ __( 'Display author name', TEXT_DOMAIN ) }
                        checked={ displayAuthor }
                        onChange={ ( value ) =>
                            setAttributes( { displayAuthor: value } )
                        }
                    />
                    <ToggleControl
                        // @ts-expect-error - upstream prop
                        __nextHasNoMarginBottom
                        label={ __( 'Display post date', TEXT_DOMAIN ) }
                        checked={ displayPostDate }
                        onChange={ ( value ) =>
                            setAttributes( { displayPostDate: value } )
                        }
                    />
                </PanelBody>

                <PanelBody title={ __( 'Featured image', TEXT_DOMAIN ) }>
                    <ToggleControl
                        // @ts-expect-error - upstream prop
                        __nextHasNoMarginBottom
                        label={ __( 'Display featured image', TEXT_DOMAIN ) }
                        checked={ displayFeaturedImage }
                        onChange={ ( value ) =>
                            setAttributes( { displayFeaturedImage: value } )
                        }
                    />
                    { displayFeaturedImage && (
                        <ToggleControl
                            // @ts-expect-error - upstream prop
                            __nextHasNoMarginBottom
                            label={ __(
                                'Add link to featured image',
                                TEXT_DOMAIN
                            ) }
                            checked={ addLinkToFeaturedImage }
                            onChange={ ( value ) =>
                                setAttributes( {
                                    addLinkToFeaturedImage: value,
                                } )
                            }
                        />
                    ) }
                </PanelBody>

                <PanelBody title={ __( 'Sorting and filtering', TEXT_DOMAIN ) }>
                    <RangeControl
                        // @ts-expect-error - upstream prop
                        __nextHasNoMarginBottom
                        __next40pxDefaultSize
                        label={ __( 'Number of items', TEXT_DOMAIN ) }
                        value={ postsToShow }
                        onChange={ ( value?: number ) =>
                            setAttributes( { postsToShow: value ?? 5 } )
                        }
                        min={ 1 }
                        max={ 100 }
                        required
                    />
                    <SelectControl
                        // @ts-expect-error - upstream prop
                        __next40pxDefaultSize
                        __nextHasNoMarginBottom
                        label={ __( 'Order by', TEXT_DOMAIN ) }
                        value={ `${ orderBy }/${ order }` }
                        options={ [
                            {
                                label: __( 'Newest to oldest', TEXT_DOMAIN ),
                                value: 'date/desc',
                            },
                            {
                                label: __( 'Oldest to newest', TEXT_DOMAIN ),
                                value: 'date/asc',
                            },
                            {
                                label: __( 'A → Z', TEXT_DOMAIN ),
                                value: 'title/asc',
                            },
                            {
                                label: __( 'Z → A', TEXT_DOMAIN ),
                                value: 'title/desc',
                            },
                        ] }
                        onChange={ ( value: string ) => {
                            const [ newOrderBy, newOrder ] = value.split( '/' );
                            setAttributes( {
                                order: newOrder,
                                orderBy: newOrderBy,
                            } );
                        } }
                    />
                    <SelectControl
                        // @ts-expect-error - upstream prop
                        __next40pxDefaultSize
                        __nextHasNoMarginBottom
                        label={ __( 'Layout', TEXT_DOMAIN ) }
                        value={ postLayout }
                        options={ [
                            { label: __( 'List', TEXT_DOMAIN ), value: 'list' },
                            { label: __( 'Grid', TEXT_DOMAIN ), value: 'grid' },
                        ] }
                        onChange={ ( value: string ) =>
                            setAttributes( { postLayout: value } )
                        }
                    />
                    { postLayout === 'grid' && (
                        <RangeControl
                            // @ts-expect-error - upstream prop
                            __nextHasNoMarginBottom
                            __next40pxDefaultSize
                            label={ __( 'Columns', TEXT_DOMAIN ) }
                            value={ columns }
                            onChange={ ( value?: number ) =>
                                setAttributes( { columns: value ?? 3 } )
                            }
                            min={ 2 }
                            max={ MAX_POSTS_COLUMNS }
                            required
                        />
                    ) }
                </PanelBody>
            </InspectorControls>

            <div { ...blockProps }>
                <ServerSideRender
                    block="artisanpack/latest-posts"
                    attributes={ attributes }
                />
            </div>
        </>
    );
}
