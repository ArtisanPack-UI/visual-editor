/**
 * Tests for the click-below-the-content target (#1102), run against the
 * real block-editor store.
 */

import { act, fireEvent, render, screen } from '@testing-library/react';
import { store as blockEditorStore } from '@wordpress/block-editor';
import { createBlock, getBlockType, registerBlockType, setDefaultBlockName } from '@wordpress/blocks';
import type { BlockInstance } from '@wordpress/blocks';
import { createRegistry, RegistryProvider } from '@wordpress/data';
import { afterEach, beforeAll, beforeEach, describe, expect, it, vi } from 'vitest';

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

    describe('drag forwarding to the root drop zone (M-1)', () => {
        /** jsdom has no `DragEvent`; this is the part the target uses. */
        class TestDragEvent extends MouseEvent {
            readonly dataTransfer: DataTransfer | null;

            constructor(type: string, init: DragEventInit = {}) {
                super(type, init);
                this.dataTransfer = init.dataTransfer ?? null;
            }
        }

        beforeEach(() => {
            vi.stubGlobal('DragEvent', TestDragEvent);
        });

        afterEach(() => {
            vi.unstubAllGlobals();
        });

        function setUpWithRoot(withRoot = true): { target: HTMLElement; root: HTMLElement | null } {
            registry.dispatch(blockEditorStore).resetBlocks([createBlock(IMAGE, {})]);

            render(
                <RegistryProvider value={registry}>
                    <div>
                        {withRoot ? (
                            <div className="is-root-container" data-testid="root">
                                <div data-testid="first-block" />
                                <div data-testid="last-block" />
                            </div>
                        ) : (
                            <div data-testid="not-root" />
                        )}
                        <CanvasEndClickTarget />
                    </div>
                </RegistryProvider>
            );

            if (withRoot) {
                vi.spyOn(screen.getByTestId('last-block'), 'getBoundingClientRect').mockReturnValue({
                    left: 10,
                    right: 110,
                    top: 100,
                    bottom: 200,
                    width: 100,
                    height: 100,
                    x: 10,
                    y: 100,
                    toJSON: () => ({}),
                });
            }

            return {
                target: screen.getByTestId('ap-canvas-end-click-target'),
                root: withRoot ? screen.getByTestId('root') : null,
            };
        }

        function drag(target: HTMLElement, type: string, init: DragEventInit = {}): DragEvent {
            const event = new TestDragEvent(type, {
                bubbles: true,
                cancelable: true,
                clientX: 500,
                clientY: 400,
                ...init,
            }) as unknown as DragEvent;

            target.dispatchEvent(event);

            return event;
        }

        it.each(['dragenter', 'dragover', 'drop'])(
            'forwards %s to the root, pinned inside the last block, and claims it when handled',
            (type) => {
                const { target, root } = setUpWithRoot();
                const dataTransfer = { files: [] } as unknown as DataTransfer;
                const received: DragEvent[] = [];

                root!.addEventListener(type, (event) => {
                    received.push(event as DragEvent);
                    event.preventDefault();
                });

                const original = drag(target, type, { dataTransfer });

                expect(received).toHaveLength(1);
                expect(received[0]!.clientX).toBe(109);
                expect(received[0]!.clientY).toBe(199);
                expect(received[0]!.dataTransfer).toBe(dataTransfer);
                expect(received[0]!.bubbles).toBe(false);
                expect(original.defaultPrevented).toBe(true);
            }
        );

        it('lets the original keep bubbling so document handlers see the drag once', () => {
            const { target, root } = setUpWithRoot();
            const reachedDocument = vi.fn();

            root!.addEventListener('dragover', (event) => event.preventDefault());
            document.addEventListener('dragover', reachedDocument);

            drag(target, 'dragover');

            document.removeEventListener('dragover', reachedDocument);

            expect(reachedDocument).toHaveBeenCalledTimes(1);
        });

        it('keeps the pointer column when it is over the last block', () => {
            const { target, root } = setUpWithRoot();
            let clientX = -1;

            root!.addEventListener('dragover', (event) => {
                clientX = (event as DragEvent).clientX;
                event.preventDefault();
            });

            drag(target, 'dragover', { clientX: 60 });

            expect(clientX).toBe(60);
        });

        it('leaves the original alone when the root does not handle the copy', () => {
            const { target, root } = setUpWithRoot();
            const reachedDocument = vi.fn();

            root!.addEventListener('drop', () => undefined);
            document.addEventListener('drop', reachedDocument);

            const original = drag(target, 'drop');

            document.removeEventListener('drop', reachedDocument);

            expect(original.defaultPrevented).toBe(false);
            expect(reachedDocument).toHaveBeenCalledTimes(1);
        });

        it('forwards dragleave with its related target', () => {
            const { target, root } = setUpWithRoot();
            const firstBlock = screen.getByTestId('first-block');
            let relatedTarget: EventTarget | null = null;

            root!.addEventListener('dragleave', (event) => {
                relatedTarget = (event as DragEvent).relatedTarget;
            });

            drag(target, 'dragleave', { relatedTarget: firstBlock });

            expect(relatedTarget).toBe(firstBlock);
        });

        it('forwards nothing when the previous sibling is not the root list', () => {
            const { target } = setUpWithRoot(false);
            const notRoot = screen.getByTestId('not-root');
            const received = vi.fn();

            notRoot.addEventListener('drop', received);

            const original = drag(target, 'drop');

            expect(received).not.toHaveBeenCalled();
            expect(original.defaultPrevented).toBe(false);
        });
    });
});
