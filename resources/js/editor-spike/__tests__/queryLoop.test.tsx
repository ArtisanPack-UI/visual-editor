import { afterEach, beforeEach, describe, expect, it } from 'vitest';
import { act, fireEvent, render, screen, within } from '@testing-library/react';
import type { Block } from '../mocks/blockTree';
import { BlockTreeProvider } from '../primitives/BlockTreeContext';
import { RenderBlock } from '../primitives/useInnerBlocksProps';
import { clearRegistry, getBlock } from '../registry';
import { PARAGRAPH_BLOCK_NAME, registerParagraphBlock } from '../blocks/paragraph';
import { POST_TITLE_BLOCK_NAME, registerPostTitleBlock } from '../blocks/post-title';
import { QUERY_LOOP_BLOCK_NAME, registerQueryLoopBlock } from '../blocks/query-loop';
import { getTiptapEditor } from '../richtext/useTiptap';

function makeQueryLoopTree(): Block[] {
    return [
        {
            clientId: 'ql-1',
            name: QUERY_LOOP_BLOCK_NAME,
            attributes: { postType: 'post', perPage: 5 },
            innerBlocks: [
                {
                    clientId: 'ql-1-title',
                    name: POST_TITLE_BLOCK_NAME,
                    attributes: { level: 2 },
                    innerBlocks: [],
                },
                {
                    clientId: 'ql-1-paragraph',
                    name: PARAGRAPH_BLOCK_NAME,
                    attributes: { content: '<p>Shared template body.</p>' },
                    innerBlocks: [],
                },
            ],
        },
    ];
}

beforeEach(() => {
    clearRegistry();
    registerParagraphBlock();
    registerPostTitleBlock();
    registerQueryLoopBlock();
});

afterEach(() => {
    clearRegistry();
});

describe('query-loop block registration', () => {
    it('registers ve/query-loop in the block registry', () => {
        expect(getBlock(QUERY_LOOP_BLOCK_NAME)).toBeDefined();
    });
});

describe('query-loop block edit', () => {
    it('renders one post article per mock post returned by the query', () => {
        const tree = makeQueryLoopTree();

        render(
            <BlockTreeProvider blocks={tree}>
                <RenderBlock block={tree[0]} />
            </BlockTreeProvider>
        );

        const articles = document.querySelectorAll('[data-post-id]');
        expect(articles).toHaveLength(5);
    });

    it('renders the correct per-post title via BlockContext (not all the same)', () => {
        const tree = makeQueryLoopTree();

        render(
            <BlockTreeProvider blocks={tree}>
                <RenderBlock block={tree[0]} />
            </BlockTreeProvider>
        );

        expect(screen.getByText('Lorem ipsum dolor sit amet')).toBeInTheDocument();
        expect(screen.getByText('Sed do eiusmod tempor incididunt')).toBeInTheDocument();
        expect(screen.getByText('Duis aute irure dolor in reprehenderit')).toBeInTheDocument();
        expect(screen.getByText('Curabitur pretium tincidunt lacus')).toBeInTheDocument();
        expect(
            screen.getByText('Praesent dapibus, neque id cursus faucibus')
        ).toBeInTheDocument();
    });

    it('marks the first post as active by default and all others as inactive', () => {
        const tree = makeQueryLoopTree();

        render(
            <BlockTreeProvider blocks={tree}>
                <RenderBlock block={tree[0]} />
            </BlockTreeProvider>
        );

        const activePosts = document.querySelectorAll('[data-active="true"]');
        const inactivePosts = document.querySelectorAll('[data-active="false"]');
        expect(activePosts).toHaveLength(1);
        expect(inactivePosts).toHaveLength(4);
        expect(activePosts[0].getAttribute('data-post-id')).toBe('1');
    });

    it('renders a live Tiptap editor inside the active post only', () => {
        const tree = makeQueryLoopTree();

        render(
            <BlockTreeProvider blocks={tree}>
                <RenderBlock block={tree[0]} />
            </BlockTreeProvider>
        );

        const tiptapEditors = document.querySelectorAll('.ve-richtext.ProseMirror');
        expect(tiptapEditors).toHaveLength(1);

        const activePost = document.querySelector<HTMLElement>('[data-active="true"]');
        expect(activePost).not.toBeNull();
        expect(activePost!.querySelector('.ve-richtext.ProseMirror')).not.toBeNull();
    });

    it('activates a previously-inactive post when its Edit button is clicked', () => {
        const tree = makeQueryLoopTree();

        render(
            <BlockTreeProvider blocks={tree}>
                <RenderBlock block={tree[0]} />
            </BlockTreeProvider>
        );

        const thirdPost = document.querySelector<HTMLElement>('[data-post-id="3"]');
        expect(thirdPost).not.toBeNull();
        const activateButton = within(thirdPost!).getByRole('button', { name: /edit this post/i });

        fireEvent.click(activateButton);

        expect(
            document.querySelector('[data-post-id="3"]')!.getAttribute('data-active')
        ).toBe('true');
        expect(
            document.querySelector('[data-post-id="1"]')!.getAttribute('data-active')
        ).toBe('false');

        // Only the newly active post has a Tiptap editor.
        const tiptapEditors = document.querySelectorAll('.ve-richtext.ProseMirror');
        expect(tiptapEditors).toHaveLength(1);
        expect(
            document.querySelector('[data-post-id="3"]')!.querySelector('.ve-richtext.ProseMirror')
        ).not.toBeNull();
    });

    it('propagates edits in the active post to every inactive preview (shared template)', async () => {
        const tree = makeQueryLoopTree();

        render(
            <BlockTreeProvider blocks={tree}>
                <RenderBlock block={tree[0]} />
            </BlockTreeProvider>
        );

        const tiptap = document.querySelector<HTMLElement>('.ve-richtext.ProseMirror');
        expect(tiptap).not.toBeNull();

        const editor = getTiptapEditor(tiptap!);
        expect(editor).not.toBeNull();

        await act(async () => {
            editor!.commands.insertContentAt(0, 'SYNCED ');
        });

        // All four inactive previews should now show the edited text.
        const previews = document.querySelectorAll(
            '[data-active="false"] [data-block-name="ve/paragraph"]'
        );
        expect(previews).toHaveLength(4);
        previews.forEach((preview) => {
            expect(preview.textContent).toContain('SYNCED');
        });
    });
});
