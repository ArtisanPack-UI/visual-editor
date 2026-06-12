/**
 * Vue renderers for the search cluster (#502).
 *
 * Mirrors the Blade partials and React components so every renderer
 * emits identical markup. Every request-time value (`?s=`, `?taxonomy=`,
 * `?post_type=`) arrives via a `_resolved*` stamp, so these components
 * stay purely declarative.
 */

import { defineComponent, h, type VNode } from 'vue';

import {
    attrArray,
    attrBoolean,
    attrString,
    classList,
} from '../../support/attributes';
import { safeUrl } from '../../support/urlSanitizer';
import { blockRendererProps } from '../shared';

const SLUG_PATTERN = /[^a-z0-9_-]/gi;

function sanitizeSlug(value: unknown, fallback: string): string {
    const raw = attrString(value, fallback).replace(SLUG_PATTERN, '').toLowerCase();
    return raw === '' ? fallback.toLowerCase() : raw;
}

function shortHash(value: string): string {
    let hash = 0;
    for (let i = 0; i < value.length; i++) {
        hash = (hash * 31 + value.charCodeAt(i)) | 0;
    }
    return (hash >>> 0).toString(16).padStart(8, '0').slice(0, 8);
}

interface TaxonomyTerm {
    readonly slug: string;
    readonly name: string;
}

function normalizeTerms(value: unknown): ReadonlyArray<TaxonomyTerm> {
    const terms: TaxonomyTerm[] = [];
    for (const entry of attrArray(value)) {
        if (entry === null || typeof entry !== 'object') {
            continue;
        }
        const record = entry as Record<string, unknown>;
        const slug = attrString(record.slug);
        const name = attrString(record.name);
        if (slug === '' || name === '') {
            continue;
        }
        terms.push({ slug, name });
    }
    return terms;
}

export const SearchFieldBlock = defineComponent({
    name: 'SearchFieldBlock',
    props: blockRendererProps,
    setup(props) {
        return (): VNode => {
            const label = attrString(props.attributes.label, 'Search');
            const placeholder = attrString(
                props.attributes.placeholder,
                'Search …'
            );
            const searchValue = attrString(props.attributes._resolvedSearchValue);
            const className = attrString(props.attributes.className);

            const classes = classList(['ap-search-field', className]);
            const inputId = `ap-search-field-${shortHash(
                `${label}|${placeholder}|${searchValue}`
            )}`;

            return h('div', { class: classes }, [
                h(
                    'label',
                    { class: 'ap-search-field__label', for: inputId },
                    label
                ),
                h('input', {
                    type: 'search',
                    class: 'ap-search-field__input',
                    id: inputId,
                    name: 's',
                    placeholder,
                    value: searchValue,
                }),
            ]);
        };
    },
});

export const SearchFiltersBlock = defineComponent({
    name: 'SearchFiltersBlock',
    props: blockRendererProps,
    setup(props, { slots }) {
        return (): VNode => {
            const postType = sanitizeSlug(props.attributes.postType, 'post');
            const action =
                safeUrl(attrString(props.attributes._resolvedFormAction, '/')) ||
                '/';
            const className = attrString(props.attributes.className);

            const classes = classList(['ap-search-filters', className]);

            return h('div', { class: classes }, [
                h(
                    'form',
                    {
                        class: 'ap-search-filters__form',
                        method: 'get',
                        action,
                    },
                    [
                        h('input', {
                            type: 'hidden',
                            name: 'post_type',
                            value: postType,
                        }),
                        slots.default?.(),
                    ]
                ),
            ]);
        };
    },
});

export const SearchFiltersButtonsBlock = defineComponent({
    name: 'SearchFiltersButtonsBlock',
    props: blockRendererProps,
    setup(props) {
        return (): VNode => {
            const searchLabel = attrString(
                props.attributes.searchLabel,
                'Search'
            );
            const clearLabel = attrString(props.attributes.clearLabel, 'Clear');
            const className = attrString(props.attributes.className);

            const classes = classList(['ap-search-filters-buttons', className]);

            return h('div', { class: classes }, [
                h('input', {
                    type: 'submit',
                    class: 'ap-search-filters-buttons__submit',
                    value: searchLabel,
                }),
                h('input', {
                    type: 'reset',
                    class: 'ap-search-filters-buttons__reset',
                    value: clearLabel,
                }),
            ]);
        };
    },
});

export const SearchFiltersTaxonomyBlock = defineComponent({
    name: 'SearchFiltersTaxonomyBlock',
    props: blockRendererProps,
    setup(props) {
        return (): VNode => {
            const label = attrString(props.attributes.label, 'Choose');
            const taxonomy = sanitizeSlug(props.attributes.taxonomy, 'category');
            const taxonomyName = attrString(
                props.attributes.taxonomyName,
                'Category'
            );
            const className = attrString(props.attributes.className);
            const terms = normalizeTerms(props.attributes._resolvedTerms);
            const selectedTerm = attrString(
                props.attributes._resolvedSelectedTerm
            );

            const classes = classList(['ap-search-filters-taxonomy', className]);
            const selectId = `ap-search-filters-taxonomy-${taxonomy}`;

            const hasMatchingTerm = terms.some(
                (term) => term.slug === selectedTerm
            );
            const placeholderSelected = !hasMatchingTerm;

            const options: VNode[] = [
                h(
                    'option',
                    {
                        value: '',
                        ...(placeholderSelected ? { selected: '' } : {}),
                    },
                    `Select a ${taxonomyName}`
                ),
                ...terms.map((term) =>
                    h(
                        'option',
                        {
                            value: term.slug,
                            ...(term.slug === selectedTerm
                                ? { selected: '' }
                                : {}),
                        },
                        term.name
                    )
                ),
            ];

            return h('div', { class: classes }, [
                h(
                    'label',
                    {
                        class: 'ap-search-filters-taxonomy__label',
                        for: selectId,
                    },
                    label
                ),
                h(
                    'select',
                    {
                        class: 'ap-search-filters-taxonomy__select',
                        id: selectId,
                        name: taxonomy,
                    },
                    options
                ),
            ]);
        };
    },
});

export const PostTypesSearchResultsBlock = defineComponent({
    name: 'PostTypesSearchResultsBlock',
    props: blockRendererProps,
    setup(props, { slots }) {
        return (): VNode => {
            const className = attrString(props.attributes.className);
            const classes = classList([
                'ap-post-types-search-results',
                className,
            ]);
            return h('div', { class: classes }, slots.default?.());
        };
    },
});

export const SinglePostTypesSearchResultsBlock = defineComponent({
    name: 'SinglePostTypesSearchResultsBlock',
    props: blockRendererProps,
    setup(props, { slots }) {
        return (): VNode | null => {
            const isActive = attrBoolean(
                props.attributes._resolvedActive,
                false
            );
            if (!isActive) {
                return null;
            }
            const className = attrString(props.attributes.className);
            const classes = classList([
                'ap-single-post-types-search-results',
                className,
            ]);
            return h('div', { class: classes }, slots.default?.());
        };
    },
});
