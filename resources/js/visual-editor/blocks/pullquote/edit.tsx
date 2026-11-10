/**
 * Pullquote — editor-side render.
 *
 * Ported from `@wordpress/block-library/src/pullquote/edit.js` (v9.43.0).
 * Unlike upstream, Enter at the end of the quote text also leaves the block
 * (as it already does at the end of the citation), so the caret continues
 * in a new paragraph below (#1101). Shift+Enter still adds a line break.
 * Both exits are only wired when a default block can actually be inserted
 * after this one; otherwise Enter keeps RichText's default behavior.
 */

import type { ReactElement } from 'react';
import clsx from 'clsx';
import { __ } from '@wordpress/i18n';
import { TEXT_DOMAIN } from '../../vendor/i18n';
import {
    AlignmentControl,
    BlockControls,
    RichText,
    store as blockEditorStore,
    useBlockProps,
} from '@wordpress/block-editor';
import { createBlock, getDefaultBlockName } from '@wordpress/blocks';
import { useSelect } from '@wordpress/data';

interface PullquoteAttributes {
    readonly textAlign?: string;
    readonly citation?: string;
    readonly value?: string;
}

interface PullquoteEditProps {
    readonly attributes: PullquoteAttributes;
    readonly setAttributes: (next: Partial<PullquoteAttributes>) => void;
    readonly isSelected: boolean;
    readonly clientId: string;
    readonly insertBlocksAfter?: (block: unknown) => void;
}

interface InsertionSelectors {
    canInsertBlockType(blockName: string, rootClientId: string | null): boolean;
    getBlockRootClientId(clientId: string): string | null;
}

/**
 * Renders the pullquote's text and citation RichTexts with the alignment
 * toolbar.
 *
 * @param props Block edit props.
 *
 * @return The editor-side pullquote.
 */
export default function PullquoteEdit({
    attributes,
    setAttributes,
    isSelected,
    clientId,
    insertBlocksAfter,
}: PullquoteEditProps): ReactElement {
    const { textAlign, citation, value } = attributes;
    const blockProps = useBlockProps({
        className: clsx({
            [`has-text-align-${textAlign}`]: textAlign,
        }),
    });
    const shouldShowCitation = !RichText.isEmpty(citation) || isSelected;
    // RichText consumes Enter before calling `__unstableOnSplitAtEnd`, so the
    // handler is only passed when it can actually insert a block (#1101).
    const canExit = useSelect(
        (select) => {
            const defaultName = getDefaultBlockName();

            if (!insertBlocksAfter || !defaultName) {
                return false;
            }

            const { canInsertBlockType, getBlockRootClientId } = select(
                blockEditorStore
            ) as unknown as InsertionSelectors;

            return canInsertBlockType(
                defaultName,
                getBlockRootClientId(clientId)
            );
        },
        [clientId, insertBlocksAfter]
    );
    const exitToDefaultBlock = canExit
        ? (): void => {
              insertBlocksAfter?.(createBlock(getDefaultBlockName() as string));
          }
        : undefined;

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
                        aria-label={__('Pullquote text', TEXT_DOMAIN)}
                        placeholder={__('Add quote', TEXT_DOMAIN)}
                        __unstableOnSplitAtEnd={exitToDefaultBlock}
                    />
                    {shouldShowCitation && (
                        <RichText
                            identifier="citation"
                            tagName="cite"
                            style={{ display: 'block' }}
                            value={citation ?? ''}
                            aria-label={__('Pullquote citation text', TEXT_DOMAIN)}
                            placeholder={__('Add citation', TEXT_DOMAIN)}
                            onChange={(nextCitation: string) =>
                                setAttributes({ citation: nextCitation })
                            }
                            className="wp-block-pullquote__citation"
                            __unstableOnSplitAtEnd={exitToDefaultBlock}
                        />
                    )}
                </blockquote>
            </figure>
        </>
    );
}
