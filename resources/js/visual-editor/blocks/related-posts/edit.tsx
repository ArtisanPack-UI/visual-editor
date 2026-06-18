/**
 * Related Posts — editor-side component (#501, #601).
 *
 * Mirrors `artisanpack/query` for the WYSIWYG canvas: the saved tree
 * nests an `artisanpack/post-template` whose inner blocks are the
 * per-post iteration template (post-title, post-date, etc.). The query
 * preview hook resolves N related posts via the editor's
 * `/visual-editor/api/query/resolve` endpoint using the `relatedTo`
 * shortcut, and that result set is piped down through the
 * `artisanpack/queryPreview` block context so the nested post-template
 * renders one editable iteration plus N read-only ghosts via
 * `<QueryPreviewIterations>` — same code path Query Loop uses, so
 * variants, grid spans, and masonry packing all work uniformly.
 *
 * Pre-#601 saved content (flat inner blocks with no post-template
 * wrapper) is handled server-side by the QueryInliner's legacy
 * expansion branch — see `expandRelatedPosts()` for the
 * backward-compat path.
 */

import type { ReactElement } from 'react';
import { useEffect, useMemo } from '@wordpress/element';
import {
    BlockContextProvider,
    InnerBlocks,
    InspectorControls,
    useBlockProps,
} from '@wordpress/block-editor';
import {
    Notice,
    PanelBody,
    RangeControl,
    SelectControl,
} from '@wordpress/components';
import { __ } from '@wordpress/i18n';

import {
    QUERY_PREVIEW_CONTEXT_KEY,
    type QueryPreviewContextValue,
} from '../../editor/query-preview-context';
import { useQueryPreview } from '../../editor/use-query-preview';
import { TEXT_DOMAIN } from '../../vendor/i18n';
import PostVariantsPanel from '../query/post-variants-panel';

interface RelatedPostsQuery {
    readonly perPage?: number;
    readonly offset?: number;
    readonly order?: 'asc' | 'desc';
    readonly orderBy?: string;
    readonly postType?: string;
    readonly inherit?: boolean;
}

interface RelatedPostsDisplayLayout {
    readonly type?: 'list' | 'grid' | 'masonry' | string;
    readonly columns?: number;
}

interface RelatedPostsAttributes {
    readonly numPosts: number;
    readonly numColumns: number;
    readonly queryId?: string;
    readonly query?: RelatedPostsQuery;
    readonly displayLayout?: RelatedPostsDisplayLayout;
    readonly enhancedPagination?: boolean;
}

interface RelatedPostsEditProps {
    readonly attributes: RelatedPostsAttributes;
    readonly setAttributes: (next: Partial<RelatedPostsAttributes>) => void;
    readonly clientId: string;
    readonly context?: Record<string, unknown>;
}

// Seed every newly-inserted related-posts block with a post-template
// so authors land on the WYSIWYG iteration path immediately rather
// than discovering they need to add the wrapper by hand. Matches
// Query Loop's pattern — the nested post-template seeds its own
// `post-title` default through QueryPreviewIterations, so authors get
// a working preview from the first click; date/excerpt/etc. are added
// inside the post-template the same way as Query Loop. Keeping this
// minimal also guarantees the Post Variants panel's "clone the base
// children" seeding sees a stable tree (one post-title) on first
// "Add variant" click — a richer nested template here would race the
// post-template's own mount and produce empty variant clones.
// The `template` only applies on first mount, so it does not overwrite
// an existing user-arranged tree (pre-#601 flat saves continue to
// render through the QueryInliner's legacy expansion branch).
const DEFAULT_TEMPLATE: ReadonlyArray<[string]> = [
    ['artisanpack/post-template'],
];

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

function clampOffset(value: number | undefined): number {
    const next =
        typeof value === 'number' && Number.isFinite(value)
            ? Math.trunc(value)
            : 0;
    return next < 0 ? 0 : next;
}

function readHostPostId(context?: Record<string, unknown>): number {
    if (!context) {
        return 0;
    }
    const value = context.postId;
    if (typeof value === 'number' && Number.isFinite(value)) {
        return Math.trunc(value);
    }
    if (typeof value === 'string' && value !== '') {
        const parsed = Number(value);
        return Number.isFinite(parsed) ? Math.trunc(parsed) : 0;
    }
    return 0;
}

