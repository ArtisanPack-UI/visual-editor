import { act, fireEvent, render, screen } from '@testing-library/react';
import { afterEach, beforeEach, describe, expect, it } from 'vitest';
import {
    InnerBlocks,
    useInnerBlocksProps,
} from '../useInnerBlocksProps';
import { EditorStoreProvider } from '../EditorStoreContext';
import { useBlockContextValue } from '../BlockContext';
import {
    clearRegistry,
    registerBlock,
    type BlockEditProps,
} from '../../registry';
import { createEditorStore, type Block, type EditorStore } from '../../store';

function makeBlock(
    clientId: string,
    name = 've/paragraph',
    overrides: Partial<Block> = {}
): Block {
    return {
        clientId,
        name,
        attributes: {},
        innerBlocks: [],
        ...overrides,
    };
}

function Paragraph({ attributes, clientId }: BlockEditProps) {
    return <p data-client-id={clientId}>{String(attributes.content ?? '')}</p>;
}

function Group({ block }: BlockEditProps) {
    return <InnerBlocks parentClientId={block.clientId} />;
}

function PostIdReader({ clientId }: BlockEditProps) {
    const postId = useBlockContextValue<number>(
        'postId',
        (v): v is number => typeof v === 'number'
    );
    return <span data-client-id={clientId}>postId={String(postId)}</span>;
}

function makeTree(): Block[] {
    return [
        makeBlock('parent', 've/group', {
            innerBlocks: [
                makeBlock('child-1', 've/paragraph', {
                    attributes: { content: 'First paragraph' },
                }),
                makeBlock('child-2', 've/paragraph', {
                    attributes: { content: 'Second paragraph' },
                }),
            ],
        }),
        makeBlock('context-parent', 've/query-loop', {
            attributes: { postId: 42 },
            innerBlocks: [makeBlock('context-child', 've/post-id-reader')],
        }),
    ];
}

function renderWithStore(store: EditorStore, ui: React.ReactElement) {
    return render(<EditorStoreProvider store={store}>{ui}</EditorStoreProvider>);
}

beforeEach(() => {
    clearRegistry();
    registerBlock({ name: 've/paragraph', edit: Paragraph });
    registerBlock({ name: 've/group', edit: Group });
    registerBlock({
        name: 've/query-loop',
        edit: Group,
        providesContext: (attrs) => ({ postId: attrs.postId }),
    });
    registerBlock({ name: 've/post-id-reader', edit: PostIdReader });
});

afterEach(() => {
    clearRegistry();
});

describe('InnerBlocks', () => {
    it('renders each child block from the store', () => {
        const store = createEditorStore(makeTree());
        renderWithStore(store, <InnerBlocks parentClientId="parent" />);

        expect(screen.getByText('First paragraph')).toBeInTheDocument();
        expect(screen.getByText('Second paragraph')).toBeInTheDocument();
    });

    it('renders an empty wrapper when the parent has no children', () => {
        const store = createEditorStore([makeBlock('lonely', 've/group')]);
        renderWithStore(store, <InnerBlocks parentClientId="lonely" />);

        const wrapper = document.querySelector('[data-parent-client-id="lonely"]');
        expect(wrapper).not.toBeNull();
        expect(wrapper?.children.length).toBe(0);
    });

    it('renders a fallback for unknown block types', () => {
        const store = createEditorStore(makeTree());
        clearRegistry();
        registerBlock({ name: 've/group', edit: Group });
        renderWithStore(store, <InnerBlocks parentClientId="parent" />);

        const missing = document.querySelectorAll('[data-block-missing="ve/paragraph"]');
        expect(missing.length).toBe(2);
    });

    it('passes providesContext values to descendants', () => {
        const store = createEditorStore(makeTree());
        renderWithStore(store, <InnerBlocks parentClientId={null} />);

        expect(screen.getByText('postId=42')).toBeInTheDocument();
    });

    it('forwards className and parent id on the wrapper element', () => {
        const store = createEditorStore(makeTree());
        renderWithStore(
            store,
            <InnerBlocks parentClientId="parent" className="ve-inner-blocks" />
        );

        const wrapper = document.querySelector('[data-parent-client-id="parent"]');
        expect(wrapper?.classList.contains('ve-inner-blocks')).toBe(true);
    });

    it('reactively re-renders when the store changes', () => {
        const store = createEditorStore(makeTree());
        renderWithStore(store, <InnerBlocks parentClientId="parent" />);

        act(() => {
            store.getState().updateBlockAttributes('child-1', { content: 'Updated' });
        });

        expect(screen.getByText('Updated')).toBeInTheDocument();
    });
});

