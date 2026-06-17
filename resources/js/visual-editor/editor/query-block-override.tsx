/**
 * Edit-component overrides for `core/query` and `core/post-template`.
 *
 * Both upstream Edits pull a heavy chain of selectors the M2 shim does
 * not implement:
 *
 *   - `core/query` calls `getTaxonomies`/`getEntityRecords` on the
 *     "post types" and "taxonomies" entities for its variation picker
 *     and toolbar.
 *   - `core/post-template` calls `getEntityRecords('postType', …)` to
 *     fetch the loop's post records, then renders an editable
 *     iteration per result. With the shim returning an empty list, it
 *     shows a "No posts found" placeholder and disables `<InnerBlocks>`.
 *
 * Rather than backfill all of those selectors just for the canvas
 * preview, this filter swaps both Edits with thin wrappers:
 *
 *   - `core/query` calls `/visual-editor/api/query/resolve` via the
 *     {@link useQueryPreview} hook and pipes the resolved record set
 *     down through a `BlockContextProvider` keyed by
 *     `artisanpack/queryPreview`. Descendant blocks
 *     (`core/post-template`, `core/query-pagination`, `core/query-title`,
 *     `core/query-no-results`, and the artisanpack mirrors) read this
 *     context to render against the real resolved data instead of
 *     placeholder values (#599).
 *   - `core/post-template` iterates the resolved posts: index 0 is an
 *     editable `<InnerBlocks />` (with a default `core/post-title`
 *     template) and indices 1..N are read-only ghosts wrapped in
 *     their own `BlockContextProvider` so inner `core/post-*` blocks
 *     resolve per-iteration via G3's entity adapter.
 *
 * Idempotent across HMR via a global Symbol guard.
 */

import { useEffect } from 'react';
import {
    BlockContextProvider,
    InnerBlocks,
    InspectorControls,
    useBlockProps,
} from '@wordpress/block-editor';
import { Notice, PanelBody } from '@wordpress/components';
import { addFilter } from '@wordpress/hooks';
import { __ } from '@wordpress/i18n';

import { TEXT_DOMAIN } from '../vendor/i18n';

import {
    QUERY_PREVIEW_CONTEXT_KEY,
    readQueryPreviewContext,
    type QueryPreviewContextValue,
} from './query-preview-context';
import { QueryPreviewIterations } from './query-preview-iterations';
import { useQueryPreview } from './use-query-preview';

const FILTER_HOOK = 'blocks.registerBlockType';
const FILTER_NAMESPACE = 'artisanpack-ui/visual-editor/core-query-edit';

const QUERY_BLOCK = 'core/query';
const POST_TEMPLATE_BLOCK = 'core/post-template';

const POST_TEMPLATE_DEFAULT_TEMPLATE: ReadonlyArray<[string]> = [
    ['core/post-title'],
];

const REGISTERED_KEY = Symbol.for(
    'artisanpack-ui.visual-editor.core-query-edit.registered'
);

interface GlobalSentinelHost {
    [REGISTERED_KEY]?: boolean;
}

interface BlockSettings {
    name?: string;
    edit?: unknown;
    usesContext?: ReadonlyArray<string>;
    [key: string]: unknown;
}

interface QueryEditProps {
    attributes: Record<string, unknown>;
    setAttributes: (changes: Record<string, unknown>) => void;
    clientId: string;
}

function QueryEdit({ attributes, setAttributes, clientId }: QueryEditProps): JSX.Element {
    const blockProps = useBlockProps();

    const queryFromAttrs =
        attributes.query !== null && typeof attributes.query === 'object' && !Array.isArray(attributes.query)
            ? (attributes.query as Record<string, unknown>)
            : {};

    // Stamp this block instance's `clientId` as `query.queryId` whenever
    // the persisted value differs (or is missing). Comparing against
    // `clientId` rather than just absence handles the duplicated-block
    // case: when a `core/query` is duplicated in the canvas, Gutenberg
    // assigns a fresh `clientId` but copies the source's attributes
    // verbatim — including its `queryId`. Without this rewrite, two
    // sibling blocks would both resolve to the same record set on the
    // public-render path. Done in `useEffect` rather than during render
    // so React does not see a setState during the render phase.
    const persistedQueryId =
        typeof queryFromAttrs.queryId === 'string' || typeof queryFromAttrs.queryId === 'number'
            ? String(queryFromAttrs.queryId)
            : null;

    useEffect(() => {
        if (persistedQueryId === clientId) {
            return;
        }

        setAttributes({ query: { ...queryFromAttrs, queryId: clientId } });
        // `queryFromAttrs` is captured by reference for the next setAttributes
        // call only — the effect intentionally re-runs when queryId
        // disagrees with clientId (or vice versa) rather than on every
        // attribute touch, hence the targeted dep list.
        // eslint-disable-next-line react-hooks/exhaustive-deps
    }, [persistedQueryId, clientId]);

    const preview = useQueryPreview(queryFromAttrs);

    const postType =
        typeof queryFromAttrs.postType === 'string' && queryFromAttrs.postType !== ''
            ? queryFromAttrs.postType
            : 'post';

    // Default mirrors the first-party `artisanpack/query` block's
    // `perPage: 5` so the iteration cap + pagination-numbers preview
    // get a sensible value when the saved tree omits the attribute.
    // A zero would skip pagination-numbers computation entirely (the
    // descendants guard on `perPage <= 0`), but the override is supposed
    // to behave like a configured query in the canvas.
    const perPage = typeof queryFromAttrs.perPage === 'number' ? queryFromAttrs.perPage : 5;

    // Pipe the resolved record set + paginator state down to
    // descendants via block context. `post-template` iterates against
    // `posts`; `query-pagination` reads `total` + `currentPage` +
    // `perPage`; `query-title` reads `queryTitle`. The canvas always
    // previews page 1 — pagination is not interactive in the editor by
    // design (issue #599 scope).
    const queryPreviewContext: QueryPreviewContextValue = {
        posts: preview.status === 'ready' ? preview.posts : [],
        total: preview.total,
        currentPage: 1,
        queryTitle: '',
        perPage,
        status: preview.status,
    };

    const blockContext = {
        postType,
        [ QUERY_PREVIEW_CONTEXT_KEY ]: queryPreviewContext,
    };

    return (
        <div {...blockProps}>
            <InspectorControls>
                <PanelBody title={__('Query preview', TEXT_DOMAIN)}>
                    <PreviewStatus preview={preview} />
                </PanelBody>
            </InspectorControls>
            <BlockContextProvider value={blockContext}>
                <InnerBlocks />
            </BlockContextProvider>
        </div>
    );
}

