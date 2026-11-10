/**
 * Quote — editor-side render.
 *
 * Ported from `@wordpress/block-library/src/quote/edit.js` (v9.43.0).
 * Behaviour parity with upstream: inner-block paragraph template, citation
 * RichText, alignment control. The upstream `useMigrateOnLoad` hook is
 * NOT ported here — the deprecation chain handles legacy `value` →
 * inner-blocks migration via `migrateToQuoteV2` on block load instead.
 */

import type { ReactElement } from 'react';
import clsx from 'clsx';
import { __ } from '@wordpress/i18n';
import { TEXT_DOMAIN } from '../../vendor/i18n';
import {
    AlignmentControl,
    BlockControls,
    RichText,
    useBlockProps,
    useInnerBlocksProps,
    store as blockEditorStore,
} from '@wordpress/block-editor';
import { useSelect } from '@wordpress/data';

interface QuoteAttributes {
    readonly textAlign?: string;
    readonly citation?: string;
    readonly value?: string;
    readonly allowedBlocks?: string[];
}

interface QuoteEditProps {
    readonly attributes: QuoteAttributes;
    readonly setAttributes: (next: Partial<QuoteAttributes>) => void;
    readonly clientId: string;
    readonly className?: string;
}

export const TEMPLATE: ReadonlyArray<readonly [string, Record<string, unknown>]> = [
    ['artisanpack/paragraph', {}],
];

export default function QuoteEdit({
    attributes,
    setAttributes,
    className,
    clientId,
}: QuoteEditProps): ReactElement {
    const { textAlign, citation, allowedBlocks } = attributes;

    // An empty citation is held back until the template paragraph exists
    // (#1105). A freshly inserted quote is selected before its template
    // paragraph is inserted, so the block's focus-first-element effect
    // would otherwise land the caret in the empty citation. With no text
    // field to focus, the template insert moves the caret into the
    // paragraph instead, matching upstream `core/quote`. A citation that
    // already has content always shows.
    const hasInnerBlocks = useSelect(
        (select) =>
            (
                select(blockEditorStore) as {
                    getBlockCount: (rootClientId: string) => number;
                }
            ).getBlockCount(clientId) > 0,
        [clientId]
    );

    const blockProps = useBlockProps({
        className: clsx(className, {
            [`has-text-align-${textAlign}`]: textAlign,
        }),
    });

    const innerBlocksProps = useInnerBlocksProps(blockProps, {
        template: TEMPLATE as unknown as readonly [string, Record<string, unknown>][],
        templateInsertUpdatesSelection: true,
        renderAppender: false,
        allowedBlocks,
    });

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
            <blockquote {...innerBlocksProps}>
                {(innerBlocksProps as { children?: React.ReactNode }).children}
                {(hasInnerBlocks || !RichText.isEmpty(citation)) && (
                    <RichText
                        identifier="citation"
                        tagName="cite"
                        style={{ display: 'block' }}
                        value={citation ?? ''}
                        onChange={(nextCitation: string) =>
                            setAttributes({ citation: nextCitation })
                        }
                        placeholder={__('Add citation', TEXT_DOMAIN)}
                        className="wp-block-quote__citation"
                    />
                )}
            </blockquote>
        </>
    );
}
