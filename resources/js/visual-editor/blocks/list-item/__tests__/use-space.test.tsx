/**
 * Tests for the `artisanpack/list-item` Space/Tab hook and the indent hook
 * behind it, run against the real block-editor store (#1101).
 */

import type { ReactNode } from 'react';
import { beforeAll, beforeEach, describe, expect, it } from 'vitest';
import { act, renderHook } from '@testing-library/react';
import { store as blockEditorStore } from '@wordpress/block-editor';
import { createBlock, getBlockType, registerBlockType } from '@wordpress/blocks';
import type { BlockInstance } from '@wordpress/blocks';
import { createRegistry, RegistryProvider } from '@wordpress/data';

import listMetadata from '../../list/block.json';
import listItemMetadata from '../block.json';
import useIndentListItem from '../hooks/use-indent-list-item';
import useSpace from '../hooks/use-space';

const LIST = 'artisanpack/list';
const ITEM = 'artisanpack/list-item';

function item(content: string, nested: BlockInstance[] = []): BlockInstance {
    return createBlock(
        ITEM,
        { content },
        nested.length ? [createBlock(LIST, {}, nested)] : []
    );
}

function press(element: HTMLElement, init: KeyboardEventInit): KeyboardEvent {
    const event = new KeyboardEvent('keydown', {
        bubbles: true,
        cancelable: true,
        ...init,
    } as KeyboardEventInit);

    element.dispatchEvent(event);

    return event;
}

describe('useSpace / useIndentListItem', () => {
    let registry: ReturnType<typeof createRegistry>;

    beforeAll(() => {
        for (const metadata of [listMetadata, listItemMetadata]) {
            if (!getBlockType(metadata.name)) {
                const { name, ...rest } = metadata;

                registerBlockType(name, {
                    ...rest,
                    edit: () => null,
                    save: () => null,
                } as never);
            }
        }
    });

    beforeEach(() => {
        registry = createRegistry();
        registry.register(blockEditorStore);
    });

    function allowInsertion(
        dispatch: ReturnType<typeof registry.dispatch>,
        blocks: BlockInstance[]
    ): void {
        for (const block of blocks) {
            dispatch.updateBlockListSettings(block.clientId, {});
            allowInsertion(dispatch, block.innerBlocks);
        }
    }

    function wrapper({ children }: { children: ReactNode }) {
        return <RegistryProvider value={registry}>{children}</RegistryProvider>;
    }

    function setUp(blocks: BlockInstance[], target: BlockInstance, offset: number) {
        const dispatch = registry.dispatch(blockEditorStore);

        dispatch.resetBlocks(blocks);
        allowInsertion(dispatch, blocks);
        dispatch.selectionChange(target.clientId, 'content', offset, offset);

        const { result } = renderHook(() => useSpace(target.clientId), { wrapper });
        const element = document.createElement('div');

        act(() => result.current(element));

        return { element };
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

    function currentOutline(): unknown[] {
        return outline(registry.select(blockEditorStore).getBlocks());
    }

    it('indents an item under its previous sibling on Tab at the start', () => {
        const target = item('Two');
        const { element } = setUp([createBlock(LIST, {}, [item('One'), target])], target, 0);

        let event: KeyboardEvent | undefined;
        act(() => {
            event = press(element, { keyCode: 9 });
        });

        expect(event?.defaultPrevented).toBe(true);
        expect(currentOutline()).toEqual([[['One', [['Two']]]]]);
    });

    it('appends to the previous sibling\'s existing nested list', () => {
        const target = item('Two');
        const { element } = setUp(
            [createBlock(LIST, {}, [item('One', [item('Nested')]), target])],
            target,
            0
        );

        act(() => {
            press(element, { keyCode: 32 });
        });

        expect(currentOutline()).toEqual([[['One', [['Nested', 'Two']]]]]);
    });

    it('outdents a nested item on Shift+Tab at the start', () => {
        const target = item('Nested');
        const { element } = setUp(
            [createBlock(LIST, {}, [item('One', [target])])],
            target,
            0
        );
        registry.dispatch(blockEditorStore).selectBlock(target.clientId);
        registry.dispatch(blockEditorStore).selectionChange(target.clientId, 'content', 0, 0);

        let event: KeyboardEvent | undefined;
        act(() => {
            event = press(element, { keyCode: 9, shiftKey: true });
        });

        expect(event?.defaultPrevented).toBe(true);
        expect(currentOutline()).toEqual([['One', 'Nested']]);
    });

    it('leaves Tab alone on the first item, mid-text and with modifiers', () => {
        const first = item('One');
        const { element } = setUp([createBlock(LIST, {}, [first, item('Two')])], first, 0);

        const onFirst = press(element, { keyCode: 9 });

        expect(onFirst.defaultPrevented).toBe(false);

        const second = item('Two');
        const midText = setUp([createBlock(LIST, {}, [item('One'), second])], second, 2);
        const midEvent = press(midText.element, { keyCode: 9 });
        const ctrlEvent = press(midText.element, { keyCode: 9, ctrlKey: true });

        expect(midEvent.defaultPrevented).toBe(false);
        expect(ctrlEvent.defaultPrevented).toBe(false);
        expect(currentOutline()).toEqual([['One', 'Two']]);
    });

    it('returns false instead of throwing when there is no previous sibling', () => {
        const first = item('One');
        registry.dispatch(blockEditorStore).resetBlocks([createBlock(LIST, {}, [first])]);

        const { result } = renderHook(() => useIndentListItem(first.clientId), { wrapper });

        expect(result.current()).toBe(false);
    });
});
