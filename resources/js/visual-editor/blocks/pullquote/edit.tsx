/**
 * Pullquote — editor-side render.
 *
 * Ported from `@wordpress/block-library/src/pullquote/edit.js` (v9.43.0).
 */

import type { ReactElement } from 'react';
import clsx from 'clsx';
import { __ } from '@wordpress/i18n';
import {
    AlignmentControl,
    BlockControls,
    RichText,
    useBlockProps,
} from '@wordpress/block-editor';
import { createBlock, getDefaultBlockName } from '@wordpress/blocks';

interface PullquoteAttributes {
    readonly textAlign?: string;
    readonly citation?: string;
    readonly value?: string;
}

interface PullquoteEditProps {
    readonly attributes: PullquoteAttributes;
    readonly setAttributes: (next: Partial<PullquoteAttributes>) => void;
    readonly isSelected: boolean;
    readonly insertBlocksAfter?: (block: unknown) => void;
}

export default function PullquoteEdit({
    attributes,
    setAttributes,
    isSelected,
    insertBlocksAfter,
}: PullquoteEditProps): ReactElement {
    const { textAlign, citation, value } = attributes;
    const blockProps = useBlockProps({
        className: clsx({
            [`has-text-align-${textAlign}`]: textAlign,
        }),
    });
    const shouldShowCitation = !RichText.isEmpty(citation) || isSelected;

    return (
        <>
            <BlockControls group="block">
                <AlignmentControl
                    value={textAlign}
                    onChange={(nextAlign?: string) => {
                        setAttributes({ textAlign: nextAlign });
                    }}
                />
            </BlockControls>
            <figure {...blockProps}>
                <blockquote>
                    <RichText
                        identifier="value"
                        tagName="p"
                        value={value ?? ''}
                        onChange={(nextValue: string) =>
                            setAttributes({ value: nextValue })
                        }
                        aria-label={__('Pullquote text')}
                        placeholder={__('Add quote')}
                    />
                    {shouldShowCitation && (
                        <RichText
                            identifier="citation"
                            tagName="cite"
                            style={{ display: 'block' }}
                            value={citation ?? ''}
                            aria-label={__('Pullquote citation text')}
                            placeholder={__('Add citation')}
                            onChange={(nextCitation: string) =>
                                setAttributes({ citation: nextCitation })
                            }
                            className="wp-block-pullquote__citation"
                            __unstableOnSplitAtEnd={() => {
                                const defaultName = getDefaultBlockName();
                                if (defaultName) {
                                    insertBlocksAfter?.(createBlock(defaultName));
                                }
                            }}
                        />
                    )}
                </blockquote>
            </figure>
        </>
    );
}