function readHostPostType(context?: Record<string, unknown>): string {
    if (!context) {
        return 'post';
    }
    const value = context.postType;
    return typeof value === 'string' && value !== '' ? value : 'post';
}

export default function RelatedPostsEdit({
    attributes,
    setAttributes,
    clientId,
    context,
}: RelatedPostsEditProps): ReactElement {
    const query = attributes.query ?? {};

    const numPosts = clampPosts(
        typeof query.perPage === 'number' ? query.perPage : attributes.numPosts,
        3
    );
    const offset = clampOffset(query.offset);
    const order = query.order === 'asc' ? 'asc' : 'desc';
    const orderBy = typeof query.orderBy === 'string' ? query.orderBy : 'date';

    const hostPostId = readHostPostId(context);
    const hostPostType = readHostPostType(context);

    useEffect(() => {
        if (attributes.queryId === clientId) {
            return;
        }
        setAttributes({ queryId: clientId });
        // eslint-disable-next-line react-hooks/exhaustive-deps
    }, [attributes.queryId, clientId]);

    // Build the resolver payload via the `relatedTo` shortcut. When
    // no host post is in editor scope (e.g. inside a template editor
    // without a preview post selected), skip the preview entirely and
    // surface a Notice so authors know the canvas can't show real
    // matches yet.
    const previewQuery = useMemo(() => {
        if (hostPostId === 0) {
            return null;
        }
        return {
            relatedTo: hostPostId,
            postType: hostPostType,
            perPage: numPosts,
            offset,
            order,
            orderBy,
        };
    }, [hostPostId, hostPostType, numPosts, offset, order, orderBy]);

    const preview = useQueryPreview(previewQuery);

    const queryPreviewContext: QueryPreviewContextValue = {
        posts: preview.status === 'ready' ? preview.posts : [],
        total: preview.total,
        currentPage: 1,
        queryTitle: '',
        perPage: numPosts,
        status: preview.status,
    };

    const blockContext = {
        postType: hostPostType,
        [QUERY_PREVIEW_CONTEXT_KEY]: queryPreviewContext,
    };

    const updateQuery = (changes: Partial<RelatedPostsQuery>): void => {
        setAttributes({ query: { ...query, ...changes } });
    };

    const blockProps = useBlockProps({
        className: 'ap-related-posts',
    });

    const showHostMissingNotice = hostPostId === 0;
    const showZeroResultNotice =
        hostPostId !== 0 && preview.status === 'ready' && preview.total === 0;

    return (
        <>
            <InspectorControls>
                <PanelBody
                    title={__('Related posts settings', TEXT_DOMAIN)}
                    initialOpen
                >
                    <RangeControl
                        // @ts-expect-error - upstream prop
                        __nextHasNoMarginBottom
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
                    />
                    <RangeControl
                        // @ts-expect-error - upstream prop
                        __nextHasNoMarginBottom
                        label={__('Offset', TEXT_DOMAIN)}
                        value={offset}
                        onChange={(value) =>
                            updateQuery({ offset: clampOffset(value) })
                        }
                        min={0}
                        max={50}
                        allowReset
                        resetFallbackValue={0}
                    />
                    <SelectControl
                        // @ts-expect-error - upstream prop
                        __nextHasNoMarginBottom
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
                    />
                </PanelBody>
                <PostVariantsPanel
                    queryClientId={clientId}
                    previewTotal={
                        preview.status === 'ready' ? preview.total : 0
                    }
                />
            </InspectorControls>
            <div {...blockProps}>
                {showHostMissingNotice && (
                    <Notice status="info" isDismissible={false}>
                        {__(
                            'Select a preview post to see related-posts matches in the canvas. The published page resolves matches against each visitor’s host entry.',
                            TEXT_DOMAIN
                        )}
                    </Notice>
                )}
                {showZeroResultNotice && (
                    <Notice status="warning" isDismissible={false}>
                        {__(
                            'No related posts matched the host entry’s primary taxonomy. The editable iteration below is shown so you can keep editing the template.',
                            TEXT_DOMAIN
                        )}
                    </Notice>
                )}
                <BlockContextProvider value={blockContext}>
                    <InnerBlocks template={[...DEFAULT_TEMPLATE]} />
                </BlockContextProvider>
            </div>
        </>
    );
}
