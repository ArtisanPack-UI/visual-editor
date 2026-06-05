/**
 * Link-control menu picker.
 *
 * Drives the "what does this menu item point to?" UI. The user picks a
 * type (Page / Post / Custom URL / Taxonomy term) and either searches
 * the host app's entities (page / post / taxonomy / template) or types
 * a URL (custom). Powered by the D4-only `/visual-editor/api/search`
 * endpoint — see EntitySearchController.
 *
 * Type list is fixed: V1 supports the four IA types from design brief
 * §3.8. A host app that registers extra `resources` won't see them in
 * the picker (intentional — the picker is for menu IA, not arbitrary
 * data sources). 1.1+ may make the type list extensible.
 *
 * Form fields use the shared `.ap-site-editor__dialog-*` classes so
 * the type select / search field render identically to the templates
 * inspector.
 */

import { __ } from '@wordpress/i18n';
import { useEffect, useId, useMemo, useRef, useState } from 'react';

import { TEXT_DOMAIN } from '../../vendor/i18n';

import type { SiteEditorApiConfig } from '../api-client';
import { searchEntities, type SearchResult } from './api-client';
import type { MenuItem, MenuItemType } from './menu-tree';

export interface LinkPickerProps {
    apiConfig: SiteEditorApiConfig;
    item: MenuItem;
    onChange: (patch: Partial<MenuItem>) => void;
}

interface TypeOption {
    value: MenuItemType;
    label: string;
    /**
     * `null` = no entity-search step (custom URL takes the URL field
     * directly). Otherwise the slug we send to /search.
     */
    searchType: string | null;
}

function getTypeOptions(): readonly TypeOption[] {
    return [
        { value: 'page', label: __('Page', TEXT_DOMAIN), searchType: 'page' },
        { value: 'post', label: __('Post', TEXT_DOMAIN), searchType: 'post' },
        {
            value: 'taxonomy',
            label: __('Taxonomy term', TEXT_DOMAIN),
            searchType: 'taxonomy',
        },
        {
            value: 'custom',
            label: __('Custom URL', TEXT_DOMAIN),
            searchType: null,
        },
    ];
}

