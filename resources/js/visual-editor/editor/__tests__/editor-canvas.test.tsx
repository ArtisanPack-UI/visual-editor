/**
 * `EditorCanvas` mounts the post title above the iframed `BlockCanvas`
 * (#347). The real `BlockCanvas` mounts an iframe and pulls from a
 * Gutenberg data store — neither of which jsdom reproduces reliably —
 * so `@wordpress/block-editor` is stubbed here (the same approach
 * `site-editor/__tests__/canvas-frame.test.tsx` takes). The stubs let
 * the suite focus on this component's wiring:
 *   - the canvas renders inside a `BlockCanvas` (iframe mount intent);
 *   - `BlockCanvas` receives the assembled `canvasStyles` bundle (style
 *     injection into the iframe);
 *   - `PostTitle` renders above `BlockCanvas`, and only when supported;
 *   - cms-framework entities get a `BlockContextProvider` wrap.
 */

import { render, screen } from '@testing-library/react';
import { describe, expect, it, vi } from 'vitest';
import type { ReactNode } from 'react';

const blockCanvasProps = vi.fn();

vi.mock('@wordpress/block-editor', () => ({
    BlockCanvas: (props: { children: ReactNode; styles: unknown; height: string }): JSX.Element => {
        blockCanvasProps(props);

        return <div data-testid="ap-stub-block-canvas">{props.children}</div>;
    },
    BlockContextProvider: ({
        children,
        value,
    }: {
        children: ReactNode;
        value: unknown;
    }): JSX.Element => (
        <div
            data-testid="ap-stub-block-context-provider"
            data-context={JSON.stringify(value)}
        >
            {children}
        </div>
    ),
    BlockList: (): JSX.Element => <div data-testid="ap-stub-block-list" />,
    ObserveTyping: ({ children }: { children: ReactNode }): JSX.Element => (
        <div data-testid="ap-stub-observe-typing">{children}</div>
    ),
}));

vi.mock('../post-title', () => ({
    PostTitle: ({
        value,
        onChange,
    }: {
        value: string;
        onChange: (next: string) => void;
    }): JSX.Element => (
        <input
            data-testid="ap-stub-post-title"
            value={value}
            onChange={(event) => onChange(event.target.value)}
        />
    ),
}));

import { canvasStyles } from '../canvas-styles';
import { EditorCanvas } from '../editor-canvas';

describe('EditorCanvas', () => {
    it('renders the block list inside a BlockCanvas iframe', () => {
        render(
            <EditorCanvas
                showTitle
                title="Hello"
                onTitleChange={() => undefined}
                blockContext={null}
            />
        );

        const canvas = screen.getByTestId('ap-stub-block-canvas');

        expect(canvas).toBeInTheDocument();
        expect(canvas).toContainElement(
            screen.getByTestId('ap-stub-block-list')
        );
    });

    it('hands the assembled canvasStyles bundle to BlockCanvas for iframe injection', () => {
        blockCanvasProps.mockClear();

        render(
            <EditorCanvas
                showTitle
                title=""
                onTitleChange={() => undefined}
                blockContext={null}
            />
        );

        expect(blockCanvasProps).toHaveBeenCalledTimes(1);
        expect(blockCanvasProps.mock.calls[0]?.[0]).toMatchObject({
            styles: canvasStyles,
            height: '100%',
        });
    });

    it('renders PostTitle above the BlockCanvas when the document type supports a title', () => {
        render(
            <EditorCanvas
                showTitle
                title="My post"
                onTitleChange={() => undefined}
                blockContext={null}
            />
        );

        const title = screen.getByTestId('ap-stub-post-title');
        const canvas = screen.getByTestId('ap-stub-block-canvas');

        expect(title).toHaveValue('My post');
        // Source order: the title precedes the canvas in the DOM.
        expect(
            title.compareDocumentPosition(canvas) &
                Node.DOCUMENT_POSITION_FOLLOWING
        ).toBeTruthy();
    });

    it('omits PostTitle when the document type has no title support', () => {
        render(
            <EditorCanvas
                showTitle={false}
                title=""
                onTitleChange={() => undefined}
                blockContext={null}
            />
        );

        expect(
            screen.queryByTestId('ap-stub-post-title')
        ).not.toBeInTheDocument();
    });

    it('wraps the block list in a BlockContextProvider for cms-framework entities', () => {
        render(
            <EditorCanvas
                showTitle
                title=""
                onTitleChange={() => undefined}
                blockContext={{ postType: 'page', postId: 42 }}
            />
        );

        const provider = screen.getByTestId('ap-stub-block-context-provider');

        expect(provider).toHaveAttribute(
            'data-context',
            JSON.stringify({ postType: 'page', postId: 42 })
        );
        expect(provider).toContainElement(
            screen.getByTestId('ap-stub-block-list')
        );
    });

    it('skips the BlockContextProvider wrap when there is no entity context', () => {
        render(
            <EditorCanvas
                showTitle
                title=""
                onTitleChange={() => undefined}
                blockContext={null}
            />
        );

        expect(
            screen.queryByTestId('ap-stub-block-context-provider')
        ).not.toBeInTheDocument();
        expect(screen.getByTestId('ap-stub-block-list')).toBeInTheDocument();
    });
});
