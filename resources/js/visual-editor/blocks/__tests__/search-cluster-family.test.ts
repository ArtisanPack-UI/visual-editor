/**
 * Generic search cluster contract (#502).
 *
 * block.json + save contract for the six search blocks:
 *
 *   - search-field
 *   - search-filters (container)
 *   - search-filters-buttons
 *   - search-filters-taxonomy
 *   - post-types-search-results (parent container)
 *   - single-post-types-search-results (child of post-types-search-results)
 *
 * All six live under the `artisanpack` namespace + category and the
 * `artisanpack-visual-editor` textdomain. Every block is a dynamic
 * block (save returns null) so the renderers can read live request
 * state at render time.
 */

import { describe, expect, it, vi } from 'vitest';

vi.mock('@wordpress/block-editor', () => ({
    InnerBlocks: Object.assign(() => null, { Content: () => null }),
    RichText: Object.assign(() => null, { Content: () => null }),
    useBlockProps: Object.assign(() => ({}), { save: () => ({}) }),
    useInnerBlocksProps: Object.assign(() => ({}), { save: () => ({}) }),
    InspectorControls: () => null,
}));

vi.mock('@wordpress/components', () => ({
    PanelBody: () => null,
    SelectControl: () => null,
    TextControl: () => null,
}));

vi.mock('@wordpress/i18n', () => ({
    __: (value: string) => value,
}));

import searchFieldMeta from '../search-field/block.json';
import searchFiltersMeta from '../search-filters/block.json';
import searchFiltersButtonsMeta from '../search-filters-buttons/block.json';
import searchFiltersTaxonomyMeta from '../search-filters-taxonomy/block.json';
import postTypesSearchResultsMeta from '../post-types-search-results/block.json';
import singlePostTypesSearchResultsMeta from '../single-post-types-search-results/block.json';

import searchFieldSave from '../search-field/save';
import searchFiltersSave from '../search-filters/save';
import searchFiltersButtonsSave from '../search-filters-buttons/save';
import searchFiltersTaxonomySave from '../search-filters-taxonomy/save';
import postTypesSearchResultsSave from '../post-types-search-results/save';
import singlePostTypesSearchResultsSave from '../single-post-types-search-results/save';

const FAMILY = [
    { slug: 'search-field', meta: searchFieldMeta },
    { slug: 'search-filters', meta: searchFiltersMeta },
    { slug: 'search-filters-buttons', meta: searchFiltersButtonsMeta },
    { slug: 'search-filters-taxonomy', meta: searchFiltersTaxonomyMeta },
    { slug: 'post-types-search-results', meta: postTypesSearchResultsMeta },
    {
        slug: 'single-post-types-search-results',
        meta: singlePostTypesSearchResultsMeta,
    },
] as const;

describe('search cluster block.json', () => {
    it.each(FAMILY)(
        '$slug declares the artisanpack namespace + textdomain + category',
        ({ slug, meta }) => {
            expect(meta.name).toBe(`artisanpack/${slug}`);
            expect(meta.textdomain).toBe('artisanpack-visual-editor');
            expect(meta.category).toBe('search');
        }
    );

    it('search-field declares the label + placeholder attributes with safe defaults', () => {
        const attrs = (
            searchFieldMeta as { attributes?: Record<string, { default?: unknown }> }
        ).attributes;
        expect(attrs?.label?.default).toBe('Search');
        expect(attrs?.placeholder?.default).toBe('Search …');
    });

    it('search-filters declares the postType attribute with a `post` default', () => {
        const attrs = (
            searchFiltersMeta as { attributes?: Record<string, { default?: unknown }> }
        ).attributes;
        expect(attrs?.postType?.default).toBe('post');
    });

    it('search-filters-buttons declares searchLabel + clearLabel attributes', () => {
        const attrs = (
            searchFiltersButtonsMeta as {
                attributes?: Record<string, { default?: unknown }>;
            }
        ).attributes;
        expect(attrs?.searchLabel?.default).toBe('Search');
        expect(attrs?.clearLabel?.default).toBe('Clear');
    });

    it('search-filters-taxonomy declares label + taxonomy + taxonomyName attributes', () => {
        const attrs = (
            searchFiltersTaxonomyMeta as {
                attributes?: Record<string, { default?: unknown }>;
            }
        ).attributes;
        expect(attrs?.label?.default).toBe('Choose');
        expect(attrs?.taxonomy?.default).toBe('category');
        expect(attrs?.taxonomyName?.default).toBe('Category');
    });

    it('single-post-types-search-results declares the post-type parent + postType attribute', () => {
        const meta = singlePostTypesSearchResultsMeta as {
            parent?: ReadonlyArray<string>;
            attributes?: Record<string, { default?: unknown }>;
        };
        expect(meta.parent).toContain('artisanpack/post-types-search-results');
        expect(meta.attributes?.postType?.default).toBe('all');
    });
});

describe('search cluster save contract', () => {
    it.each(FAMILY)('$slug.save returns null (dynamic block)', ({ slug }) => {
        const save = (
            {
                'search-field': searchFieldSave,
                'search-filters': searchFiltersSave,
                'search-filters-buttons': searchFiltersButtonsSave,
                'search-filters-taxonomy': searchFiltersTaxonomySave,
                'post-types-search-results': postTypesSearchResultsSave,
                'single-post-types-search-results':
                    singlePostTypesSearchResultsSave,
            } as Record<string, () => null>
        )[slug];
        expect(save()).toBeNull();
    });
});