interface PreviewSummaryProps {
    preview: ReturnType<typeof useQueryPreview>;
}

function PreviewStatus({ preview }: PreviewSummaryProps): JSX.Element {
    if (preview.status === 'loading') {
        return <p>{__('Loading preview…', TEXT_DOMAIN)}</p>;
    }

    if (preview.status === 'error') {
        return (
            <Notice status="error" isDismissible={false}>
                {preview.error ?? __('Preview failed.', TEXT_DOMAIN)}
            </Notice>
        );
    }

    if (preview.status === 'ready') {
        return (
            <p>
                {__('Posts matched:', TEXT_DOMAIN)} <strong>{preview.total}</strong>
            </p>
        );
    }

    return <p>{__('Configure the query to see a preview.', TEXT_DOMAIN)}</p>;
}

interface PostTemplateEditProps {
    clientId: string;
    context?: Record<string, unknown>;
}

function PostTemplateEdit({ clientId, context }: PostTemplateEditProps): JSX.Element {
    const blockProps = useBlockProps({ className: 'wp-block-post-template' });

    const previewValue = readQueryPreviewContext(context);
    const postType = typeof context?.postType === 'string' && context.postType !== ''
        ? context.postType
        : 'post';

    return (
        <QueryPreviewIterations
            clientId={ clientId }
            preview={ previewValue }
            postType={ postType }
            defaultTemplate={ POST_TEMPLATE_DEFAULT_TEMPLATE }
            outerProps={ blockProps as Record<string, unknown> }
        />
    );
}

function overrideEdit(settings: BlockSettings, name: string): BlockSettings {
    if (name === QUERY_BLOCK) {
        // Strip upstream variations + transforms from the block's settings.
        // The variations include `core/query`'s "Posts", "Pages", and other
        // pre-configured patterns whose previews render via toolbar / picker
        // chrome that calls `getTaxonomies({per_page: -1})` on mount —
        // selectors the M2 shim does not implement, so the click crashes.
        // Variations + transforms are conveniences for the editor UI and
        // are not part of the saved block shape, so dropping them is
        // attribute-compatible.
        return {
            ...settings,
            edit: QueryEdit,
            variations: [],
            transforms: undefined,
            __experimentalLabel: undefined,
        };
    }

    if (name === POST_TEMPLATE_BLOCK) {
        // Extend upstream `usesContext` with the `artisanpack/queryPreview`
        // key so the override Edit receives the resolved record set its
        // iteration loop needs. `postType` is already on the upstream
        // declaration; appending guards against future upstream additions
        // we'd otherwise drop.
        const upstreamUsesContext = Array.isArray( settings.usesContext )
            ? settings.usesContext
            : [];
        const usesContext = upstreamUsesContext.includes( QUERY_PREVIEW_CONTEXT_KEY )
            ? upstreamUsesContext
            : [ ...upstreamUsesContext, QUERY_PREVIEW_CONTEXT_KEY ];

        return {
            ...settings,
            edit: PostTemplateEdit,
            usesContext,
        };
    }

    return settings;
}

export function registerCoreQueryBlockOverride(): void {
    const host = globalThis as unknown as GlobalSentinelHost;

    if (host[REGISTERED_KEY] === true) {
        return;
    }

    addFilter(FILTER_HOOK, FILTER_NAMESPACE, overrideEdit);
    host[REGISTERED_KEY] = true;
}
