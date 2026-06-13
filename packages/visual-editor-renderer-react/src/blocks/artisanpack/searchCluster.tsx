/**
 * React renderers for the search cluster (#502).
 *
 * Mirrors the Blade partials and Vue components so every renderer emits
 * identical markup. The blocks share a common contract: anything that
 * depends on the live request — the current `?s=` keyword, the
 * `?taxonomy=` selection, the `?post_type=` filter — arrives on the
 * attributes as a `_resolved*` stamp, so the React + Vue renderers stay
 * purely declarative.
 */

import { type JSX, type ReactNode } from 'react';

import {
    attrArray,
    attrBoolean,
    attrString,
    classList,
} from '../../support/attributes';
import { safeUrl } from '../../support/urlSanitizer';
import type { BlockRendererProps } from '../../types';

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

export function SearchFieldBlock({ attributes }: BlockRendererProps): JSX.Element {
    const label = attrString(attributes.label, 'Search');
    const placeholder = attrString(attributes.placeholder, 'Search …');
    const searchValue = attrString(attributes._resolvedSearchValue);
    const className = attrString(attributes.className);

    const classes = classList(['ap-search-field', className]);
    const inputId = `ap-search-field-${shortHash(
        `${label}|${placeholder}|${searchValue}`
    )}`;

    return (
        <div className={classes}>
            <label className="ap-search-field__label" htmlFor={inputId}>
                {label}
            </label>
            <input
                type="search"
                className="ap-search-field__input"
                id={inputId}
                name="s"
                defaultValue={searchValue}
                placeholder={placeholder}
            />
        </div>
    );
}

export function SearchFiltersBlock({
    attributes,
    children,
}: BlockRendererProps): JSX.Element {
    const postType = sanitizeSlug(attributes.postType, 'post');
    const action = safeUrl(attrString(attributes._resolvedFormAction, '/')) || '/';
    const className = attrString(attributes.className);

    const classes = classList(['ap-search-filters', className]);

    return (
        <div className={classes}>
            <form className="ap-search-filters__form" method="get" action={action}>
                <input type="hidden" name="post_type" value={postType} />
                {children}
            </form>
        </div>
    );
}

export function SearchFiltersButtonsBlock({
    attributes,
}: BlockRendererProps): JSX.Element {
    const searchLabel = attrString(attributes.searchLabel, 'Search');
    const clearLabel = attrString(attributes.clearLabel, 'Clear');
    const className = attrString(attributes.className);

    const classes = classList(['ap-search-filters-buttons', className]);

    return (
        <div className={classes}>
            <input
                type="submit"
                className="ap-search-filters-buttons__submit"
                value={searchLabel}
            />
            <input
                type="reset"
                className="ap-search-filters-buttons__reset"
                value={clearLabel}
            />
        </div>
    );
}

export function SearchFiltersTaxonomyBlock({
    attributes,
}: BlockRendererProps): JSX.Element {
    const label = attrString(attributes.label, 'Choose');
    const taxonomy = sanitizeSlug(attributes.taxonomy, 'category');
    const taxonomyName = attrString(attributes.taxonomyName, 'Category');
    const className = attrString(attributes.className);

    const terms = normalizeTerms(attributes._resolvedTerms);
    const selectedTerm = attrString(attributes._resolvedSelectedTerm);

    const classes = classList(['ap-search-filters-taxonomy', className]);
    const selectId = `ap-search-filters-taxonomy-${taxonomy}`;

    return (
        <div className={classes}>
            <label
                className="ap-search-filters-taxonomy__label"
                htmlFor={selectId}
            >
                {label}
            </label>
            <select
                className="ap-search-filters-taxonomy__select"
                id={selectId}
                name={taxonomy}
                defaultValue={selectedTerm}
            >
                <option value="">Select a {taxonomyName}</option>
                {terms.map((term) => (
                    <option key={term.slug} value={term.slug}>
                        {term.name}
                    </option>
                ))}
            </select>
        </div>
    );
}

export function PostTypesSearchResultsBlock({
    attributes,
    children,
}: BlockRendererProps): JSX.Element {
    const className = attrString(attributes.className);
    const classes = classList(['ap-post-types-search-results', className]);

    return <div className={classes}>{children}</div>;
}

export function SinglePostTypesSearchResultsBlock({
    attributes,
    children,
}: BlockRendererProps): ReactNode {
    const isActive = attrBoolean(attributes._resolvedActive, false);
    if (!isActive) {
        return null;
    }

    const className = attrString(attributes.className);
    const classes = classList(['ap-single-post-types-search-results', className]);

    return <div className={classes}>{children}</div>;
}
