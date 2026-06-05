/**
 * Edit-component overrides for the three G4b dynamic blocks.
 *
 * The upstream `core/categories`, `core/tag-cloud`, and `core/archives`
 * Edit components depend on data the shim does not provide:
 *
 *   - `core/categories` reads `getTaxonomy('category').labels.name`.
 *   - `core/tag-cloud` calls `getTaxonomies({ per_page: -1 })`.
 *   - `core/archives` renders through Gutenberg's
 *     `@wordpress/server-side-render`, which POSTs to
 *     `wp/v2/block-renderer/core/archives`. Visual-editor's preview
 *     endpoint lives at `/visual-editor/api/blocks/preview` instead.
 *
 * Rather than backfill `getTaxonomies` / `getTaxonomy` selectors and a
 * `wp/v2/block-renderer` route just for these three blocks, this filter
 * swaps each block's `edit` with a thin wrapper that uses our
 * `ServerSideRender` shim. The block's saved attribute shape, supports,
 * and `save()` stay untouched — anything written by the previous
 * upstream Edit (or by hosts that bind their own controls) is still
 * round-tripped against the same attribute keys.
 *
 * Idempotent across HMR via a global Symbol guard so the post-editor
 * and site-editor entries can both call `registerTaxonomyAndArchiveBlockOverrides()`.
 */

import { InspectorControls } from '@wordpress/block-editor';
import {
    PanelBody,
    SelectControl,
    ToggleControl,
    RangeControl,
} from '@wordpress/components';
import { useBlockProps } from '@wordpress/block-editor';
import { addFilter } from '@wordpress/hooks';
import { __ } from '@wordpress/i18n';

import { TEXT_DOMAIN } from '../vendor/i18n';

import { ServerSideRender } from './server-side-render';

const FILTER_HOOK = 'blocks.registerBlockType';
const FILTER_NAMESPACE = 'artisanpack-ui/visual-editor/taxonomy-archive-overrides';
const TARGET_BLOCKS = new Set( [
    'core/categories',
    'core/tag-cloud',
    'core/archives',
] );

const REGISTERED_KEY = Symbol.for(
    'artisanpack-ui.visual-editor.taxonomy-archive-overrides.registered'
);

// Typed as `{ label: string; value: string }[]` so TS does not narrow
// the option values to literal unions — that narrowing collides with
// the broader `string` type the block attributes carry.
const SMALLEST_SIZE_OPTIONS: { label: string; value: string }[] = [
    { label: '8pt', value: '8pt' },
    { label: '10pt', value: '10pt' },
    { label: '12pt', value: '12pt' },
];

const LARGEST_SIZE_OPTIONS: { label: string; value: string }[] = [
    { label: '16pt', value: '16pt' },
    { label: '22pt', value: '22pt' },
    { label: '28pt', value: '28pt' },
];

interface GlobalSentinelHost {
    [REGISTERED_KEY]?: boolean;
}

interface BlockSettings {
    name?: string;
    edit?: unknown;
    [key: string]: unknown;
}

interface EditProps {
    attributes: Record<string, unknown>;
    setAttributes: ( changes: Record<string, unknown> ) => void;
}

function CategoriesEdit( { attributes, setAttributes }: EditProps ): JSX.Element {
    const blockProps = useBlockProps();
    const showPostCounts = Boolean( attributes.showPostCounts );
    const showHierarchy = Boolean( attributes.showHierarchy );
    const showOnlyTopLevel = Boolean( attributes.showOnlyTopLevel );
    const showEmpty = Boolean( attributes.showEmpty );

    return (
        <>
            <InspectorControls>
                <PanelBody title={ __( 'Settings', TEXT_DOMAIN ) }>
                    <ToggleControl
                        label={ __( 'Show post counts', TEXT_DOMAIN ) }
                        checked={ showPostCounts }
                        onChange={ ( value ) => setAttributes( { showPostCounts: value } ) }
                    />
                    <ToggleControl
                        label={ __( 'Show hierarchy', TEXT_DOMAIN ) }
                        checked={ showHierarchy }
                        onChange={ ( value ) => setAttributes( { showHierarchy: value } ) }
                    />
                    <ToggleControl
                        label={ __( 'Show only top level', TEXT_DOMAIN ) }
                        checked={ showOnlyTopLevel }
                        onChange={ ( value ) => setAttributes( { showOnlyTopLevel: value } ) }
                    />
                    <ToggleControl
                        label={ __( 'Show empty terms', TEXT_DOMAIN ) }
                        checked={ showEmpty }
                        onChange={ ( value ) => setAttributes( { showEmpty: value } ) }
                    />
                </PanelBody>
            </InspectorControls>
            <div { ...blockProps }>
                <ServerSideRender block="core/categories" attributes={ attributes } />
            </div>
        </>
    );
}

