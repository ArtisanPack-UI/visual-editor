/**
 * Tests for the `artisanpack/list-item` Enter hook, run against the real
 * block-editor store (#1101).
 */

import type { ReactNode } from 'react';
import { beforeAll, beforeEach, describe, expect, it } from 'vitest';
import { act, renderHook } from '@testing-library/react';
import { store as blockEditorStore } from '@wordpress/block-editor';
import {
    createBlock,
    getBlockType,
    registerBlockType,
    setDefaultBlockName,
} from '@wordpress/blocks';
import type { BlockInstance } from '@wordpress/blocks';
import { createRegistry, RegistryProvider } from '@wordpress/data';

import listMetadata from '../../list/block.json';
import listItemMetadata from '../block.json';
import useEnter from '../hooks/use-enter';

const LIST = 'artisanpack/list';
const ITEM = 'artisanpack/list-item';
const PARAGRAPH = 'test/paragraph';

function item(content: string, nested: BlockInstance[] = []): BlockInstance {
    return createBlock(
        ITEM,
        { content },
        nested.length ? [createBlock(LIST, {}, nested)] : []
    );
}

function pressEnter(element: HTMLElement, init: KeyboardEventInit = {}): KeyboardEvent {
    const event = new KeyboardEvent('keydown', {
        bubbles: true,
        cancelable: true,
        keyCode: 13,
        ...init,
    } as KeyboardEventInit);

    element.dispatchEvent(event);

    return event;
}

describe('useEnter', () => {
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

        if (!getBlockType(PARAGRAPH)) {
            registerBlockType(PARAGRAPH, {
                apiVersion: 3,
                title: 'Paragraph',
                category: 'text',
                attributes: { content: { type: 'string' } },
                edit: () => null,
                save: () => null,
            } as never);
        }

        setDefaultBlockName(PARAGRAPH);
    });

    beforeEach(() => {
        registry = createRegistry();
        registry.register(blockEditorStore);
    });

    function setUp(blocks: BlockInstance[], target: BlockInstance) {
        const dispatch = registry.dispatch(blockEditorStore);

        dispatch.resetBlocks(blocks);
        allowInsertion(dispatch, blocks);

        const wrapper = ({ children }: { children: ReactNode }) => (
            <RegistryProvider value={registry}>{children}</RegistryProvider>
        );
        const { result } = renderHook(
            () =>
                useEnter({
                    content: target.attributes.content,
                    clientId: target.clientId,
                }),
            { wrapper }
        );
        const element = document.createElement('div');

        act(() => result.current(element));

        return { element };
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
        return blocks.map((block) => {
            if (block.name === ITEM) {
                return block.innerBlocks.length
                    ? [String(block.attributes.content), outline(block.innerBlocks)]
                    : String(block.attributes.content);
            }

            if (block.name === LIST) {
                return { list: outline(block.innerBlocks) };
            }

            return block.name;
        });
    }

    function currentOutline(): unknown[] {
        return outline(registry.select(blockEditorStore).getBlocks());
    }

    function selectedBlockName(): string | null {
        const select = registry.select(blockEditorStore);
        const clientId = select.getSelectedBlockClientId();

        return clientId ? select.getBlockName(clientId) : null;
    }

    it('leaves the list into a new paragraph from an empty last item', () => {
        const target = item('');
        const { element } = setUp([createBlock(LIST, {}, [item('One'), target])], target);

        let event: KeyboardEvent | undefined;
        act(() => {
            event = pressEnter(element);
        });

        expect(event?.defaultPrevented).toBe(true);
        expect(currentOutline()).toEqual([{ list: ['One'] }, PARAGRAPH]);
        expect(selectedBlockName()).toBe(PARAGRAPH);
    });

    it('splits the list around a new paragraph from an empty middle item', () => {
        const target = item('');
        const { element } = setUp(
            [createBlock(LIST, {}, [item('One'), target, item('Three')])],
            target
        );

        act(() => {
            pressEnter(element);
        });

        expect(currentOutline()).toEqual([
            { list: ['One'] },
            PARAGRAPH,
            { list: ['Three'] },
        ]);
        expect(selectedBlockName()).toBe(PARAGRAPH);
    });

    it('replaces a list whose only item is empty with a paragraph', () => {
        const target = item('');
        const { element } = setUp([createBlock(LIST, {}, [target])], target);

        act(() => {
            pressEnter(element);
        });

        expect(currentOutline()).toEqual([PARAGRAPH]);
    });

    it('keeps the emptied item\'s nested items in the tail list', () => {
        const target = item('', [item('Child')]);
        const { element } = setUp([createBlock(LIST, {}, [item('One'), target])], target);

        act(() => {
            pressEnter(element);
        });

        expect(currentOutline()).toEqual([
            { list: ['One'] },
            PARAGRAPH,
            { list: ['Child'] },
        ]);
    });

    it('outdents an empty nested item one level', () => {
        const target = item('');
        const { element } = setUp(
            [createBlock(LIST, {}, [item('One', [item('Nested'), target])])],
            target
        );
        registry.dispatch(blockEditorStore).selectBlock(target.clientId);

        act(() => {
            pressEnter(element);
        });

        expect(currentOutline()).toEqual([
            { list: [['One', [{ list: ['Nested'] }]], ''] },
        ]);
    });

    it('lets Enter through on an item with text', () => {
        const target = item('One');
        const { element } = setUp([createBlock(LIST, {}, [target])], target);

        let event: KeyboardEvent | undefined;
        act(() => {
            event = pressEnter(element);
        });

        expect(event?.defaultPrevented).toBe(false);
        expect(currentOutline()).toEqual([{ list: ['One'] }]);
    });

    it('ignores an item whose text is bound to data', () => {
        const target = item('');
        target.attributes.bindings = {
            content: { source: 'post_core', args: { key: 'title' } },
        };
        const { element } = setUp([createBlock(LIST, {}, [item('One'), target])], target);

        let event: KeyboardEvent | undefined;
        act(() => {
            event = pressEnter(element);
        });

        expect(event?.defaultPrevented).toBe(false);
        expect(currentOutline()).toEqual([{ list: ['One', ''] }]);
    });

    it('ignores other keys and already-handled events', () => {
        const target = item('');
        const { element } = setUp([createBlock(LIST, {}, [item('One'), target])], target);

        act(() => {
            pressEnter(element, { keyCode: 9 } as KeyboardEventInit);

            const handled = new KeyboardEvent('keydown', {
                cancelable: true,
                keyCode: 13,
            } as KeyboardEventInit);
            handled.preventDefault();
            element.dispatchEvent(handled);
        });

        expect(currentOutline()).toEqual([{ list: ['One', ''] }]);
    });
});
