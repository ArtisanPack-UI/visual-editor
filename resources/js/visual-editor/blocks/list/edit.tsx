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

const LIST_STYLE_OPTIONS = [
    { label: 'Numbers', value: 'decimal' },
    { label: 'Uppercase letters', value: 'upper-alpha' },
    { label: 'Lowercase letters', value: 'lower-alpha' },
    { label: 'Uppercase Roman numerals', value: 'upper-roman' },
    { label: 'Lowercase Roman numerals', value: 'lower-roman' },
];

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
                    title="Unordered"
                    isActive={ordered === false}
                    onClick={() => setAttributes({ ordered: false })}
                >
                    UL
                </ToolbarButton>
                <ToolbarButton
                    title="Ordered"
                    isActive={ordered === true}
                    onClick={() => setAttributes({ ordered: true })}
                >
                    OL
                </ToolbarButton>
            </BlockControls>
            {ordered && (
                <InspectorControls>
                    <PanelBody title="Settings">
                        <SelectControl
                            label="List style"
                            options={LIST_STYLE_OPTIONS}
                            value={type ?? 'decimal'}
                            onChange={(value) =>
                                setAttributes({ type: value || undefined })
                            }
                        />
                        <TextControl
                            label="Start value"
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
                            label="Reverse order"
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
