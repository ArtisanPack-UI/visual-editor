/**
 * Tests for the shared double-Enter exit hook used by Code, Preformatted
 * and Verse, run against the real block-editor store (#1101).
 */

import type { ReactNode } from 'react';
import { beforeAll, beforeEach, describe, expect, it, vi } from 'vitest';
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
import { RichTextData } from '@wordpress/rich-text';

import { useExitOnDoubleEnter } from '../use-exit-on-double-enter';

const CODE = 'test/code';
const PARAGRAPH = 'test/paragraph';

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

describe('useExitOnDoubleEnter', () => {
    let registry: ReturnType<typeof createRegistry>;

    beforeAll(() => {
        for (const name of [CODE, PARAGRAPH]) {
            if (!getBlockType(name)) {
                registerBlockType(name, {
                    apiVersion: 3,
                    title: name,
                    category: 'text',
                    attributes: { content: { type: 'rich-text' } },
                    edit: () => null,
                    save: () => null,
                } as never);
            }
        }

        setDefaultBlockName(PARAGRAPH);
    });

    beforeEach(() => {
        registry = createRegistry();
        registry.register(blockEditorStore);
    });

    function setUp(
        content: unknown,
        selection: { start: number; end?: number; clientId?: string; attributeKey?: string },
        withInsert = true,
        prepare: (block: BlockInstance) => void = () => {}
    ) {
        const block: BlockInstance = createBlock(CODE, {});
        block.attributes.content = content;
        prepare(block);
        const other = createBlock(CODE, { content: 'other' });
        const dispatch = registry.dispatch(blockEditorStore);

        dispatch.resetBlocks([block, other]);
        dispatch.selectionChange(
            selection.clientId === 'other' ? other.clientId : block.clientId,
            selection.attributeKey ?? 'content',
            selection.start,
            selection.end ?? selection.start
        );

        const setAttributes = vi.fn();
        const insertBlocksAfter = vi.fn();
        const batch = vi.spyOn(registry, 'batch');
        const wrapper = ({ children }: { children: ReactNode }) => (
            <RegistryProvider value={registry}>{children}</RegistryProvider>
        );
        const { result } = renderHook(
            () =>
                useExitOnDoubleEnter({
                    clientId: block.clientId,
                    setAttributes,
                    insertBlocksAfter: withInsert ? insertBlocksAfter : undefined,
                }),
            { wrapper }
        );
        const element = document.createElement('pre');

        act(() => result.current(element));

        return { element, setAttributes, insertBlocksAfter, batch };
    }

    it('drops the trailing newline and exits on Enter at the end of a blank last line', () => {
        const { element, setAttributes, insertBlocksAfter, batch } = setUp('foo<br>', { start: 4 });

        const event = pressEnter(element);

        expect(event.defaultPrevented).toBe(true);
        expect(setAttributes).toHaveBeenCalledWith({ content: 'foo' });
        expect(insertBlocksAfter).toHaveBeenCalledWith(
            expect.objectContaining({ name: PARAGRAPH })
        );
        expect(batch).toHaveBeenCalledTimes(1);
    });

    it('treats a literal trailing newline the same as a <br>', () => {
        const { element, setAttributes, insertBlocksAfter } = setUp('foo\n', { start: 4 });

        pressEnter(element);

        expect(setAttributes).toHaveBeenCalledWith({ content: 'foo' });
        expect(insertBlocksAfter).toHaveBeenCalledTimes(1);
    });

    it('keeps formatting when trimming the trailing newline', () => {
        const { element, setAttributes } = setUp('<strong>foo</strong><br>', { start: 4 });

        pressEnter(element);

        expect(setAttributes).toHaveBeenCalledWith({ content: '<strong>foo</strong>' });
    });

    it('returns RichTextData when the attribute holds RichTextData', () => {
        const { element, setAttributes } = setUp(
            RichTextData.fromHTMLString('foo<br>'),
            { start: 4 }
        );

        pressEnter(element);

        const next = setAttributes.mock.calls[0][0].content;
        expect(next).toBeInstanceOf(RichTextData);
        expect(next.text).toBe('foo');
    });

    it('lets the first Enter at the end through as a newline', () => {
        const { element, setAttributes, insertBlocksAfter } = setUp('foo', { start: 3 });

        const event = pressEnter(element);

        expect(event.defaultPrevented).toBe(false);
        expect(setAttributes).not.toHaveBeenCalled();
        expect(insertBlocksAfter).not.toHaveBeenCalled();
    });

    it('lets Enter through in the middle of the content, even on a blank line', () => {
        const { element, insertBlocksAfter } = setUp('foo<br><br>bar', { start: 4 });

        const event = pressEnter(element);

        expect(event.defaultPrevented).toBe(false);
        expect(insertBlocksAfter).not.toHaveBeenCalled();
    });

    it.each([
        ['Shift', { shiftKey: true }],
        ['Alt', { altKey: true }],
        ['Ctrl', { ctrlKey: true }],
        ['Meta', { metaKey: true }],
    ])('lets %s+Enter through', (_label, modifiers) => {
        const { element, insertBlocksAfter } = setUp('foo<br>', { start: 4 });

        const event = pressEnter(element, modifiers);

        expect(event.defaultPrevented).toBe(false);
        expect(insertBlocksAfter).not.toHaveBeenCalled();
    });

    it('does nothing with a non-collapsed selection', () => {
        const { element, insertBlocksAfter } = setUp('foo<br>', { start: 2, end: 4 });

        pressEnter(element);

        expect(insertBlocksAfter).not.toHaveBeenCalled();
    });

    it('does nothing when the selection is in another block or attribute', () => {
        const other = setUp('foo<br>', { start: 4, clientId: 'other' });
        pressEnter(other.element);
        expect(other.insertBlocksAfter).not.toHaveBeenCalled();

        const citation = setUp('foo<br>', { start: 4, attributeKey: 'citation' });
        pressEnter(citation.element);
        expect(citation.insertBlocksAfter).not.toHaveBeenCalled();
    });

    it('does nothing without insertBlocksAfter', () => {
        const { element, setAttributes } = setUp('foo<br>', { start: 4 }, false);

        const event = pressEnter(element);

        expect(event.defaultPrevented).toBe(false);
        expect(setAttributes).not.toHaveBeenCalled();
    });

    it('ignores other keys and already-handled events', () => {
        const { element, insertBlocksAfter } = setUp('foo<br>', { start: 4 });

        pressEnter(element, { keyCode: 9 } as KeyboardEventInit);

        const handled = new KeyboardEvent('keydown', {
            cancelable: true,
            keyCode: 13,
        } as KeyboardEventInit);
        handled.preventDefault();
        element.dispatchEvent(handled);

        expect(insertBlocksAfter).not.toHaveBeenCalled();
    });

    it('lets Enter through while an IME composition is in progress', () => {
        const { element, insertBlocksAfter } = setUp('foo<br>', { start: 4 });

        const event = pressEnter(element, { isComposing: true } as KeyboardEventInit);

        expect(event.defaultPrevented).toBe(false);
        expect(insertBlocksAfter).not.toHaveBeenCalled();
    });

    it('leaves a block bound to data alone', () => {
        const { element, setAttributes, insertBlocksAfter } = setUp(
            'foo<br>',
            { start: 4 },
            true,
            (block) => {
                block.attributes.bindings = {
                    content: { source: 'post_core', args: { key: 'title' } },
                };
            }
        );

        const event = pressEnter(element);

        expect(event.defaultPrevented).toBe(false);
        expect(setAttributes).not.toHaveBeenCalled();
        expect(insertBlocksAfter).not.toHaveBeenCalled();
    });

    it('keeps the newline when the default block is not allowed here', () => {
        registry.dispatch(blockEditorStore).updateSettings({ allowedBlockTypes: [CODE] });
        const { element, setAttributes, insertBlocksAfter } = setUp('foo<br>', { start: 4 });

        const event = pressEnter(element);

        expect(event.defaultPrevented).toBe(false);
        expect(setAttributes).not.toHaveBeenCalled();
        expect(insertBlocksAfter).not.toHaveBeenCalled();
    });
});
