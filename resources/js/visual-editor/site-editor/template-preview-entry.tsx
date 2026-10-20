/**
 * Template preview entry (#993).
 *
 * Resolves the entry whose content the site editor previews inside a
 * single-entry template (`single`, `page`, `single-{type}`, …): the
 * latest published entry of the template's post type by default, the
 * entry matching a `page-{slug}` template's slug when it's among the
 * results, or whichever entry the user picks in
 * {@see TemplatePreviewEntryPicker}. The entry reaches the `post-*`
 * blocks through the `artisanpack/postPreview` block context they
 * already read for query-loop previews, so they render it read-only.
 */

import { SelectControl } from '@wordpress/components';
import { __, sprintf } from '@wordpress/i18n';
import { useCallback, useMemo, useState } from 'react';

import {
    useQueryPreview,
    type QueryPreviewPost,
    type QueryPreviewStatus,
} from '../editor/use-query-preview';
import { TEXT_DOMAIN } from '../vendor/i18n';

import { templateSlugToPostType, type TemplatePreviewTarget } from './template-post-type';

/** Candidate entries fetched for the picker. */
export const TEMPLATE_PREVIEW_ENTRY_LIMIT = 20;

export interface TemplatePreviewEntryState {
    /** Post type + entry slug the template maps to; null when it doesn't. */
    readonly target: TemplatePreviewTarget | null;
    readonly status: QueryPreviewStatus;
    /** Candidate entries, newest first. */
    readonly entries: readonly QueryPreviewPost[];
    /** The entry the blocks preview; null falls back to placeholders. */
    readonly entry: QueryPreviewPost | null;
    /** Picks a candidate by id; null returns to the default entry. */
    readonly selectEntry: (id: number | null) => void;
}

/**
 * Resolves the preview entry for a template slug.
 *
 * @since 1.15.3
 */
export function useTemplatePreviewEntry(
    apiBase: string,
    templateSlug: string | null
): TemplatePreviewEntryState {
    const target = useMemo(
        () => (templateSlug === null ? null : templateSlugToPostType(templateSlug)),
        [templateSlug]
    );
    const query = useMemo(
        () =>
            target === null
                ? null
                : {
                      postType: target.postType,
                      perPage: TEMPLATE_PREVIEW_ENTRY_LIMIT,
                      orderBy: 'date',
                      order: 'desc',
                  },
        [target]
    );
    const preview = useQueryPreview(query, { apiBase });

    // Keyed by template so switching templates drops the old pick.
    const [selection, setSelection] = useState<{ slug: string | null; id: number | null }>({
        slug: templateSlug,
        id: null,
    });
    const selectedId = selection.slug === templateSlug ? selection.id : null;

    const entries = useMemo(
        (): readonly QueryPreviewPost[] => (target === null ? [] : preview.posts),
        [target, preview.posts]
    );

    const entry = useMemo((): QueryPreviewPost | null => {
        if (entries.length === 0) {
            return null;
        }

        const picked = selectedId === null ? undefined : entries.find((post) => post.id === selectedId);

        if (picked !== undefined) {
            return picked;
        }

        const slugMatch =
            target?.entrySlug === null || target?.entrySlug === undefined
                ? undefined
                : entries.find((post) => post.slug === target.entrySlug);

        return slugMatch ?? entries[0] ?? null;
    }, [entries, selectedId, target]);

    const selectEntry = useCallback(
        (id: number | null): void => setSelection({ slug: templateSlug, id }),
        [templateSlug]
    );
    const status: QueryPreviewStatus = target === null ? 'idle' : preview.status;

    // Memoized so the canvas header / canvas memos downstream only
    // rebuild when the preview actually changes.
    return useMemo(
        () => ({ target, status, entries, entry, selectEntry }),
        [target, status, entries, entry, selectEntry]
    );
}

export interface TemplatePreviewEntryPickerProps {
    readonly state: TemplatePreviewEntryState;
}

/**
 * "Preview content" picker for the template canvas header — the
 * equivalent of WordPress's template preview selector. Renders nothing
 * for templates that don't map to a post type.
 *
 * @since 1.15.3
 */
export function TemplatePreviewEntryPicker({ state }: TemplatePreviewEntryPickerProps): JSX.Element | null {
    const { target, status, entries, entry, selectEntry } = state;

    if (target === null) {
        return null;
    }

    if (status === 'loading' && entries.length === 0) {
        return (
            <span
                className="ap-site-editor__preview-entry"
                data-testid="ap-site-editor-preview-entry-loading"
            >
                {__('Loading preview content…', TEXT_DOMAIN)}
            </span>
        );
    }

    if (entries.length === 0) {
        return (
            <span
                className="ap-site-editor__preview-entry"
                data-testid="ap-site-editor-preview-entry-empty"
            >
                {sprintf(
                    /* translators: %s: post type slug, e.g. "post". */
                    __('No published %s to preview — showing placeholders.', TEXT_DOMAIN),
                    target.postType
                )}
            </span>
        );
    }

    return (
        <span
            className="ap-site-editor__preview-entry"
            data-testid="ap-site-editor-preview-entry"
        >
            <SelectControl
                label={__('Preview content', TEXT_DOMAIN)}
                value={entry === null ? '' : String(entry.id)}
                options={entries.map((post) => ({
                    value: String(post.id),
                    label:
                        post.title !== undefined && post.title !== ''
                            ? post.title
                            : sprintf(
                                  /* translators: %d: entry id. */
                                  __('(no title) #%d', TEXT_DOMAIN),
                                  post.id
                              ),
                }))}
                onChange={(value: string) => {
                    const id = Number.parseInt(value, 10);
                    selectEntry(Number.isFinite(id) ? id : null);
                }}
            />
        </span>
    );
}
