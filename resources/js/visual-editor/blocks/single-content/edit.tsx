/**
 * Single Content — editor-side component (#501).
 *
 * Container block that scopes its inner-block tree to one specific entry.
 * The author picks the entry via a searchable post dropdown backed by the
 * visual editor's `/posts` (or `/{postType}`) REST surface; the canvas
 * previews the inner blocks against the picked entry (or, with no entry
 * picked, whatever post the host has in scope). Server-side `QueryInliner` resolves the entry through the visual
 * editor's `QueryResolverContract` and re-stamps the inner-block tree
 * against the resolved post before the renderers walk it.
 *
 * The picked entry is resolved through the same `query/resolve` endpoint
 * the query block previews with, batched across every single-content
 * block on the page (one request per content type), and piped to the
 * inner blocks as `artisanpack/postPreview` context. The picker only
 * mounts while the block is selected (#1003).
 */

import type { ReactElement } from 'react';
import { useEffect, useMemo, useState } from '@wordpress/element';
import {
    BlockContextProvider,
    InspectorControls,
    useBlockProps,
    useInnerBlocksProps,
} from '@wordpress/block-editor';
import { PanelBody, TextControl } from '@wordpress/components';
import { __ } from '@wordpress/i18n';

import { TEXT_DOMAIN } from '../../vendor/i18n';
import { resolveCollection, resolvePostType } from '../../editor/content-type-slugs';
import type { QueryPreviewPost } from '../../editor/use-query-preview';
import { PREVIEW_CONTEXT_KEY } from '../_shared/entity-placeholder-edit';
import RecordPicker from './record-picker';
import { fetchRecordPreview } from './records-cache';

interface SingleContentAttributes {
    readonly postId: number;
    readonly postType: string;
}

interface SingleContentEditProps {
    readonly attributes: SingleContentAttributes;
    readonly setAttributes: (next: Partial<SingleContentAttributes>) => void;
    readonly isSelected?: boolean;
}

const TEMPLATE: [string, Record<string, unknown>][] = [
    ['artisanpack/post-title', {}],
    ['artisanpack/post-content', {}],
];

/**
 * Resolve the picked entry for the canvas preview. Returns `null` while
 * loading, when no entry is picked, or when the resolver doesn't return
 * the record — the inner blocks then fall back to their own previews.
 */
function useRecordPreview(postType: string, postId: number): QueryPreviewPost | null {
    const [preview, setPreview] = useState<QueryPreviewPost | null>(null);

    useEffect(() => {
        setPreview(null);

        if (!postId) {
            return;
        }

        let cancelled = false;

        fetchRecordPreview(postType, postId)
            .then((next) => {
                if (!cancelled) {
                    setPreview(next);
                }
            })
            .catch(() => {
                /* swallowed — inner blocks keep their fallback previews */
            });

        return () => {
            cancelled = true;
        };
    }, [postType, postId]);

    return preview;
}

export default function SingleContentEdit({
    attributes,
    setAttributes,
    isSelected = false,
}: SingleContentEditProps): ReactElement {
    const { postId, postType } = attributes;
    const preview = useRecordPreview(resolvePostType(postType), postId);

    // With an entry picked, always set the key — `null` while loading or
    // unresolved — so a parent query loop's preview can't leak into the
    // inner blocks. With no entry picked, the inner blocks keep whatever
    // post the surrounding context has in scope.
    const previewContext = useMemo(
        () => (postId ? { [PREVIEW_CONTEXT_KEY]: preview } : {}),
        [postId, preview]
    );

    const blockProps = useBlockProps({ className: 'ap-single-content' });
    const { children, ...innerBlocksProps } = useInnerBlocksProps(blockProps, {
        template: TEMPLATE,
    });

    return (
        <>
            <InspectorControls>
                <PanelBody
                    title={__('Single content settings', TEXT_DOMAIN)}
                    initialOpen
                >
                    {isSelected && (
                        <RecordPicker
                            collection={resolveCollection(postType)}
                            postId={postId}
                            knownLabel={
                                preview !== null && preview.id === postId && preview.title
                                    ? preview.title
                                    : null
                            }
                            onChange={(next) => setAttributes({ postId: next })}
                        />
                    )}
                    <TextControl
                        label={__('Post type', TEXT_DOMAIN)}
                        help={__(
                            'Resource slug to query. Defaults to "post".',
                            TEXT_DOMAIN
                        )}
                        value={postType}
                        onChange={(value) =>
                            setAttributes({
                                postType: value.trim() || 'post',
                                postId: 0,
                            })
                        }
                        __nextHasNoMarginBottom
                    />
                </PanelBody>
            </InspectorControls>
            <div {...innerBlocksProps}>
                <BlockContextProvider value={previewContext}>
                    {children}
                </BlockContextProvider>
            </div>
        </>
    );
}