describe('useInnerBlocksProps', () => {
    it('returns a stable innerBlocksProps object with the expected shape', () => {
        const store = createEditorStore(makeTree());
        let captured: ReturnType<typeof useInnerBlocksProps> | null = null;

        function Probe() {
            captured = useInnerBlocksProps('parent', { className: 'probe' });
            return null;
        }

        renderWithStore(store, <Probe />);

        expect(captured).not.toBeNull();
        expect(captured!.innerBlocksProps['data-parent-client-id']).toBe('parent');
        expect(captured!.innerBlocksProps.className).toBe('probe');
        expect(captured!.innerBlocksProps.ref).toBeDefined();
        expect(captured!.innerBlocksProps.children).toBeDefined();
        expect(typeof captured!.appendBlock).toBe('function');
        expect(typeof captured!.removeBlock).toBe('function');
        expect(typeof captured!.moveBlock).toBe('function');
    });

    it('appendBlock inserts a block into the parent via the store', () => {
        const store = createEditorStore(makeTree());
        let captured: ReturnType<typeof useInnerBlocksProps> | null = null;

        function Probe() {
            captured = useInnerBlocksProps('parent');
            return null;
        }

        renderWithStore(store, <Probe />);

        act(() => {
            captured!.appendBlock(
                makeBlock('child-3', 've/paragraph', {
                    attributes: { content: 'Third paragraph' },
                })
            );
        });

        const parent = store.getState().blocks.find((b) => b.clientId === 'parent');
        expect(parent?.innerBlocks.map((b) => b.clientId)).toEqual([
            'child-1',
            'child-2',
            'child-3',
        ]);
    });

    it('removeBlock removes a child via the store', () => {
        const store = createEditorStore(makeTree());
        let captured: ReturnType<typeof useInnerBlocksProps> | null = null;

        function Probe() {
            captured = useInnerBlocksProps('parent');
            return null;
        }

        renderWithStore(store, <Probe />);

        act(() => {
            captured!.removeBlock('child-1');
        });

        const parent = store.getState().blocks.find((b) => b.clientId === 'parent');
        expect(parent?.innerBlocks.map((b) => b.clientId)).toEqual(['child-2']);
    });

    it('moveBlock reorders top-level blocks via the store', () => {
        const store = createEditorStore([
            makeBlock('a', 've/paragraph'),
            makeBlock('b', 've/paragraph'),
            makeBlock('c', 've/paragraph'),
        ]);
        let captured: ReturnType<typeof useInnerBlocksProps> | null = null;

        function Probe() {
            captured = useInnerBlocksProps(null);
            return null;
        }

        renderWithStore(store, <Probe />);

        act(() => {
            captured!.moveBlock('a', 2);
        });

        expect(store.getState().blocks.map((b) => b.clientId)).toEqual(['b', 'c', 'a']);
    });

    it('selection propagates to the store when a child block is clicked', () => {
        const store = createEditorStore(makeTree());
        renderWithStore(store, <InnerBlocks parentClientId="parent" />);

        const paragraph = screen.getByText('First paragraph');
        fireEvent.click(paragraph);

        expect(store.getState().selection.clientId).toBe('child-1');
    });
});
