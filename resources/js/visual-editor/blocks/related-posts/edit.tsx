/**
 * Related Posts — editor-side component (#501).
 *
 * Container block whose inner-block tree is cloned once per resolved
 * related post at render time. The canvas previews the inner tree once
 * against the host post in scope; server-side `QueryInliner` resolves N
 * related posts via the visual editor's `QueryResolverContract` and
 * re-stamps each clone through `PostResolver`.
 */

import type { ReactElement } from 'react';
import {
    InspectorControls,
    useBlockProps,
    useInnerBlocksProps,
} from '@wordpress/block-editor';
import { PanelBody, RangeControl } from '@wordpress/components';
import { __ } from '@wordpress/i18n';

import { TEXT_DOMAIN } from '../../vendor/i18n';

interface RelatedPostsAttributes {
    readonly numPosts: number;
    readonly numColumns: number;
}

interface RelatedPostsEditProps {
    readonly attributes: RelatedPostsAttributes;
    readonly setAttributes: (next: Partial<RelatedPostsAttributes>) => void;
}

const TEMPLATE: [string, Record<string, unknown>][] = [
    ['artisanpack/post-title', {}],
    ['artisanpack/post-date', {}],
    ['artisanpack/post-excerpt', {}],
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

export default function RelatedPostsEdit({
    attributes,
    setAttributes,
}: RelatedPostsEditProps): ReactElement {
    const numPosts = clampPosts(attributes.numPosts, 3);
    const numColumns = clampColumns(attributes.numColumns, 1);

    const blockProps = useBlockProps({
        className: `ap-related-posts ap-related-posts-has-${numColumns}-columns`,
    });
    const innerBlocksProps = useInnerBlocksProps(blockProps, {
        template: TEMPLATE,
    });

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
                        onChange={(value) =>
                            setAttributes({ numPosts: clampPosts(value, 3) })
                        }
                        min={1}
                        max={10}
                        allowReset
                        resetFallbackValue={3}
                        __nextHasNoMarginBottom
                    />
                    <RangeControl
                        label={__('Columns', TEXT_DOMAIN)}
                        value={numColumns}
                        onChange={(value) =>
                            setAttributes({ numColumns: clampColumns(value, 1) })
                        }
                        min={1}
                        max={4}
                        allowReset
                        resetFallbackValue={1}
                        __nextHasNoMarginBottom
                    />
                </PanelBody>
            </InspectorControls>
            <div {...innerBlocksProps} />
        </>
    );
}
