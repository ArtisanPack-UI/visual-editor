/**
 * Tests for the `artisanpack/list-item` merge hook, run against the real
 * block-editor store (#1043).
 */

import type { ReactNode } from 'react';
import { beforeAll, beforeEach, describe, expect, it, vi } from 'vitest';
import { act, renderHook } from '@testing-library/react';
import { store as blockEditorStore } from '@wordpress/block-editor';
import { createBlock, getBlockType, registerBlockType } from '@wordpress/blocks';
import type { BlockInstance } from '@wordpress/blocks';
import { createRegistry, RegistryProvider } from '@wordpress/data';

import listMetadata from '../../list/block.json';
import listItemMetadata from '../block.json';
import useMerge from '../hooks/use-merge';
import { merge } from '../index';

const LIST = 'artisanpack/list';
const ITEM = 'artisanpack/list-item';

function item(content: string, nested: BlockInstance[] = []): BlockInstance {
    return createBlock(
        ITEM,
        { content },
        nested.length ? [createBlock(LIST, {}, nested)] : []
    );
}

describe('useMerge', () => {
    let registry: ReturnType<typeof createRegistry>;

    beforeAll(() => {
        for (const metadata of [listMetadata, listItemMetadata]) {
            if (!getBlockType(metadata.name)) {
                const { name, ...rest } = metadata;

                registerBlockType(name, {
                    ...rest,
                    edit: () => null,
                    save: () => null,
                    ...(name === ITEM ? { merge } : {}),
                } as never);
            }
        }
    });

    beforeEach(() => {
        registry = createRegistry();
        registry.register(blockEditorStore);
    });

    function setUp(items: BlockInstance[], target: BlockInstance) {
        const list = createBlock(LIST, {}, items);
        const dispatch = registry.dispatch(blockEditorStore);
        const onMerge = vi.fn();

        dispatch.resetBlocks([list]);
        allowInsertion(dispatch, [list]);

        const wrapper = ({ children }: { children: ReactNode }) => (
            <RegistryProvider value={registry}>{children}</RegistryProvider>
        );
        const { result } = renderHook(
            () => useMerge(target.clientId, onMerge),
            { wrapper }
        );

        return { list, onMerge, run: result.current };
    }

    // InnerBlocks sets list settings on mount; a parent without them refuses
    // inserts.
    function allowInsertion(
        dispatch: ReturnType<typeof registry.dispatch>,
        blocks: BlockInstance[]
    ): void {
        for (const block of blocks) {
            dispatch.updateBlockListSettings(block.clientId, {});
            allowInsertion(dispatch, block.innerBlocks);
        }
    }

    function outline(blocks: BlockInstance[]): unknown[] {
        return blocks.map((block) =>
            block.name === ITEM
                ? block.innerBlocks.length
                    ? [String(block.attributes.content), outline(block.innerBlocks)]
                    : String(block.attributes.content)
                : outline(block.innerBlocks)
        );
    }

    function currentOutline(listClientId: string): unknown[] {
        return outline(
            registry.select(blockEditorStore).getBlocks(listClientId)
        );
    }

    it('merges backward into the previous item', () => {
        const target = item('Two');
        const { list, run } = setUp([item('One'), target], target);

        act(() => run(false));

        expect(currentOutline(list.clientId)).toEqual(['OneTwo']);
    });

    it('merges backward into the last nested item above it', () => {
        const target = item('Two');
        const { list, run } = setUp(
            [item('One', [item('Nested')]), target],
            target
        );

        act(() => run(false));

        expect(currentOutline(list.clientId)).toEqual([
            ['One', [['NestedTwo']]],
        ]);
    });

    it('outdents a nested item on Backspace instead of merging it', () => {
        const target = item('Nested');
        const { list, run } = setUp([item('One', [target])], target);

        act(() => run(false));

        expect(currentOutline(list.clientId)).toEqual(['One', 'Nested']);
    });

    it('moves a merged item\'s nested list up to the previous item', () => {
        const target = item('Two', [item('Nested')]);
        const { list, run } = setUp([item('One'), target], target);

        act(() => run(false));

        expect(currentOutline(list.clientId)).toEqual([
            ['OneTwo', [['Nested']]],
        ]);
    });

    it('merges forward into the next item', () => {
        const target = item('One');
        const { list, run } = setUp([target, item('Two')], target);

        act(() => run(true));

        expect(currentOutline(list.clientId)).toEqual(['OneTwo']);
    });

    it('outdents the nested item below on Delete', () => {
        const target = item('Parent', [item('Nested')]);
        const { list, run } = setUp([target], target);

        act(() => run(true));

        expect(currentOutline(list.clientId)).toEqual(['Parent', 'Nested']);
    });

    it('hands off to the block-level merge at the end of the list', () => {
        const target = item('Only');
        const { onMerge, run } = setUp([target], target);

        act(() => run(true));

        expect(onMerge).toHaveBeenCalledWith(true);
    });

    it('hands off to the block-level merge at the start of the list', () => {
        const target = item('Only');
        const { onMerge, run } = setUp([target], target);

        act(() => run(false));

        expect(onMerge).toHaveBeenCalledWith(false);
    });

    it('lifts the nested items of an empty first item', () => {
        const target = item('', [item('Nested')]);
        const { list, run } = setUp([target], target);

        act(() => run(false));

        expect(currentOutline(list.clientId)).toEqual(['Nested']);
    });
});
