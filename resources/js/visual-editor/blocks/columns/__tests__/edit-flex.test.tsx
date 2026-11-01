/**
 * Custom Flex Panel values on a Columns block with columns in it (#1039).
 */

import { render } from '@testing-library/react';
import { describe, expect, it, vi } from 'vitest';

vi.mock('@wordpress/data', () => {
    const storeApi = {
        getBlocks: () => [{ clientId: 'column' }],
        getBlockOrder: () => ['column'],
        canInsertBlockType: () => true,
        canRemoveBlock: () => true,
    };

    return {
        useSelect: (source: unknown) =>
            typeof source === 'function' ? source(() => storeApi) : storeApi,
        useDispatch: () => ({
            replaceInnerBlocks: () => undefined,
            updateBlockAttributes: () => undefined,
        }),
        useRegistry: () => ({ batch: (fn: () => void) => fn() }),
    };
});

vi.mock('@wordpress/blocks', () => ({
    createBlock: () => undefined,
    createBlocksFromInnerBlocksTemplate: () => [],
    store: {},
}));

vi.mock('@wordpress/block-editor', () => ({
    InspectorControls: () => null,
    BlockControls: () => null,
    BlockVerticalAlignmentToolbar: () => null,
    __experimentalBlockVariationPicker: () => null,
    useBlockProps: (props?: Record<string, unknown>) => ({ ...props }),
    useInnerBlocksProps: (props?: Record<string, unknown>) => ({ ...props }),
    useSettings: (...paths: string[]): unknown[] => paths.map(() => undefined),
    store: {},
}));

import ColumnsEdit from '../edit';

describe('ColumnsEdit', () => {
    it('publishes custom flex values to the canvas', () => {
        render(
            <ColumnsEdit
                attributes={{
                    artisanpackFlex: { container: { gap: { column: { base: '5px' } } } },
                }}
                setAttributes={vi.fn()}
                clientId="columns-flex"
                name="artisanpack/columns"
            />
        );

        expect(
            document.querySelector('style[data-ap-canvas-styles="flex-arbitrary"]')
                ?.textContent
        ).toContain('column-gap: 5px');
    });
});
