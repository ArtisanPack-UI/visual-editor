/**
 * Single Post Type Search Results — editor-side component (#502).
 *
 * Lets the author pick which post type this section renders for and
 * accepts any inner blocks they want to show for that post type.
 * Intentionally ships no default template so authors decide what
 * "results" looks like (a query loop, static cards, an empty state, …).
 */

import type { ReactElement } from 'react';
import {
    InspectorControls,
    useBlockProps,
    useInnerBlocksProps,
} from '@wordpress/block-editor';
import { PanelBody, SelectControl } from '@wordpress/components';
import { __ } from '@wordpress/i18n';

import { TEXT_DOMAIN } from '../../vendor/i18n';
import { getContentTypes } from '../../editor/content-type-registry';

interface SinglePostTypesSearchResultsAttributes {
    readonly postType: string;
}

interface SinglePostTypesSearchResultsEditProps {
    readonly attributes: SinglePostTypesSearchResultsAttributes;
    readonly setAttributes: (
        next: Partial<SinglePostTypesSearchResultsAttributes>
    ) => void;
}

export default function SinglePostTypesSearchResultsEdit({
    attributes,
    setAttributes,
}: SinglePostTypesSearchResultsEditProps): ReactElement {
    const { postType } = attributes;

    const blockProps = useBlockProps({
        className: 'ap-single-post-types-search-results',
    });
    // No default `template`: the section accepts any inner blocks the
    // author wants to show for the matched post type. Forcing an
    // `artisanpack/query` here would prepopulate a query-pagination
    // sub-tree that's not always the right fit for a search result
    // section, so we leave the choice to the author.
    const innerBlocksProps = useInnerBlocksProps(blockProps, {});

    const options = [
        { value: 'all', label: __('All post types', TEXT_DOMAIN) },
        ...getContentTypes().map((entry) => ({
            value: entry.slug,
            label: entry.label,
        })),
    ];

    return (
        <>
            <InspectorControls>
                <PanelBody
                    title={__('Single post type results settings', TEXT_DOMAIN)}
                    initialOpen
                >
                    <SelectControl
                        label={__('Post type', TEXT_DOMAIN)}
                        value={postType}
                        options={options}
                        onChange={(value) => setAttributes({ postType: value })}
                        help={__(
                            'Show this section when the host page\'s `?post_type=` matches this value. Choose "All post types" to show it when no parameter is set.',
                            TEXT_DOMAIN
                        )}
                        __nextHasNoMarginBottom
                    />
                </PanelBody>
            </InspectorControls>
            <div {...innerBlocksProps} />
        </>
    );
}
