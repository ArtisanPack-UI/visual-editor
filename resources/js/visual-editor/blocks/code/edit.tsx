/**
 * Code — editor-side render.
 *
 * Ported from `@wordpress/block-library/src/code/edit.js` (v9.43.0).
 */

import type { ReactElement } from 'react';
import { __ } from '@wordpress/i18n';
import { TEXT_DOMAIN } from '../../vendor/i18n';
import { InspectorControls, RichText, useBlockProps } from '@wordpress/block-editor';
import { createBlock, getDefaultBlockName } from '@wordpress/blocks';
import { PanelBody, SelectControl } from '@wordpress/components';

import { CODE_LANGUAGE_LABELS } from './languages';

interface CodeAttributes {
    readonly content: string;
    readonly language?: string;
}

interface CodeEditProps {
    readonly attributes: CodeAttributes;
    readonly setAttributes: (next: Partial<CodeAttributes>) => void;
    readonly onRemove?: () => void;
    readonly insertBlocksAfter?: (block: unknown) => void;
    readonly mergeBlocks?: (forward?: boolean) => void;
}

export default function CodeEdit({
    attributes,
    setAttributes,
    onRemove,
    insertBlocksAfter,
    mergeBlocks,
}: CodeEditProps): ReactElement {
    const blockProps = useBlockProps();
    const languageOptions = [
        { label: __('None', TEXT_DOMAIN), value: '' },
        { label: __('Plain text', TEXT_DOMAIN), value: 'plaintext' },
        ...CODE_LANGUAGE_LABELS,
    ];

    return (
        <>
            <InspectorControls>
                <PanelBody title={__('Settings', TEXT_DOMAIN)}>
                    <SelectControl
                        __next40pxDefaultSize
                        __nextHasNoMarginBottom
                        label={__('Language', TEXT_DOMAIN)}
                        help={__('Highlights the code on the published page.', TEXT_DOMAIN)}
                        value={attributes.language ?? ''}
                        options={languageOptions}
                        onChange={(language: string) =>
                            setAttributes({ language: language || undefined })
                        }
                    />
                </PanelBody>
            </InspectorControls>
            <pre {...blockProps}>
                <RichText
                    tagName="code"
                    identifier="content"
                    value={attributes.content}
                    onChange={(content: string) => setAttributes({ content })}
                    onRemove={onRemove}
                    onMerge={mergeBlocks}
                    placeholder={__('Write code…', TEXT_DOMAIN)}
                    aria-label={__('Code', TEXT_DOMAIN)}
                    preserveWhiteSpace
                    __unstablePastePlainText
                    __unstableOnSplitAtDoubleLineEnd={() => {
                        const defaultName = getDefaultBlockName();
                        if (defaultName) {
                            insertBlocksAfter?.(createBlock(defaultName));
                        }
                    }}
                />
            </pre>
        </>
    );
}
