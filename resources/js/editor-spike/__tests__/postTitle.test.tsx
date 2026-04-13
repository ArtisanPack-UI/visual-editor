import { afterEach, beforeEach, describe, expect, it } from 'vitest';
import { render, screen } from '@testing-library/react';
import type { Block } from '../mocks/blockTree';
import { BlockContextProvider } from '../primitives/BlockContext';
import { BlockTreeProvider } from '../primitives/BlockTreeContext';
import { RenderBlock } from '../primitives/useInnerBlocksProps';
import { clearRegistry, getBlock } from '../registry';
import {
    POST_TITLE_BLOCK_NAME,
    registerPostTitleBlock,
} from '../blocks/post-title';

function makePostTitle(clientId = 'pt-test'): Block {
    return {
        clientId,
        name: POST_TITLE_BLOCK_NAME,
        attributes: { level: 2 },
        innerBlocks: [],
    };
}

beforeEach(() => {
    clearRegistry();
    registerPostTitleBlock();
});

afterEach(() => {
    clearRegistry();
});

describe('post-title block registration', () => {
    it('registers ve/post-title in the block registry', () => {
        expect(getBlock(POST_TITLE_BLOCK_NAME)).toBeDefined();
    });

    it('declares usesContext for postId and postType', () => {
        const definition = getBlock(POST_TITLE_BLOCK_NAME);
        expect(definition?.usesContext).toEqual(['postId', 'postType']);
    });
});

describe('post-title block edit', () => {
    it('renders the post title from the context post', () => {
        const block = makePostTitle();

        render(
            <BlockTreeProvider blocks={[block]}>
                <BlockContextProvider value={{ postId: 1, postType: 'post' }}>
                    <RenderBlock block={block} />
                </BlockContextProvider>
            </BlockTreeProvider>
        );

        expect(screen.getByText('Lorem ipsum dolor sit amet')).toBeInTheDocument();
    });

    it('renders a placeholder when no postId is in context', () => {
        const block = makePostTitle();

        render(
            <BlockTreeProvider blocks={[block]}>
                <RenderBlock block={block} />
            </BlockTreeProvider>
        );

        const heading = screen.getByText('[post title]');
        expect(heading).toBeInTheDocument();
        expect(heading.getAttribute('data-placeholder')).toBe('true');
    });

    it('shows different titles when wrapped in different BlockContextProviders', () => {
        const blockA = makePostTitle('pt-a');
        const blockB = makePostTitle('pt-b');

        render(
            <BlockTreeProvider blocks={[blockA, blockB]}>
                <BlockContextProvider value={{ postId: 1, postType: 'post' }}>
                    <RenderBlock block={blockA} />
                </BlockContextProvider>
                <BlockContextProvider value={{ postId: 2, postType: 'post' }}>
                    <RenderBlock block={blockB} />
                </BlockContextProvider>
            </BlockTreeProvider>
        );

        expect(screen.getByText('Lorem ipsum dolor sit amet')).toBeInTheDocument();
        expect(screen.getByText('Sed do eiusmod tempor incididunt')).toBeInTheDocument();
    });
});
