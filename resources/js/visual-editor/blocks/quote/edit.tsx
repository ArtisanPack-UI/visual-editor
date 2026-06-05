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
import {
    AlignmentControl,
    BlockControls,
    RichText,
    useBlockProps,
    useInnerBlocksProps,
} from '@wordpress/block-editor';

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

const TEMPLATE: ReadonlyArray<readonly [string, Record<string, unknown>]> = [
    ['core/paragraph', {}],
];

export default function QuoteEdit({
    attributes,
    setAttributes,
    className,
}: QuoteEditProps): ReactElement {
    const { textAlign, citation, allowedBlocks } = attributes;

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
                <RichText
                    identifier="citation"
                    tagName="cite"
                    style={{ display: 'block' }}
                    value={citation ?? ''}
                    onChange={(nextCitation: string) =>
                        setAttributes({ citation: nextCitation })
                    }
                    placeholder={__('Add citation')}
                    className="wp-block-quote__citation"
                />
            </blockquote>
        </>
    );
}
