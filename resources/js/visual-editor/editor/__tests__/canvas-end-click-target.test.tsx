/**
 * Tests for the click-below-the-content target (#1102), run against the
 * real block-editor store.
 */

import { act, fireEvent, render, screen } from '@testing-library/react';
import { store as blockEditorStore } from '@wordpress/block-editor';
import { createBlock, getBlockType, registerBlockType, setDefaultBlockName } from '@wordpress/blocks';
import type { BlockInstance } from '@wordpress/blocks';
import { createRegistry, RegistryProvider } from '@wordpress/data';
import { afterEach, beforeAll, beforeEach, describe, expect, it } from 'vitest';

import { CanvasEndClickTarget } from '../canvas-end-click-target';

const PARAGRAPH = 'test/paragraph';
const IMAGE = 'test/image';

describe('CanvasEndClickTarget', () => {
    let registry: ReturnType<typeof createRegistry>;

    beforeAll(() => {
        for (const name of [PARAGRAPH, IMAGE]) {
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

    afterEach(() => {
        document.body.innerHTML = '';
    });

    function setUp(blocks: BlockInstance[], settings: Record<string, unknown> = {}): HTMLElement {
        const dispatch = registry.dispatch(blockEditorStore);

        dispatch.updateSettings(settings);
        dispatch.resetBlocks(blocks);

        render(
            <RegistryProvider value={registry}>
                <CanvasEndClickTarget />
            </RegistryProvider>
        );

        return screen.getByTestId('ap-canvas-end-click-target');
    }

    function rootBlocks(): BlockInstance[] {
        return registry.select(blockEditorStore).getBlocks();
    }

    function selectedClientId(): string | null {
        return registry.select(blockEditorStore).getSelectedBlockClientId();
    }

    it('appends and selects an empty paragraph when the last block is not one', () => {
        const image = createBlock(IMAGE, {});
        const target = setUp([image]);

        act(() => {
            fireEvent.click(target);
        });

        const blocks = rootBlocks();

        expect(blocks).toHaveLength(2);
        expect(blocks[0]?.clientId).toBe(image.clientId);
        expect(blocks[1]?.name).toBe(PARAGRAPH);
        expect(selectedClientId()).toBe(blocks[1]?.clientId);
    });

    it('appends after a paragraph that has content', () => {
        const paragraph = createBlock(PARAGRAPH, { content: 'Hello' });
        const target = setUp([paragraph]);

        act(() => {
            fireEvent.click(target);
        });

        expect(rootBlocks()).toHaveLength(2);
    });

    it('selects an existing empty trailing paragraph instead of adding another', () => {
        const image = createBlock(IMAGE, {});
        const empty = createBlock(PARAGRAPH, {});
        const target = setUp([image, empty]);

        act(() => {
            fireEvent.click(target);
        });

        expect(rootBlocks()).toHaveLength(2);
        expect(selectedClientId()).toBe(empty.clientId);
    });

    it('never stacks more than one empty trailing paragraph across repeated clicks', () => {
        const target = setUp([createBlock(IMAGE, {})]);

        act(() => {
            fireEvent.click(target);
        });
        act(() => {
            fireEvent.click(target);
        });
        act(() => {
            fireEvent.click(target);
        });

        expect(rootBlocks()).toHaveLength(2);
    });

    it('focuses the empty trailing paragraph when it is already selected', () => {
        const empty = createBlock(PARAGRAPH, {});
        const target = setUp([empty]);
        const blockElement = document.createElement('p');

        blockElement.setAttribute('data-block', empty.clientId);
        blockElement.tabIndex = 0;
        document.body.appendChild(blockElement);

        act(() => {
            registry.dispatch(blockEditorStore).selectBlock(empty.clientId);
        });
        act(() => {
            fireEvent.click(target);
        });

        expect(rootBlocks()).toHaveLength(1);
        expect(document.activeElement).toBe(blockElement);
    });

    it('appends into an empty document', () => {
        const target = setUp([]);

        act(() => {
            fireEvent.click(target);
        });

        expect(rootBlocks()).toHaveLength(1);
        expect(rootBlocks()[0]?.name).toBe(PARAGRAPH);
    });

    it('shows the text cursor only while it can append', () => {
        expect(setUp([createBlock(IMAGE, {})])).toHaveClass('is-enabled');
    });

    it.each(['all', 'insert', 'contentOnly'])('does nothing when the root is template-locked (%s)', (templateLock) => {
        const target = setUp([createBlock(IMAGE, {})], { templateLock });

        expect(target).not.toHaveClass('is-enabled');

        act(() => {
            fireEvent.click(target);
        });

        expect(rootBlocks()).toHaveLength(1);
    });

    it('does nothing when the root does not allow the default block', () => {
        const target = setUp([createBlock(IMAGE, {})], { allowedBlockTypes: [IMAGE] });

        expect(target).not.toHaveClass('is-enabled');

        act(() => {
            fireEvent.click(target);
        });

        expect(rootBlocks()).toHaveLength(1);
    });

    it('does nothing when the root editing mode is disabled', () => {
        const target = setUp([createBlock(IMAGE, {})]);

        act(() => {
            registry.dispatch(blockEditorStore).setBlockEditingMode('', 'disabled');
        });
        act(() => {
            fireEvent.click(target);
        });

        expect(rootBlocks()).toHaveLength(1);
    });

    it('ignores non-primary clicks', () => {
        const target = setUp([createBlock(IMAGE, {})]);

        act(() => {
            fireEvent.click(target, { button: 1 });
        });

        expect(rootBlocks()).toHaveLength(1);
    });

    it('renders a compact variant for the composed view', () => {
        const dispatch = registry.dispatch(blockEditorStore);

        dispatch.resetBlocks([]);

        render(
            <RegistryProvider value={registry}>
                <CanvasEndClickTarget compact />
            </RegistryProvider>
        );

        expect(screen.getByTestId('ap-canvas-end-click-target')).toHaveClass(
            'ap-canvas-end-click-target--compact'
        );
    });
});
