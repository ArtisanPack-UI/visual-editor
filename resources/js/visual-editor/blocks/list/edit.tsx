/**
 * List — editor-side render.
 *
 * Simplified port of `@wordpress/block-library/src/list/edit.js` (v9.43.0).
 * The upstream edit ships ordered/unordered toggle buttons + indent/outdent
 * toolbar + ordered-list settings panel; this fork ships the toggle buttons
 * and a minimal ordered-list settings panel. The indent/outdent hooks rely
 * on block-editor private dispatch surface and are deferred to the post-I7
 * customization phase. Inner-block insertion still uses
 * `artisanpack/list-item` so list nesting works.
 */

import type { ReactElement } from 'react';
import {
    BlockControls,
    InspectorControls,
    useBlockProps,
    useInnerBlocksProps,
} from '@wordpress/block-editor';
import {
    PanelBody,
    SelectControl,
    TextControl,
    ToggleControl,
    ToolbarButton,
} from '@wordpress/components';
import { __, _x } from '@wordpress/i18n';

import { TEXT_DOMAIN } from '../../vendor/i18n';

interface ListAttributes {
    readonly ordered?: boolean;
    readonly type?: string;
    readonly reversed?: boolean;
    readonly start?: number;
}

interface ListEditProps {
    readonly attributes: ListAttributes;
    readonly setAttributes: (next: Partial<ListAttributes>) => void;
}

const TEMPLATE: ReadonlyArray<readonly [string, Record<string, unknown>]> = [
    ['artisanpack/list-item', {}],
];

function listStyleOptions(): Array<{ label: string; value: string }> {
    return [
        { label: __('Numbers', TEXT_DOMAIN), value: 'decimal' },
        { label: __('Uppercase letters', TEXT_DOMAIN), value: 'upper-alpha' },
        { label: __('Lowercase letters', TEXT_DOMAIN), value: 'lower-alpha' },
        { label: __('Uppercase Roman numerals', TEXT_DOMAIN), value: 'upper-roman' },
        { label: __('Lowercase Roman numerals', TEXT_DOMAIN), value: 'lower-roman' },
    ];
}

export default function ListEdit({
    attributes,
    setAttributes,
}: ListEditProps): ReactElement {
    const { ordered, type, reversed, start } = attributes;
    const blockProps = useBlockProps({
        style: {
            listStyleType: ordered && type !== 'decimal' ? type : undefined,
        },
    });
    const innerBlocksProps = useInnerBlocksProps(blockProps, {
        defaultBlock: { name: 'artisanpack/list-item' } as unknown as never,
        directInsert: true,
        template: TEMPLATE as unknown as readonly [string, Record<string, unknown>][],
        templateLock: false,
        templateInsertUpdatesSelection: true,
    });

    const TagName = ordered ? 'ol' : 'ul';

    return (
        <>
            <BlockControls group="block">
                <ToolbarButton
                    title={__('Unordered', TEXT_DOMAIN)}
                    isActive={ordered === false}
                    onClick={() => setAttributes({ ordered: false })}
                >
                    {_x('UL', 'unordered list toolbar button', TEXT_DOMAIN)}
                </ToolbarButton>
                <ToolbarButton
                    title={__('Ordered', TEXT_DOMAIN)}
                    isActive={ordered === true}
                    onClick={() => setAttributes({ ordered: true })}
                >
                    {_x('OL', 'ordered list toolbar button', TEXT_DOMAIN)}
                </ToolbarButton>
            </BlockControls>
            {ordered && (
                <InspectorControls>
                    <PanelBody title={__('Settings', TEXT_DOMAIN)}>
                        <SelectControl
                            label={__('List style', TEXT_DOMAIN)}
                            options={listStyleOptions()}
                            value={type ?? 'decimal'}
                            onChange={(value) =>
                                setAttributes({ type: value || undefined })
                            }
                        />
                        <TextControl
                            label={__('Start value', TEXT_DOMAIN)}
                            type="number"
                            value={
                                Number.isInteger(start) ? String(start) : ''
                            }
                            onChange={(value) => {
                                const int = parseInt(value, 10);
                                setAttributes({
                                    start: isNaN(int) ? undefined : int,
                                });
                            }}
                        />
                        <ToggleControl
                            label={__('Reverse order', TEXT_DOMAIN)}
                            checked={!!reversed}
                            onChange={(value) =>
                                setAttributes({ reversed: value || undefined })
                            }
                        />
                    </PanelBody>
                </InspectorControls>
            )}
            <TagName {...innerBlocksProps} />
        </>
    );
}
