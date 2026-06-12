/**
 * Search Filters — editor-side component (#502).
 *
 * Wrapper that drops a `<form>` chrome around the inner filter blocks
 * (search field, taxonomy dropdowns, submit + reset buttons). The
 * inspector picks the post type the form scopes its search to; the
 * post-type list comes from the editor's content-type registry so the
 * dropdown stays in sync with whichever post types the host has
 * published.
 */

import type { ReactElement } from 'react';
import {
    InnerBlocks,
    InspectorControls,
    useBlockProps,
    useInnerBlocksProps,
} from '@wordpress/block-editor';
import { PanelBody, SelectControl } from '@wordpress/components';
import { __ } from '@wordpress/i18n';

import { TEXT_DOMAIN } from '../../vendor/i18n';
import { getContentTypes } from '../../editor/content-type-registry';

interface SearchFiltersAttributes {
    readonly postType: string;
}

interface SearchFiltersEditProps {
    readonly attributes: SearchFiltersAttributes;
    readonly setAttributes: (next: Partial<SearchFiltersAttributes>) => void;
}

const ALLOWED_BLOCKS: string[] = [
    'artisanpack/search-field',
    'artisanpack/search-filters-taxonomy',
    'artisanpack/search-filters-buttons',
];

const TEMPLATE: [string, Record<string, unknown>][] = [
    ['artisanpack/search-field', {}],
    ['artisanpack/search-filters-buttons', {}],
];

export default function SearchFiltersEdit({
    attributes,
    setAttributes,
}: SearchFiltersEditProps): ReactElement {
    const { postType } = attributes;

    const blockProps = useBlockProps({ className: 'ap-search-filters' });
    // Wrap inner blocks in a `<div>` for the editor — not a `<form>` —
    // so Gutenberg's inline + sibling appenders render properly. The
    // runtime renderers wrap the same children in a `<form method="get">`
    // when emitting front-end markup.
    const innerBlocksProps = useInnerBlocksProps(blockProps, {
        allowedBlocks: ALLOWED_BLOCKS,
        template: TEMPLATE,
        renderAppender: InnerBlocks.ButtonBlockAppender,
    });

    const options = getContentTypes().map((entry) => ({
        value: entry.slug,
        label: entry.label,
    }));

    if (options.length === 0) {
        options.push({ value: 'post', label: __('Posts', TEXT_DOMAIN) });
    }

    return (
        <>
            <InspectorControls>
                <PanelBody title={__('Search filters settings', TEXT_DOMAIN)} initialOpen>
                    <SelectControl
                        label={__('Post type', TEXT_DOMAIN)}
                        value={postType}
                        options={options}
                        onChange={(value) => setAttributes({ postType: value })}
                        help={__(
                            'Scope the form to a single post type. Posted as a hidden `post_type` field so the host search route can filter results.',
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
