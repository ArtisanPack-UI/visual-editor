import { render } from '@testing-library/react';
import { afterEach, beforeEach, describe, expect, it, vi } from 'vitest';
import type { ComponentType } from 'react';

const filters: Array<{
    hook: string;
    namespace: string;
    callback: (component: unknown) => unknown;
}> = [];

vi.mock('@wordpress/hooks', () => ({
    addFilter: (
        hook: string,
        namespace: string,
        callback: (component: unknown) => unknown
    ) => {
        filters.push({ hook, namespace, callback });
    },
}));

// `@wordpress/block-editor` cannot load under vitest (its ESM build imports
// `diff` without extensions). Stand in a faithful port of upstream
// `getGapCSSValue` (hooks/gap.js) + `getSpacingPresetCssVar` so the preset
// expansion and per-axis defaulting are still exercised.
vi.mock('@wordpress/block-editor', () => {
    const presetVar = (value?: string): string | undefined => {
        if (!value) {
            return undefined;
        }

        const slug = value.match(/var:preset\|spacing\|(.+)/);

        return slug ? `var(--wp--preset--spacing--${slug[1]})` : value;
    };

    return {
        __experimentalGetGapCSSValue: (
            blockGap: string | { top?: string; left?: string } | undefined,
            defaultValue = '0'
        ): string | null => {
            if (!blockGap) {
                return null;
            }

            const isString = typeof blockGap === 'string';
            const row = presetVar(isString ? blockGap : blockGap.top) || defaultValue;
            const column = presetVar(isString ? blockGap : blockGap.left) || defaultValue;

            return row === column ? row : `${row} ${column}`;
        },
    };
});

// Mirror the production sentinel so it can be cleared between tests.
const REGISTERED_KEY = Symbol.for(
    'artisanpack-ui.visual-editor.navigation-block-gap.registered'
);

beforeEach(() => {
    filters.length = 0;
    delete (globalThis as Record<symbol, unknown>)[REGISTERED_KEY];
    vi.resetModules();
});

afterEach(() => {
    delete (globalThis as Record<symbol, unknown>)[REGISTERED_KEY];
    vi.resetModules();
});

interface InnerProps {
    wrapperProps?: { style?: Record<string, unknown> } & Record<string, unknown>;
}

async function renderFiltered(props: Record<string, unknown>): Promise<InnerProps> {
    const mod = await import('../navigation-block-gap');
    mod.registerNavigationBlockGap();

    const filter = filters[0];

    if (filter === undefined) {
        throw new Error('addFilter was not called');
    }

    let received: InnerProps = {};
    const Inner = (innerProps: InnerProps): null => {
        received = innerProps;

        return null;
    };
    const Wrapped = filter.callback(Inner) as ComponentType<Record<string, unknown>>;

    render(<Wrapped {...props} />);

    return received;
}

describe('registerNavigationBlockGap', () => {
    it('registers the editor.BlockListBlock filter exactly once', async () => {
        const mod = await import('../navigation-block-gap');

        mod.registerNavigationBlockGap();
        mod.registerNavigationBlockGap();

        expect(filters).toHaveLength(1);
        expect(filters[0]?.hook).toBe('editor.BlockListBlock');
    });

    it('writes a preset Block spacing value onto the navigation wrapper', async () => {
        const received = await renderFiltered({
            name: 'core/navigation',
            attributes: { style: { spacing: { blockGap: 'var:preset|spacing|40' } } },
        });

        expect(received.wrapperProps?.style?.gap).toBe('var(--wp--preset--spacing--40)');
    });

    it('combines a per-axis value into a row / column gap, defaulting the missing axis', async () => {
        const both = await renderFiltered({
            name: 'core/navigation',
            attributes: { style: { spacing: { blockGap: { top: '1rem', left: '2rem' } } } },
        });

        expect(both.wrapperProps?.style?.gap).toBe('1rem 2rem');

        const columnOnly = await renderFiltered({
            name: 'core/navigation',
            attributes: { style: { spacing: { blockGap: { left: '2rem' } } } },
        });

        expect(columnOnly.wrapperProps?.style?.gap).toBe(
            'var(--wp--style--block-gap, 0.5em) 2rem'
        );
    });

    it('preserves existing wrapper props and styles', async () => {
        const received = await renderFiltered({
            name: 'core/navigation',
            attributes: { style: { spacing: { blockGap: '12px' } } },
            wrapperProps: { 'data-x': 'y', style: { color: 'red' } },
        });

        expect(received.wrapperProps).toEqual({
            'data-x': 'y',
            style: { color: 'red', gap: '12px' },
        });
    });

    it.each([
        ['no style', {}],
        ['an empty string', { style: { spacing: { blockGap: '' } } }],
        ['an empty axis object', { style: { spacing: { blockGap: { top: '', left: ' ' } } } }],
        ['a non-string value', { style: { spacing: { blockGap: 12 } } }],
    ])('leaves the wrapper untouched for %s', async (_label, attributes) => {
        const wrapperProps = { style: { color: 'red' } };
        const received = await renderFiltered({
            name: 'core/navigation',
            attributes,
            wrapperProps,
        });

        expect(received.wrapperProps).toBe(wrapperProps);
    });

    it('ignores blocks other than core/navigation', async () => {
        const received = await renderFiltered({
            name: 'core/group',
            attributes: { style: { spacing: { blockGap: '12px' } } },
        });

        expect(received.wrapperProps).toBeUndefined();
    });
});
