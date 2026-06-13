/**
 * Related Posts — editor-side component (#501).
 *
 * Mirrors `artisanpack/query` for the inspector UX (posts-per-page,
 * order, columns, offset) but locks the relation rule to "same post
 * type as the host entry, sharing at least one term in the host's
 * primary taxonomy". The `query` attribute uses the query-block shape
 * because the renderers + server-side `QueryInliner` consume it; the
 * `postType` comes from the host post in scope and is not pickable here.
 *
 * The canvas previews the editable inner-block tree once against the
 * host post, then renders N − 1 read-only stub cards below it so authors
 * can see at a glance how many related posts will render. Server-side
 * `QueryInliner.expandRelatedPosts` resolves the real list via the
 * bound `QueryResolverContract` and stamps each clone through
 * `PostResolver` at render time.
 */

import type { ReactElement } from 'react';
import { useEffect, useState } from '@wordpress/element';
import {
    InspectorControls,
    useBlockProps,
    useInnerBlocksProps,
} from '@wordpress/block-editor';
import { PanelBody, RangeControl, SelectControl } from '@wordpress/components';
import { __ } from '@wordpress/i18n';

import { TEXT_DOMAIN } from '../../vendor/i18n';

interface RelatedPostsQuery {
    readonly perPage?: number;
    readonly offset?: number;
    readonly order?: 'asc' | 'desc';
    readonly orderBy?: string;
    readonly postType?: string;
    readonly inherit?: boolean;
}

interface RelatedPostsDisplayLayout {
    readonly type?: string;
    readonly columns?: number;
}

interface RelatedPostsAttributes {
    readonly numPosts: number;
    readonly numColumns: number;
    readonly queryId?: string;
    readonly query?: RelatedPostsQuery;
    readonly displayLayout?: RelatedPostsDisplayLayout;
}

interface RelatedPostsEditProps {
    readonly attributes: RelatedPostsAttributes;
    readonly setAttributes: (next: Partial<RelatedPostsAttributes>) => void;
    readonly clientId: string;
}

interface PreviewPost {
    readonly id: number;
    readonly title: string;
    readonly excerpt: string;
    readonly date: string;
}

const TEMPLATE: [string, Record<string, unknown>][] = [
    ['artisanpack/post-title', {}],
    ['artisanpack/post-date', {}],
    ['artisanpack/post-excerpt', {}],
];

const API_BASE = '/visual-editor/api';

function clampPosts(value: number | undefined, fallback: number): number {
    const next =
        typeof value === 'number' && Number.isFinite(value)
            ? Math.trunc(value)
            : fallback;
    if (next < 1) {
        return 1;
    }
    if (next > 10) {
        return 10;
    }
    return next;
}

function clampColumns(value: number | undefined, fallback: number): number {
    const next =
        typeof value === 'number' && Number.isFinite(value)
            ? Math.trunc(value)
            : fallback;
    if (next < 1) {
        return 1;
    }
    if (next > 4) {
        return 4;
    }
    return next;
}

function clampOffset(value: number | undefined): number {
    const next =
        typeof value === 'number' && Number.isFinite(value)
            ? Math.trunc(value)
            : 0;
    return next < 0 ? 0 : next;
}

// The REST surface mirrors WP's `excerpt.rendered` shape, which ships
// `<p>...</p>` wrappers and entity-encoded punctuation. The preview
// card only needs a one-line plain-text excerpt — strip tags + decode
// entities client-side so we never feed unsanitized HTML through
// `dangerouslySetInnerHTML`.
function stripHtml(value: string): string {
    if (typeof document === 'undefined') {
        return value.replace(/<[^>]*>/g, '').trim();
    }

    const parser = document.createElement('div');
    parser.innerHTML = value;
    return (parser.textContent ?? '').trim();
}

function extractStringField(record: Record<string, unknown>, key: string): string {
    const value = record[key];
    if (typeof value === 'string') {
        return value;
    }
    if (value !== null && typeof value === 'object') {
        const envelope = value as { rendered?: unknown; raw?: unknown };
        if (typeof envelope.rendered === 'string') {
            return envelope.rendered;
        }
        if (typeof envelope.raw === 'string') {
            return envelope.raw;
        }
    }
    return '';
}

