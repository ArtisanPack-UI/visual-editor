/**
 * Integration test for #1105: a Quote that replaces a paragraph, as the
 * slash inserter does, puts the caret in the quote's own paragraph.
 *
 * Unlike `edit.test.tsx`, nothing is mocked: the real Quote and Paragraph
 * blocks render in a real `BlockEditorProvider`, so the test covers the
 * template insert, the selection update and the block focus effect
 * together.
 *
 * The original bug was a timing race in the iframed browser canvas, where
 * the quote was focused before its template paragraph existed. jsdom
 * inserts the template within the same commit, so this test passes with
 * or without the fix: it guards the wiring the fix relies on, while
 * `edit.test.tsx` covers the held-back citation itself.
 */

// `inner-blocks` and `block-list` import each other, and `inner-blocks`
// memoizes `BlockListItems` at module scope. Under Vitest's module
// loader, evaluating `block-list` first leaves that memo wrapping
// `undefined`, so any block with inner blocks crashes. Loading
// `inner-blocks` first resolves the cycle the way the production bundle
// does. This must stay the first import.
import '../../../../../../node_modules/@wordpress/block-editor/build-module/components/inner-blocks/index.mjs';
import { act, cleanup, render, waitFor } from '@testing-library/react';
import { BlockEditorProvider, BlockList, store as blockEditorStore, WritingFlow } from '@wordpress/block-editor';
import {
    createBlock,
    getBlockType,
    registerBlockType,
    setDefaultBlockName,
    type BlockInstance,
} from '@wordpress/blocks';
import { useRegistry } from '@wordpress/data';
import { afterEach, beforeAll, describe, expect, it } from 'vitest';

import * as paragraph from '../../paragraph';
import * as quote from '../index';

type Registry = ReturnType<typeof useRegistry>;

interface BlockEditorSelectors {
    getSelectedBlock(): BlockInstance | null;
    getBlockRootClientId(clientId: string): string | null;
    getBlockName(clientId: string | null): string | null;
    getBlocks(rootClientId?: string): BlockInstance[];
}

/** Exposes the provider's registry, which is a sub-registry of the default one. */
function RegistryProbe({ onRegistry }: { onRegistry: (registry: Registry) => void }): null {
    onRegistry(useRegistry());

    return null;
}

/**
 * Registers a forked block from its module exports.
 */
function register(block: { metadata: { name: string }; edit: unknown; save: unknown }): void {
    if (!getBlockType(block.metadata.name)) {
        registerBlockType(block.metadata.name, {
            ...(block.metadata as Record<string, unknown>),
            edit: block.edit,
            save: block.save,
        } as never);
    }
}

describe('Quote slash insert focus (#1105)', () => {
    beforeAll(() => {
        register(paragraph);
        register(quote);
        setDefaultBlockName(paragraph.metadata.name);
    });

    afterEach(() => {
        cleanup();
    });

    it('selects and focuses the inner paragraph, not the citation', async () => {
        const start = createBlock(paragraph.metadata.name);
        let registry: Registry | null = null;

        render(
            <BlockEditorProvider value={[start]} onInput={() => undefined} onChange={() => undefined}>
                <RegistryProbe onRegistry={(next) => (registry = next)} />
                <WritingFlow>
                    <BlockList />
                </WritingFlow>
            </BlockEditorProvider>
        );

        const select = (): BlockEditorSelectors =>
            registry!.select(blockEditorStore) as unknown as BlockEditorSelectors;
        const dispatch = registry!.dispatch(blockEditorStore);

        act(() => {
            dispatch.selectBlock(start.clientId);
        });

        // Every element that takes focus from here on.
        const focused: Element[] = [];
        const recordFocus = (event: FocusEvent): void => {
            focused.push(event.target as Element);
        };

        document.addEventListener('focusin', recordFocus);

        // What the slash inserter does on choosing Quote.
        act(() => {
            dispatch.replaceBlocks(start.clientId, [createBlock(quote.metadata.name)]);
        });

        await waitFor(() => {
            expect(select().getSelectedBlock()?.name).toBe(paragraph.metadata.name);
        });

        const selected = select().getSelectedBlock()!;
        const quoteId = select().getBlockRootClientId(selected.clientId);

        expect(select().getBlockName(quoteId)).toBe(quote.metadata.name);

        await waitFor(() => {
            const active = document.activeElement as HTMLElement | null;

            expect(active?.getAttribute('contenteditable')).toBe('true');
            expect(active?.closest('[data-block]')?.getAttribute('data-block')).toBe(selected.clientId);
        });

        document.removeEventListener('focusin', recordFocus);

        // The citation must never take the caret on the way to the
        // paragraph, and it still renders once the paragraph exists.
        expect(focused.some((element) => element.closest('cite') !== null)).toBe(false);
        expect(document.querySelector('.wp-block-quote__citation')).not.toBeNull();
    });
});
