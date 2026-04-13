import { afterEach, beforeEach, describe, expect, it } from 'vitest';
import { render, screen } from '@testing-library/react';
import type { Block } from '../mocks/blockTree';
import {
    clearRegistry,
    registerBlock,
    type BlockEditProps,
} from '../registry';
import { useBlockContextValue } from '../primitives/BlockContext';
import { BlockTreeProvider } from '../primitives/BlockTreeContext';
import { InnerBlocks, useInnerBlocksProps } from '../primitives/useInnerBlocksProps';

const tree: Block[] = [
    {
        clientId: 'parent',
        name: 've/group',
        attributes: { tag: 'section' },
        innerBlocks: [
            {
                clientId: 'child-1',
                name: 've/paragraph',
                attributes: { content: 'First paragraph' },
                innerBlocks: [],
            },
            {
                clientId: 'child-2',
                name: 've/paragraph',
                attributes: { content: 'Second paragraph' },
                innerBlocks: [],
            },
        ],
    },
    {
        clientId: 'context-root',
        name: 've/group',
        attributes: {},
        innerBlocks: [
            {
                clientId: 'context-parent',
                name: 've/query-loop',
                attributes: { postId: 42, postType: 'post' },
                innerBlocks: [
                    {
                        clientId: 'context-child',
                        name: 've/post-id-reader',
                        attributes: {},
                        innerBlocks: [],
                    },
                ],
            },
        ],
    },
];

function Paragraph({ attributes, clientId }: BlockEditProps) {
    return <p data-client-id={clientId}>{String(attributes.content ?? '')}</p>;
}

function PostIdReader({ clientId }: BlockEditProps) {
    const postId = useBlockContextValue<number>('postId');
    return <span data-client-id={clientId}>postId={String(postId)}</span>;
}

beforeEach(() => {
    clearRegistry();
    registerBlock({ name: 've/paragraph', edit: Paragraph });
    registerBlock({
        name: 've/query-loop',
        edit: ({ block }) => <InnerBlocks parentClientId={block.clientId} />,
        providesContext: (attributes) => ({
            postId: attributes.postId,
            postType: attributes.postType,
        }),
    });
    registerBlock({ name: 've/post-id-reader', edit: PostIdReader });
});

afterEach(() => {
    clearRegistry();
});

describe('InnerBlocks', () => {
    it('renders each child block from the parent in the mock tree', () => {
        render(
            <BlockTreeProvider blocks={tree}>
                <InnerBlocks parentClientId="parent" />
            </BlockTreeProvider>
        );

        expect(screen.getByText('First paragraph')).toBeInTheDocument();
        expect(screen.getByText('Second paragraph')).toBeInTheDocument();
    });

    it('renders an empty wrapper when the parent has no children', () => {
        render(
            <BlockTreeProvider blocks={[{ clientId: 'lonely', name: 've/group', attributes: {}, innerBlocks: [] }]}>
                <InnerBlocks parentClientId="lonely" />
            </BlockTreeProvider>
        );

        const wrapper = document.querySelector('[data-parent-client-id="lonely"]');
        expect(wrapper).not.toBeNull();
        expect(wrapper?.children.length).toBe(0);
    });

    it('renders a fallback for unknown block types', () => {
        clearRegistry();
        render(
            <BlockTreeProvider blocks={tree}>
                <InnerBlocks parentClientId="parent" />
            </BlockTreeProvider>
        );

        const missing = document.querySelectorAll('[data-block-missing="ve/paragraph"]');
        expect(missing.length).toBe(2);
    });

    it('passes providesContext values to descendants via BlockContext', () => {
        render(
            <BlockTreeProvider blocks={tree}>
                <InnerBlocks parentClientId="context-root" />
            </BlockTreeProvider>
        );

        expect(screen.getByText('postId=42')).toBeInTheDocument();
    });

    it('forwards className and parent id on the wrapper element', () => {
        render(
            <BlockTreeProvider blocks={tree}>
                <InnerBlocks parentClientId="parent" className="ve-inner-blocks" />
            </BlockTreeProvider>
        );

        const wrapper = document.querySelector('[data-parent-client-id="parent"]');
        expect(wrapper).not.toBeNull();
        expect(wrapper?.classList.contains('ve-inner-blocks')).toBe(true);
    });
});

describe('useInnerBlocksProps', () => {
    it('returns props with a ref, children, and the parent id', () => {
        let captured: ReturnType<typeof useInnerBlocksProps> | null = null;

        function Probe() {
            captured = useInnerBlocksProps('parent', { className: 'probe' });
            return null;
        }

        render(
            <BlockTreeProvider blocks={tree}>
                <Probe />
            </BlockTreeProvider>
        );

        expect(captured).not.toBeNull();
        expect(captured!['data-parent-client-id']).toBe('parent');
        expect(captured!.className).toBe('probe');
        expect(captured!.ref).toBeDefined();
        expect(captured!.children).toBeDefined();
    });
});