export default function RelatedPostsEdit({
    attributes,
    setAttributes,
    clientId,
}: RelatedPostsEditProps): ReactElement {
    const query = attributes.query ?? {};
    const displayLayout = attributes.displayLayout ?? {};

    const numPosts = clampPosts(
        typeof query.perPage === 'number' ? query.perPage : attributes.numPosts,
        3
    );
    const numColumns = clampColumns(
        typeof displayLayout.columns === 'number'
            ? displayLayout.columns
            : attributes.numColumns,
        1
    );
    const offset = clampOffset(query.offset);
    const order = query.order === 'asc' ? 'asc' : 'desc';
    const orderBy = typeof query.orderBy === 'string' ? query.orderBy : 'date';

    const [previewPosts, setPreviewPosts] = useState<ReadonlyArray<PreviewPost>>(
        []
    );

    useEffect(() => {
        if (attributes.queryId === clientId) {
            return;
        }
        setAttributes({ queryId: clientId });
        // eslint-disable-next-line react-hooks/exhaustive-deps
    }, [attributes.queryId, clientId]);

    useEffect(() => {
        const controller = new AbortController();
        const params = new URLSearchParams({
            per_page: String(numPosts),
            orderby: orderBy,
            order,
        });
        if (offset > 0) {
            params.set('offset', String(offset));
        }

        void fetch(`${API_BASE}/posts?${params.toString()}`, {
            credentials: 'same-origin',
            headers: { Accept: 'application/json' },
            signal: controller.signal,
        })
            .then((res) => (res.ok ? res.json() : null))
            .then((body) => {
                if (body === null) {
                    setPreviewPosts([]);
                    return;
                }
                const raw = Array.isArray(body)
                    ? body
                    : Array.isArray((body as { data?: unknown }).data)
                      ? ((body as { data: unknown[] }).data)
                      : [];
                const next: PreviewPost[] = [];
                for (const entry of raw) {
                    if (entry === null || typeof entry !== 'object') {
                        continue;
                    }
                    const record = entry as Record<string, unknown>;
                    const id = Number(record.id);
                    if (!Number.isFinite(id) || id <= 0) {
                        continue;
                    }
                    next.push({
                        id: Math.trunc(id),
                        title:
                            extractStringField(record, 'title') ||
                            (typeof record.slug === 'string'
                                ? record.slug
                                : `#${id}`),
                        excerpt: extractStringField(record, 'excerpt'),
                        date:
                            typeof record.date === 'string'
                                ? record.date
                                : '',
                    });
                }
                setPreviewPosts(next.slice(0, numPosts));
            })
            .catch((error) => {
                if ((error as { name?: string }).name !== 'AbortError') {
                    setPreviewPosts([]);
                }
            });

        return () => controller.abort();
    }, [numPosts, order, orderBy, offset]);

    const updateQuery = (changes: Partial<RelatedPostsQuery>): void => {
        setAttributes({ query: { ...query, ...changes } });
    };

    const updateLayout = (changes: Partial<RelatedPostsDisplayLayout>): void => {
        setAttributes({ displayLayout: { ...displayLayout, ...changes } });
    };

    const blockProps = useBlockProps({
        className: `ap-related-posts ap-related-posts-has-${numColumns}-columns`,
    });
    const innerBlocksProps = useInnerBlocksProps(
        { className: 'ap-related-posts__item ap-related-posts__item--editable' },
        { template: TEMPLATE }
    );

    const additionalPreview = previewPosts.slice(1);

    return (
        <>
            <InspectorControls>
                <PanelBody
                    title={__('Related posts settings', TEXT_DOMAIN)}
                    initialOpen
                >
                    <RangeControl
                        label={__('Number of related posts', TEXT_DOMAIN)}
                        value={numPosts}
                        onChange={(value) => {
                            const next = clampPosts(value, 3);
                            setAttributes({ numPosts: next });
                            updateQuery({ perPage: next });
                        }}
                        min={1}
                        max={10}
                        allowReset
                        resetFallbackValue={3}
                        __nextHasNoMarginBottom
                    />
                    <RangeControl
                        label={__('Columns', TEXT_DOMAIN)}
                        value={numColumns}
                        onChange={(value) => {
                            const next = clampColumns(value, 1);
                            setAttributes({ numColumns: next });
                            updateLayout({ columns: next });
                        }}
                        min={1}
                        max={4}
                        allowReset
                        resetFallbackValue={1}
                        __nextHasNoMarginBottom
                    />
                    <RangeControl
                        label={__('Offset', TEXT_DOMAIN)}
                        value={offset}
                        onChange={(value) =>
                            updateQuery({ offset: clampOffset(value) })
                        }
                        min={0}
                        max={50}
                        allowReset
                        resetFallbackValue={0}
                        __nextHasNoMarginBottom
                    />
                    <SelectControl
                        label={__('Order by', TEXT_DOMAIN)}
                        value={`${orderBy}/${order}`}
                        options={[
                            {
                                label: __('Newest to oldest', TEXT_DOMAIN),
                                value: 'date/desc',
                            },
                            {
                                label: __('Oldest to newest', TEXT_DOMAIN),
                                value: 'date/asc',
                            },
                            {
                                label: __('A → Z', TEXT_DOMAIN),
                                value: 'title/asc',
                            },
                            {
                                label: __('Z → A', TEXT_DOMAIN),
                                value: 'title/desc',
                            },
                        ]}
                        onChange={(value) => {
                            const [newOrderBy, newOrder] = value.split('/');
                            updateQuery({
                                orderBy: newOrderBy,
                                order: newOrder === 'asc' ? 'asc' : 'desc',
                            });
                        }}
                        __nextHasNoMarginBottom
                    />
                </PanelBody>
            </InspectorControls>
            <div {...blockProps}>
                <div {...innerBlocksProps} />
                {additionalPreview.map((post) => (
                    <article
                        key={post.id}
                        className="ap-related-posts__item ap-related-posts__item--preview"
                        aria-label={__(
                            'Read-only preview of an additional related post',
                            TEXT_DOMAIN
                        )}
                    >
                        <h3 className="ap-related-posts__preview-title">
                            {post.title}
                        </h3>
                        {post.date !== '' && (
                            <p className="ap-related-posts__preview-date">
                                {post.date.slice(0, 10)}
                            </p>
                        )}
                        {post.excerpt !== '' && (
                            <p className="ap-related-posts__preview-excerpt">
                                {stripHtml(post.excerpt)}
                            </p>
                        )}
                    </article>
                ))}
                {previewPosts.length === 0 && numPosts > 1 && (
                    <p className="ap-related-posts__preview-empty">
                        {__(
                            'Editor preview only shows the editable template above. The published page renders one entry per related post.',
                            TEXT_DOMAIN
                        )}
                    </p>
                )}
            </div>
        </>
    );
}
