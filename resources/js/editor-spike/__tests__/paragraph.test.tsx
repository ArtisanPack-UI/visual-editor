import { afterEach, beforeEach, describe, expect, it } from 'vitest';
import { act, render, screen } from '@testing-library/react';
import { getTiptapEditor } from '../richtext/useTiptap';
import type { Block } from '../mocks/blockTree';
import { BlockTreeProvider } from '../primitives/BlockTreeContext';
import { ReadOnlyProvider } from '../primitives/ReadOnlyContext';
import { clearRegistry, getBlock } from '../registry';
import { RenderBlock } from '../primitives/useInnerBlocksProps';
import { PARAGRAPH_BLOCK_NAME, registerParagraphBlock } from '../blocks/paragraph';

function makeParagraph(content: string): Block {
    return {
        clientId: 'p-test',
        name: PARAGRAPH_BLOCK_NAME,
        attributes: { content },
        innerBlocks: [],
    };
}

beforeEach(() => {
    clearRegistry();
    registerParagraphBlock();
});

afterEach(() => {
    clearRegistry();
});

describe('paragraph block registration', () => {
    it('registers ve/paragraph in the block registry', () => {
        expect(getBlock(PARAGRAPH_BLOCK_NAME)).toBeDefined();
    });
});

describe('paragraph block edit', () => {
    it('renders the initial content from attributes.content', () => {
        const block = makeParagraph('<p>Hello Tiptap</p>');

        render(
            <BlockTreeProvider blocks={[block]}>
                <RenderBlock block={block} />
            </BlockTreeProvider>
        );

        expect(screen.getByText('Hello Tiptap')).toBeInTheDocument();
    });

    it('writes back to attributes.content when the editor updates', async () => {
        const block = makeParagraph('<p>initial</p>');

        render(
            <BlockTreeProvider blocks={[block]}>
                <RenderBlock block={block} />
            </BlockTreeProvider>
        );

        const tiptap = document.querySelector<HTMLElement>('.ve-richtext.ProseMirror');
        expect(tiptap).not.toBeNull();

        const editor = getTiptapEditor(tiptap!);
        expect(editor).not.toBeNull();

        await act(async () => {
            editor!.commands.insertContentAt(0, 'edited ');
        });

        expect(typeof block.attributes.content).toBe('string');
        expect(block.attributes.content as string).toContain('edited');
        expect(block.attributes.content as string).toContain('initial');
    });

    it('renders as static, non-editable HTML when read-only', () => {
        const block = makeParagraph('<p>Preview only</p>');

        render(
            <BlockTreeProvider blocks={[block]}>
                <ReadOnlyProvider value={true}>
                    <RenderBlock block={block} />
                </ReadOnlyProvider>
            </BlockTreeProvider>
        );

        const wrapper = document.querySelector<HTMLElement>(
            '[data-block-name="ve/paragraph"]'
        );
        expect(wrapper).not.toBeNull();
        expect(wrapper!.getAttribute('data-read-only')).toBe('true');
        expect(wrapper!.getAttribute('contenteditable')).toBeNull();
        expect(screen.getByText('Preview only')).toBeInTheDocument();
        expect(document.querySelector('.ProseMirror')).toBeNull();
    });
});
