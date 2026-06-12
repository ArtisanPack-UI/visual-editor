/**
 * Single Content — editor-side component (#501).
 *
 * Container block that scopes its inner-block tree to one specific entry.
 * The author picks the entry id + type from the inspector; the canvas
 * previews the inner blocks against whatever post the host has in scope.
 * Server-side `QueryInliner` resolves the entry through the visual
 * editor's `QueryResolverContract` and re-stamps the inner-block tree
 * against the resolved post before the renderers walk it.
 */

import type { ReactElement } from 'react';
import {
    InspectorControls,
    useBlockProps,
    useInnerBlocksProps,
} from '@wordpress/block-editor';
import { PanelBody, TextControl } from '@wordpress/components';
import { __ } from '@wordpress/i18n';

import { TEXT_DOMAIN } from '../../vendor/i18n';

interface SingleContentAttributes {
    readonly postId: number;
    readonly postType: string;
}

interface SingleContentEditProps {
    readonly attributes: SingleContentAttributes;
    readonly setAttributes: (next: Partial<SingleContentAttributes>) => void;
}

const TEMPLATE: [string, Record<string, unknown>][] = [
    ['artisanpack/post-title', {}],
    ['artisanpack/post-content', {}],
];

function clampPostId(value: number | string | undefined): number {
    const parsed = typeof value === 'number' ? value : Number(value);
    if (!Number.isFinite(parsed) || parsed < 0) {
        return 0;
    }
    return Math.trunc(parsed);
}

export default function SingleContentEdit({
    attributes,
    setAttributes,
}: SingleContentEditProps): ReactElement {
    const { postId, postType } = attributes;

    const blockProps = useBlockProps({ className: 'ap-single-content' });
    const innerBlocksProps = useInnerBlocksProps(blockProps, {
        template: TEMPLATE,
    });

    return (
        <>
            <InspectorControls>
                <PanelBody
                    title={__('Single content settings', TEXT_DOMAIN)}
                    initialOpen
                >
                    <TextControl
                        label={__('Post ID', TEXT_DOMAIN)}
                        help={__(
                            'ID of the entry to render. Leave at 0 to render the host post in scope.',
                            TEXT_DOMAIN
                        )}
                        value={postId ? String(postId) : ''}
                        onChange={(value) =>
                            setAttributes({ postId: clampPostId(value) })
                        }
                        __nextHasNoMarginBottom
                    />
                    <TextControl
                        label={__('Post type', TEXT_DOMAIN)}
                        help={__(
                            'Resource slug to query. Defaults to "post".',
                            TEXT_DOMAIN
                        )}
                        value={postType}
                        onChange={(value) =>
                            setAttributes({ postType: value.trim() || 'post' })
                        }
                        __nextHasNoMarginBottom
                    />
                </PanelBody>
            </InspectorControls>
            <div {...innerBlocksProps} />
        </>
    );
}
