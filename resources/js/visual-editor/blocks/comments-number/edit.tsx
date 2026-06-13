/**
 * Comments Number — editor-side preview.
 *
 * Shows a stub `0 {plural}` line so authors get an immediate visual of
 * the label they're configuring. The real count comes from
 * `PostResolver` at render time and is combined with the saved labels
 * by the Blade / React / Vue renderers.
 */

import type { ReactElement } from 'react';
import { InspectorControls, useBlockProps } from '@wordpress/block-editor';
import { PanelBody, TextControl } from '@wordpress/components';
import { __ } from '@wordpress/i18n';

import { TEXT_DOMAIN } from '../../vendor/i18n';

interface CommentsNumberAttributes {
    readonly singularCommentText: string;
    readonly pluralCommentText: string;
}

interface CommentsNumberEditProps {
    readonly attributes: CommentsNumberAttributes;
    readonly setAttributes: (next: Partial<CommentsNumberAttributes>) => void;
}

export default function CommentsNumberEdit({
    attributes,
    setAttributes,
}: CommentsNumberEditProps): ReactElement {
    const { singularCommentText, pluralCommentText } = attributes;

    const blockProps = useBlockProps({ className: 'ap-comments-number' });

    const previewLabel = pluralCommentText.trim() !== ''
        ? pluralCommentText
        : __('Comments', TEXT_DOMAIN);

    return (
        <>
            <InspectorControls>
                <PanelBody
                    title={__('Comments number settings', TEXT_DOMAIN)}
                    initialOpen
                >
                    <TextControl
                        label={__('Singular label', TEXT_DOMAIN)}
                        value={singularCommentText}
                        onChange={(value) =>
                            setAttributes({ singularCommentText: value })
                        }
                        help={__('Shown when the post has exactly one comment.', TEXT_DOMAIN)}
                        __nextHasNoMarginBottom
                    />
                    <TextControl
                        label={__('Plural label', TEXT_DOMAIN)}
                        value={pluralCommentText}
                        onChange={(value) =>
                            setAttributes({ pluralCommentText: value })
                        }
                        help={__('Shown when the post has zero or multiple comments.', TEXT_DOMAIN)}
                        __nextHasNoMarginBottom
                    />
                </PanelBody>
            </InspectorControls>
            <p {...blockProps}>{`0 ${previewLabel}`}</p>
        </>
    );
}