function TagCloudEdit( { attributes, setAttributes }: EditProps ): JSX.Element {
    const blockProps = useBlockProps();
    const numberOfTags =
        typeof attributes.numberOfTags === 'number' ? attributes.numberOfTags : 45;
    const showTagCounts = Boolean( attributes.showTagCounts );
    const smallestFontSize =
        typeof attributes.smallestFontSize === 'string'
            ? attributes.smallestFontSize
            : '8pt';
    const largestFontSize =
        typeof attributes.largestFontSize === 'string'
            ? attributes.largestFontSize
            : '22pt';

    return (
        <>
            <InspectorControls>
                <PanelBody title={ __( 'Settings', TEXT_DOMAIN ) }>
                    <RangeControl
                        label={ __( 'Number of tags', TEXT_DOMAIN ) }
                        min={ 1 }
                        max={ 100 }
                        value={ numberOfTags }
                        onChange={ ( value ) => setAttributes( { numberOfTags: value ?? 45 } ) }
                    />
                    <ToggleControl
                        label={ __( 'Show tag counts', TEXT_DOMAIN ) }
                        checked={ showTagCounts }
                        onChange={ ( value ) => setAttributes( { showTagCounts: value } ) }
                    />
                    <SelectControl
                        label={ __( 'Smallest size', TEXT_DOMAIN ) }
                        value={ smallestFontSize }
                        options={ SMALLEST_SIZE_OPTIONS }
                        onChange={ ( value ) => setAttributes( { smallestFontSize: value } ) }
                    />
                    <SelectControl
                        label={ __( 'Largest size', TEXT_DOMAIN ) }
                        value={ largestFontSize }
                        options={ LARGEST_SIZE_OPTIONS }
                        onChange={ ( value ) => setAttributes( { largestFontSize: value } ) }
                    />
                </PanelBody>
            </InspectorControls>
            <div { ...blockProps }>
                <ServerSideRender block="core/tag-cloud" attributes={ attributes } />
            </div>
        </>
    );
}

function ArchivesEdit( { attributes, setAttributes }: EditProps ): JSX.Element {
    const blockProps = useBlockProps();
    const showPostCounts = Boolean( attributes.showPostCounts );
    const displayAsDropdown = Boolean( attributes.displayAsDropdown );
    const type = attributes.type === 'yearly' ? 'yearly' : 'monthly';

    return (
        <>
            <InspectorControls>
                <PanelBody title={ __( 'Settings', TEXT_DOMAIN ) }>
                    <ToggleControl
                        label={ __( 'Display as dropdown', TEXT_DOMAIN ) }
                        checked={ displayAsDropdown }
                        onChange={ ( value ) => setAttributes( { displayAsDropdown: value } ) }
                    />
                    <ToggleControl
                        label={ __( 'Show post counts', TEXT_DOMAIN ) }
                        checked={ showPostCounts }
                        onChange={ ( value ) => setAttributes( { showPostCounts: value } ) }
                    />
                    <SelectControl
                        label={ __( 'Group by', TEXT_DOMAIN ) }
                        value={ type }
                        options={ [
                            { label: __( 'Monthly', TEXT_DOMAIN ), value: 'monthly' },
                            { label: __( 'Yearly', TEXT_DOMAIN ), value: 'yearly' },
                        ] }
                        onChange={ ( value ) => setAttributes( { type: value } ) }
                    />
                </PanelBody>
            </InspectorControls>
            <div { ...blockProps }>
                <ServerSideRender block="core/archives" attributes={ attributes } />
            </div>
        </>
    );
}

const EDITS: Record<string, ( props: EditProps ) => JSX.Element> = {
    'core/categories': CategoriesEdit,
    'core/tag-cloud': TagCloudEdit,
    'core/archives': ArchivesEdit,
};

function overrideEdit( settings: BlockSettings, name: string ): BlockSettings {
    if ( ! TARGET_BLOCKS.has( name ) ) {
        return settings;
    }

    const edit = EDITS[ name ];

    if ( edit === undefined ) {
        return settings;
    }

    return { ...settings, edit };
}

export function registerTaxonomyAndArchiveBlockOverrides(): void {
    const host = globalThis as unknown as GlobalSentinelHost;

    if ( host[ REGISTERED_KEY ] === true ) {
        return;
    }

    addFilter( FILTER_HOOK, FILTER_NAMESPACE, overrideEdit );
    host[ REGISTERED_KEY ] = true;
}
