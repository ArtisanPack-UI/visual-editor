/**
 * Tests for the I1 fork class-name alias filter.
 *
 * The module exports a one-shot registrar (idempotent for production
 * safety). Tests use `vi.resetModules` to get a fresh module per test so
 * the singleton flag doesn't bleed across cases.
 */

import { describe, it, expect, vi, beforeEach } from 'vitest';

interface FilterHandler {
    name: string;
    callback: (...args: unknown[]) => unknown;
}

const filters = new Map<string, FilterHandler[]>();

vi.mock('@wordpress/hooks', () => ({
    addFilter: (
        hookName: string,
        name: string,
        callback: (...args: unknown[]) => unknown
    ) => {
        if (!filters.has(hookName)) {
            filters.set(hookName, []);
        }
        filters.get(hookName)!.push({ name, callback });
    },
}));

function applyFilter(hookName: string, value: unknown, ...args: unknown[]): unknown {
    const handlers = filters.get(hookName) ?? [];
    return handlers.reduce(
        (acc, { callback }) => callback(acc, ...args),
        value
    );
}

beforeEach(() => {
    filters.clear();
    vi.resetModules();
});

describe('registerForkClassNameAlias', () => {
    it('is idempotent — calling twice registers the filter once', async () => {
        const mod = await import('../fork-class-name-alias');
        mod.registerForkClassNameAlias();
        mod.registerForkClassNameAlias();
        expect(filters.get('blocks.getBlockDefaultClassName')).toHaveLength(1);
    });

    it('remaps every I1 fork block name to its upstream class', async () => {
        const mod = await import('../fork-class-name-alias');
        mod.registerForkClassNameAlias();
        const slugs = [
            'heading',
            'list',
            'list-item',
            'quote',
            'code',
            'preformatted',
            'pullquote',
            'verse',
            'table',
        ];
        for (const slug of slugs) {
            expect(
                applyFilter(
                    'blocks.getBlockDefaultClassName',
                    `wp-block-artisanpack-${slug}`,
                    `artisanpack/${slug}`
                )
            ).toBe(`wp-block-${slug}`);
        }
    });

    it('leaves unknown artisanpack blocks alone (e.g. artisanpack/callout)', async () => {
        const mod = await import('../fork-class-name-alias');
        mod.registerForkClassNameAlias();
        expect(
            applyFilter(
                'blocks.getBlockDefaultClassName',
                'wp-block-artisanpack-callout',
                'artisanpack/callout'
            )
        ).toBe('wp-block-artisanpack-callout');
    });

    it('leaves core/* blocks alone', async () => {
        const mod = await import('../fork-class-name-alias');
        mod.registerForkClassNameAlias();
        expect(
            applyFilter(
                'blocks.getBlockDefaultClassName',
                'wp-block-table',
                'core/table'
            )
        ).toBe('wp-block-table');
    });
});
