import { render, screen } from '@testing-library/react';
import { describe, expect, it } from 'vitest';
import {
    BlockContextProvider,
    useBlockContext,
    useBlockContextValue,
} from '../BlockContext';

function isNumber(value: unknown): value is number {
    return typeof value === 'number';
}

function isString(value: unknown): value is string {
    return typeof value === 'string';
}

function Reader() {
    const context = useBlockContext();
    return <div data-testid="context">{JSON.stringify(context)}</div>;
}

function TypedReader() {
    const postId = useBlockContextValue<number>('postId', isNumber);
    const missing = useBlockContextValue<string>('missing', isString);
    const wrongType = useBlockContextValue<number>('postType', isNumber);
    return (
        <div>
            <span data-testid="postId">{String(postId)}</span>
            <span data-testid="missing">{String(missing)}</span>
            <span data-testid="wrongType">{String(wrongType)}</span>
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

    it('returns an empty frozen object outside of any provider', () => {
        render(<Reader />);

        expect(screen.getByTestId('context').textContent).toBe('{}');
    });
});

describe('useBlockContextValue', () => {
    it('returns typed values that pass the guard', () => {
        render(
            <BlockContextProvider value={{ postId: 42, postType: 'post' }}>
                <TypedReader />
            </BlockContextProvider>
        );

        expect(screen.getByTestId('postId').textContent).toBe('42');
    });

    it('returns undefined for missing keys', () => {
        render(
            <BlockContextProvider value={{ postId: 42 }}>
                <TypedReader />
            </BlockContextProvider>
        );

        expect(screen.getByTestId('missing').textContent).toBe('undefined');
    });

    it('returns undefined when the guard rejects the value', () => {
        render(
            <BlockContextProvider value={{ postId: 42, postType: 'post' }}>
                <TypedReader />
            </BlockContextProvider>
        );

        expect(screen.getByTestId('wrongType').textContent).toBe('undefined');
    });

    it('returns a stable reference across renders when raw value is unchanged', () => {
        const captured: Array<{ value: { id: number } | undefined }> = [];

        function Probe({ tick }: { tick: number }) {
            const value = useBlockContextValue<{ id: number }>(
                'obj',
                (v): v is { id: number } =>
                    typeof v === 'object' && v !== null && 'id' in v
            );
            captured.push({ value });
            return <span>{tick}</span>;
        }

        const obj = { id: 1 };
        const { rerender } = render(
            <BlockContextProvider value={{ obj }}>
                <Probe tick={1} />
            </BlockContextProvider>
        );

        rerender(
            <BlockContextProvider value={{ obj }}>
                <Probe tick={2} />
            </BlockContextProvider>
        );

        expect(captured.length).toBeGreaterThanOrEqual(2);
        expect(captured[0].value).toBe(captured[captured.length - 1].value);
    });
});
