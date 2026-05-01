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
 *     {@link useQueryPreview} hook, pushes the first result's `postId`
 *     into a `BlockContextProvider`, and renders `<InnerBlocks />` so
 *     the editable `core/post-template` shell renders inside.
 *   - `core/post-template` ignores its upstream entity-records call
 *     and just renders `<InnerBlocks />` with a default
 *     `core/post-title` template, so users can build the per-iteration
 *     layout. The wrapping `BlockContextProvider` from `core/query`
 *     means inner `core/post-*` blocks resolve against the right
 *     post via G3's entity adapter.
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

    // Stamp a queryId so the inliner can match this block's saved
    // attributes to its resolved record set on the public-render path.
    // Done in `useEffect` rather than during render so React does not
    // see a setState during the render phase (which logs a warning and
    // schedules an extra rerender) and so the call is skipped if the
    // component unmounts before the effect runs.
    const hasQueryId =
        typeof queryFromAttrs.queryId === 'string' || typeof queryFromAttrs.queryId === 'number';

    useEffect(() => {
        if (hasQueryId) {
            return;
        }

        setAttributes({ query: { ...queryFromAttrs, queryId: clientId } });
        // `queryFromAttrs` is captured by reference for the next setAttributes
        // call only — the effect intentionally re-runs when queryId presence
        // flips (e.g. a host nukes the value) rather than on every attribute
        // touch, hence the targeted dep list.
        // eslint-disable-next-line react-hooks/exhaustive-deps
    }, [hasQueryId, clientId]);

    const preview = useQueryPreview(queryFromAttrs);

    const postType =
        typeof queryFromAttrs.postType === 'string' && queryFromAttrs.postType !== ''
            ? queryFromAttrs.postType
            : 'post';

    const firstPost = preview.posts[0];
    const blockContext =
        firstPost === undefined
            ? { postType }
            : { postType, postId: firstPost.id };

    return (
        <div {...blockProps}>
            <InspectorControls>
                <PanelBody title={__('Query preview', TEXT_DOMAIN)}>
                    <PreviewStatus preview={preview} />
                </PanelBody>
            </InspectorControls>
            <PreviewBanner preview={preview} />
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

function PreviewBanner({ preview }: PreviewSummaryProps): JSX.Element | null {
    if (preview.status !== 'ready') {
        return null;
    }

    if (preview.total === 0) {
        return (
            <Notice status="warning" isDismissible={false}>
                {__('No posts matched the current query.', TEXT_DOMAIN)}
            </Notice>
        );
    }

    if (preview.total === 1) {
        return null;
    }

    return (
        <Notice status="info" isDismissible={false}>
            {__(
                'The canvas previews the first matching post. The saved page renders all matching posts.',
                TEXT_DOMAIN
            )}
        </Notice>
    );
}

function PostTemplateEdit(): JSX.Element {
    const blockProps = useBlockProps({ className: 'wp-block-post-template' });

    return (
        <div {...blockProps}>
            <InnerBlocks template={[...POST_TEMPLATE_DEFAULT_TEMPLATE]} />
        </div>
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
        return {
            ...settings,
            edit: PostTemplateEdit,
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
