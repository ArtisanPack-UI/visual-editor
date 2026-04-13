import { render, screen } from '@testing-library/react';
import {
    BlockContextProvider,
    useBlockContext,
    useBlockContextValue,
} from '../primitives/BlockContext';

function Reader() {
    const context = useBlockContext();
    return <div data-testid="context">{JSON.stringify(context)}</div>;
}

function TypedReader() {
    const postId = useBlockContextValue<number>('postId');
    const missing = useBlockContextValue<string>('missing');
    return (
        <div>
            <span data-testid="postId">{String(postId)}</span>
            <span data-testid="missing">{String(missing)}</span>
        </div>
    );
}

describe('BlockContextProvider', () => {
    it('exposes the provided value to descendants', () => {
        render(
            <BlockContextProvider value={{ postId: 1, postType: 'post' }}>
                <Reader />
            </BlockContextProvider>
        );

        expect(screen.getByTestId('context').textContent).toBe(
            JSON.stringify({ postId: 1, postType: 'post' })
        );
    });

    it('merges nested provider values with parent context', () => {
        render(
            <BlockContextProvider value={{ postId: 1, postType: 'post' }}>
                <BlockContextProvider value={{ extra: 'data' }}>
                    <Reader />
                </BlockContextProvider>
            </BlockContextProvider>
        );

        expect(screen.getByTestId('context').textContent).toBe(
            JSON.stringify({ postId: 1, postType: 'post', extra: 'data' })
        );
    });

    it('lets a nested provider override a parent key', () => {
        render(
            <BlockContextProvider value={{ postId: 1, postType: 'post' }}>
                <BlockContextProvider value={{ postType: 'page' }}>
                    <Reader />
                </BlockContextProvider>
            </BlockContextProvider>
        );

        expect(screen.getByTestId('context').textContent).toBe(
            JSON.stringify({ postId: 1, postType: 'page' })
        );
    });

    it('returns an empty object outside of any provider', () => {
        render(<Reader />);

        expect(screen.getByTestId('context').textContent).toBe('{}');
    });

    it('useBlockContextValue returns typed values and undefined for missing keys', () => {
        render(
            <BlockContextProvider value={{ postId: 42 }}>
                <TypedReader />
            </BlockContextProvider>
        );

        expect(screen.getByTestId('postId').textContent).toBe('42');
        expect(screen.getByTestId('missing').textContent).toBe('undefined');
    });
});