export function LinkPicker(props: LinkPickerProps): JSX.Element {
    const { apiConfig, item, onChange } = props;
    const typeId = useId();
    const queryId = useId();
    const urlId = useId();
    const searchErrorId = useId();

    const typeOptions = useMemo(() => getTypeOptions(), []);
    const activeType = typeOptions.find((option) => option.value === item.type);

    const [query, setQuery] = useState('');
    const [results, setResults] = useState<readonly SearchResult[]>([]);
    const [isSearching, setIsSearching] = useState(false);
    const [searchError, setSearchError] = useState<string | null>(null);

    const requestEpoch = useRef(0);

    useEffect(() => {
        if (activeType === undefined || activeType.searchType === null) {
            setResults([]);
            setIsSearching(false);
            setSearchError(null);
            return undefined;
        }

        // Debounce: 250ms is the same cadence the templates `slug`
        // input uses elsewhere in the editor for "stop typing first".
        const handle = window.setTimeout(() => {
            requestEpoch.current += 1;
            const epoch = requestEpoch.current;

            setIsSearching(true);
            setSearchError(null);

            (async () => {
                try {
                    const data = await searchEntities(apiConfig, {
                        type: activeType.searchType ?? '',
                        q: query,
                    });

                    if (epoch !== requestEpoch.current) {
                        return;
                    }

                    setResults(data);
                } catch (error: unknown) {
                    if (epoch !== requestEpoch.current) {
                        return;
                    }

                    setSearchError(
                        error instanceof Error
                            ? error.message
                            : __('Search failed.', TEXT_DOMAIN)
                    );
                    setResults([]);
                } finally {
                    if (epoch === requestEpoch.current) {
                        setIsSearching(false);
                    }
                }
            })();
        }, 250);

        return () => window.clearTimeout(handle);
    }, [activeType, apiConfig, query]);

    return (
        <div
            className="ap-nav-inspector__link-picker"
            data-testid="ap-nav-link-picker"
        >
            <div className="ap-site-editor__dialog-field">
                <label
                    className="ap-site-editor__dialog-label"
                    htmlFor={typeId}
                >
                    {__('Link type', TEXT_DOMAIN)}
                </label>
                <select
                    id={typeId}
                    className="ap-site-editor__dialog-select"
                    value={item.type}
                    onChange={(event) => {
                        const next = event.target.value as MenuItemType;
                        // Switching type clears `targetId` + `url` so the
                        // user can't accidentally save a stale typed
                        // reference under the wrong wire shape. Also
                        // clear `sourceKind` / `sourceType` so the new
                        // IA selection drives serialization — without
                        // that, a CPT-sourced item the user retypes as
                        // `Page` would still emit the original CPT
                        // wire shape.
                        onChange({
                            type: next,
                            targetId: null,
                            url: next === 'custom' ? item.url : null,
                            sourceKind: null,
                            sourceType: null,
                        });
                    }}
                    data-testid="ap-nav-link-picker-type"
                >
                    {typeOptions.map((option) => (
                        <option key={option.value} value={option.value}>
                            {option.label}
                        </option>
                    ))}
                </select>
            </div>

            {activeType !== undefined && activeType.searchType !== null ? (
                <>
                    <div className="ap-site-editor__dialog-field">
                        <label
                            className="ap-site-editor__dialog-label"
                            htmlFor={queryId}
                        >
                            {__('Search', TEXT_DOMAIN)}
                        </label>
                        <input
                            id={queryId}
                            type="search"
                            className="ap-site-editor__dialog-input"
                            value={query}
                            onChange={(event) => setQuery(event.target.value)}
                            placeholder={__('Type to search…', TEXT_DOMAIN)}
                            data-testid="ap-nav-link-picker-query"
                            aria-invalid={
                                Boolean(searchError) || undefined
                            }
                            aria-describedby={
                                searchError !== null
                                    ? searchErrorId
                                    : undefined
                            }
                        />
                    </div>

                    {searchError !== null ? (
                        <p
                            id={searchErrorId}
                            className="ap-site-editor__dialog-field-error"
                            role="alert"
                        >
                            {searchError}
                        </p>
                    ) : null}

                    <ul
                        className="ap-nav-inspector__results"
                        aria-label={__('Search results', TEXT_DOMAIN)}
                        aria-busy={isSearching}
                        data-testid="ap-nav-link-picker-results"
                    >
                        {results.length === 0 && !isSearching ? (
                            <li className="ap-nav-inspector__result-empty">
                                {__('No results.', TEXT_DOMAIN)}
                            </li>
                        ) : null}
                        {results.map((result) => {
                            const selected =
                                item.targetId !== null &&
                                String(item.targetId) === String(result.id);

                            return (
                                <li
                                    key={`${result.type}-${result.id}`}
                                    className="ap-nav-inspector__result"
                                >
                                    <button
                                        type="button"
                                        className="ap-nav-inspector__result-button"
                                        data-selected={selected}
                                        onClick={() => {
                                            onChange({
                                                targetId: result.id,
                                                autoLabel: result.title,
                                                url:
                                                    result.url ?? item.url,
                                                // Picking a result is an
                                                // explicit re-typing — the
                                                // wire-source attributes
                                                // from the original block
                                                // no longer apply.
                                                sourceKind: null,
                                                sourceType: null,
                                            });
                                        }}
                                        data-testid={`ap-nav-link-picker-result-${result.id}`}
                                    >
                                        {result.title}
                                    </button>
                                </li>
                            );
                        })}
                    </ul>
                </>
            ) : (
                <div className="ap-site-editor__dialog-field">
                    <label
                        className="ap-site-editor__dialog-label"
                        htmlFor={urlId}
                    >
                        {__('URL', TEXT_DOMAIN)}
                    </label>
                    <input
                        id={urlId}
                        type="url"
                        className="ap-site-editor__dialog-input"
                        value={item.url ?? ''}
                        onChange={(event) =>
                            onChange({ url: event.target.value })
                        }
                        placeholder="https://example.com"
                        data-testid="ap-nav-link-picker-url"
                    />
                </div>
            )}
        </div>
    );
}
