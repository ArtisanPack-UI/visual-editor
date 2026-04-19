import { render, screen } from '@testing-library/react';
import { afterEach, beforeEach, describe, expect, it } from 'vitest';
import { BlockPreview, useBlockPreview } from '../useBlockPreview';
import { InnerBlocks } from '../useInnerBlocksProps';
import { useReadOnly } from '../ReadOnlyContext';
import {
    clearRegistry,
    registerBlock,
    type BlockEditProps,
} from '../../registry';
import type { Block } from '../../store';

function Paragraph({ attributes, clientId }: BlockEditProps) {
    const readOnly = useReadOnly();
    const content = String(attributes.content ?? '');

    if (readOnly) {
        return (
            <p data-client-id={clientId} data-read-only="true">
                {content}
            </p>
        );
    }

    return (
        <p
            data-client-id={clientId}
            contentEditable
            suppressContentEditableWarning
            onInput={() => {}}
        >
            {content}
        </p>
    );
}

function ClickButton({ clientId }: BlockEditProps) {
    return (
        <button
            type="button"
            data-client-id={clientId}
            onClick={() => {
                throw new Error('button handler should not fire inside a preview');
            }}
        >
            click me
        </button>
    );
}

function Group({ block }: BlockEditProps) {
    return <InnerBlocks parentClientId={block.clientId} />;
}

const tree: Block[] = [
    {
        clientId: 'p1',
        name: 've/paragraph',
        attributes: { content: 'Hello preview' },
        innerBlocks: [],
    },
    {
        clientId: 'group-root',
        name: 've/group',
        attributes: {},
        innerBlocks: [
            {
                clientId: 'nested-p',
                name: 've/paragraph',
                attributes: { content: 'Nested line' },
                innerBlocks: [],
            },
            {
                clientId: 'nested-button',
                name: 've/button',
                attributes: {},
                innerBlocks: [],
            },
        ],
    },
];

beforeEach(() => {
    clearRegistry();
    registerBlock({ name: 've/paragraph', edit: Paragraph });
    registerBlock({ name: 've/button', edit: ClickButton });
    registerBlock({ name: 've/group', edit: Group });
});

afterEach(() => {
    clearRegistry();
});

describe('BlockPreview', () => {
    it('renders a paragraph block read-only (no contenteditable)', () => {
        render(<BlockPreview blocks={[tree[0]]} />);

        const paragraph = screen.getByText('Hello preview');
        expect(paragraph.getAttribute('contenteditable')).toBeNull();
        expect(paragraph.getAttribute('data-read-only')).toBe('true');
    });

    it('renders nested blocks via InnerBlocks inside the preview', () => {
        render(<BlockPreview blocks={[tree[1]]} />);

        expect(screen.getByText('Nested line')).toBeInTheDocument();
        expect(screen.getByText('click me')).toBeInTheDocument();

        const nested = screen.getByText('Nested line');
        expect(nested.getAttribute('contenteditable')).toBeNull();
        expect(nested.getAttribute('data-read-only')).toBe('true');
    });

    // Skipped under React 18 (#311): React 19 emits the boolean `inert` prop as
    // an HTML attribute; React 18 sets it via the IDL `el.inert` setter, which
    // jsdom 25 does not reflect back onto `hasAttribute('inert')`. Pinning to
    // React 18 matches Gutenberg's peer range, and this legacy tree is deleted
    // at M15, so rewriting the runtime (and the test below that asserts
    // `previewProps.inert === true`) isn't worth the churn. Restore when the
    // `_legacy` tree is removed.
    it.skip('applies inert, aria-hidden, pointer-events:none, and user-select:none', () => {
        const { container } = render(
            <BlockPreview blocks={[tree[0]]} className="ve-preview" />
        );

        const wrapper = container.querySelector('[data-block-preview]') as HTMLElement | null;
        expect(wrapper).not.toBeNull();
        expect(wrapper!.classList.contains('ve-preview')).toBe(true);
        expect(wrapper!.style.pointerEvents).toBe('none');
        expect(wrapper!.style.userSelect).toBe('none');
        expect(wrapper!.hasAttribute('inert')).toBe(true);
        expect(wrapper!.getAttribute('aria-hidden')).toBe('true');
    });

    it('renders multiple root blocks', () => {
        render(<BlockPreview blocks={tree} />);

        expect(screen.getByText('Hello preview')).toBeInTheDocument();
        expect(screen.getByText('Nested line')).toBeInTheDocument();
    });
});

describe('useBlockPreview', () => {
    it('returns previewProps spreadable onto a wrapper element', () => {
        let captured: ReturnType<typeof useBlockPreview> | null = null;

        function Probe() {
            captured = useBlockPreview({ blocks: [tree[0]] });
            return <div {...captured.previewProps} />;
        }

        const { container } = render(<Probe />);

        expect(captured).not.toBeNull();
        expect(captured!.previewProps['data-block-preview']).toBe(true);
        expect(captured!.previewProps['aria-hidden']).toBe('true');
        expect(captured!.previewProps.style.pointerEvents).toBe('none');
        expect(captured!.previewProps.style.userSelect).toBe('none');
        expect(captured!.previewProps.inert).toBe(true);
        expect(container.querySelector('[data-block-preview]')).not.toBeNull();
    });
});
