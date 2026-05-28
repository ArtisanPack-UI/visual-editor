/**
 * Details — edit component.
 *
 * Ported from `@wordpress/block-library/src/details/edit.js` (v9.43.0).
 * Adds an explicit `wp-block-details` class to `useBlockProps` so the
 * editor canvas matches the front-end without depending on
 * `__experimentalSelector` internals.
 */

import type { ReactElement } from 'react';
import clsx from 'clsx';
import {
    RichText,
    useBlockProps,
    useInnerBlocksProps,
    InspectorControls,
    // eslint-disable-next-line @typescript-eslint/no-explicit-any
    store as blockEditorStore,
} from '@wordpress/block-editor';
import {
    TextControl,
    ToggleControl,
    // eslint-disable-next-line @typescript-eslint/no-explicit-any
    __experimentalToolsPanel as ToolsPanel,
    // eslint-disable-next-line @typescript-eslint/no-explicit-any
    __experimentalToolsPanelItem as ToolsPanelItem,
} from '@wordpress/components';
import { __ } from '@wordpress/i18n';
import { useState } from '@wordpress/element';
import { useSelect } from '@wordpress/data';

interface DetailsAttributes {
    readonly name?: string;
    readonly showContent?: boolean;
    readonly summary?: string;
    readonly allowedBlocks?: string[];
    readonly placeholder?: string;
}

const TEMPLATE: Array<[string, Record<string, unknown>]> = [
    [
        'core/paragraph',
        {
            placeholder: __('Type / to add a hidden block'),
        },
    ],
];

export default function DetailsEdit({
    attributes,
    setAttributes,
    clientId,
}: {
    attributes: DetailsAttributes;
    setAttributes: (attrs: Partial<DetailsAttributes>) => void;
    clientId: string;
}): ReactElement {
    const { name, showContent, summary, allowedBlocks, placeholder } =
        attributes;

    const blockProps = useBlockProps({
        className: clsx('wp-block-details'),
    });

    // eslint-disable-next-line @typescript-eslint/no-explicit-any
    const innerBlocksProps = (useInnerBlocksProps as any)(blockProps, {
        template: TEMPLATE,
        __experimentalCaptureToolbars: true,
        allowedBlocks,
    });

    const [isOpen, setIsOpen] = useState(!!showContent);

    const hasSelectedInnerBlock = useSelect(
        // eslint-disable-next-line @typescript-eslint/no-explicit-any
        (select: any) =>
            select(blockEditorStore).hasSelectedInnerBlock(clientId, true),
        [clientId]
    );

    const handleSummaryKeyDown = (
        event: React.KeyboardEvent<HTMLElement>
    ): void => {
        if (event.key === 'Enter' && !event.shiftKey) {
            setIsOpen((prevIsOpen) => !prevIsOpen);
            event.preventDefault();
        }
    };

    // Prevent spacebar from toggling <details> while typing.
    const handleSummaryKeyUp = (
        event: React.KeyboardEvent<HTMLElement>
    ): void => {
        if (event.key === ' ') {
            event.preventDefault();
        }
    };

    return (
        <>
            <InspectorControls>
                {/* eslint-disable-next-line @typescript-eslint/no-explicit-any */}
                <ToolsPanel
                    label={__('Settings')}
                    resetAll={() => {
                        setAttributes({
                            showContent: false,
                        });
                    }}
                >
                    {/* eslint-disable-next-line @typescript-eslint/no-explicit-any */}
                    <ToolsPanelItem
                        isShownByDefault
                        label={__('Open by default')}
                        hasValue={() => !!showContent}
                        onDeselect={() => {
                            setAttributes({
                                showContent: false,
                            });
                        }}
                    >
                        <ToggleControl
                            // @ts-expect-error - upstream prop
                            __nextHasNoMarginBottom
                            label={__('Open by default')}
                            checked={!!showContent}
                            onChange={() =>
                                setAttributes({
                                    showContent: !showContent,
                                })
                            }
                        />
                    </ToolsPanelItem>
                </ToolsPanel>
            </InspectorControls>
            <InspectorControls group="advanced">
                <TextControl
                    // @ts-expect-error - upstream prop
                    __next40pxDefaultSize
                    label={__('Name attribute')}
                    value={name || ''}
                    onChange={(newName: string) =>
                        setAttributes({ name: newName })
                    }
                    help={__(
                        'Enables multiple Details blocks with the same name attribute to be connected, with only one open at a time.'
                    )}
                />
            </InspectorControls>
            <details
                {...innerBlocksProps}
                open={isOpen || hasSelectedInnerBlock}
                onToggle={(event: React.SyntheticEvent<HTMLDetailsElement>) =>
                    setIsOpen((event.currentTarget as HTMLDetailsElement).open)
                }
                name={name || ''}
            >
                <summary
                    onKeyDown={handleSummaryKeyDown}
                    onKeyUp={handleSummaryKeyUp}
                >
                    {/* eslint-disable-next-line @typescript-eslint/no-explicit-any */}
                    <RichText
                        identifier="summary"
                        aria-label={__(
                            'Write summary. Press Enter to expand or collapse the details.'
                        )}
                        placeholder={placeholder || __('Write summary…')}
                        withoutInteractiveFormatting
                        value={summary}
                        onChange={(newSummary: string) =>
                            setAttributes({ summary: newSummary })
                        }
                    />
                </summary>
                {innerBlocksProps.children}
            </details>
        </>
    );
}
