/**
 * Usage counting has to survive the read-side fork rewrite.
 *
 * `TemplateAdapter` rewrites every `core/x` name to `artisanpack/x` when
 * it serves templates and parts, so the trees this hook walks arrive
 * carrying `artisanpack/block` for a synced-pattern reference. Matching
 * only the core name made the count silently read zero, which in the
 * delete dialog reads as "this pattern is used nowhere" — the most
 * dangerous possible wrong answer for a destructive action.
 */

import { act, renderHook } from '@testing-library/react';
import { beforeEach, describe, expect, it, vi } from 'vitest';

import { usePatternUsage } from '../use-pattern-usage';

const LIST_MOCK = vi.fn();
const FETCH_MOCK = vi.fn();

// `../../api-client`, not `../api-client`: the hook lives in `patterns/`,
// so its own import resolves to the site-editor client one level up.
// `patterns/api-client` is a different module (the pattern CRUD client)
// and mocking that one leaves the hook talking to the real thing.
vi.mock('../../api-client', () => ({
    listEntities: (...args: unknown[]) => LIST_MOCK(...args),
    fetchEntity: (...args: unknown[]) => FETCH_MOCK(...args),
}));

// The hook pulls `parse` in only for its raw-markup fallback, which these
// cases never reach — every fixture supplies `content.blocks`. Stubbing it
// keeps the suite off `@wordpress/blocks`' JSON-import entry path.
vi.mock('@wordpress/blocks', () => ({
    parse: () => [],
}));

const API_CONFIG = { apiBase: '/visual-editor/api' };

function templateWith(blocks: readonly unknown[]): Record<string, unknown> {
    return {
        id: 'page',
        slug: 'page',
        content: { raw: '', blocks },
    };
}

function patternRef(name: string, ref: number): Record<string, unknown> {
    return { name, attributes: { ref }, innerBlocks: [] };
}

describe('usePatternUsage', () => {
    beforeEach(() => {
        LIST_MOCK.mockReset();
        FETCH_MOCK.mockReset();
        FETCH_MOCK.mockResolvedValue(null);
    });

    it('counts forked `artisanpack/block` references', async () => {
        LIST_MOCK.mockImplementation((_config, kind: string) =>
            Promise.resolve(
                kind === 'template'
                    ? [templateWith([patternRef('artisanpack/block', 7)])]
                    : []
            )
        );

        const { result } = renderHook(() =>
            usePatternUsage({ apiConfig: API_CONFIG })
        );

        const usage = await result.current.run(7);

        expect(usage.total).toBe(1);
        expect(usage.perKind.template).toBe(1);
    });

    it('still counts raw `core/block` references', async () => {
        // The raw-markup fallback path runs `parse()`, which emits core
        // names regardless of what the adapter did to the parsed tree.
        LIST_MOCK.mockImplementation((_config, kind: string) =>
            Promise.resolve(
                kind === 'template-part'
                    ? [templateWith([patternRef('core/block', 7)])]
                    : []
            )
        );

        const { result } = renderHook(() =>
            usePatternUsage({ apiConfig: API_CONFIG })
        );

        const usage = await result.current.run(7);

        expect(usage.total).toBe(1);
        expect(usage.perKind['template-part']).toBe(1);
    });

    it('fails loud instead of under-counting when the list fills a page', async () => {
        // A full page means there may be more records a single fetch can't
        // see; under-counting could greenlight deleting an in-use pattern.
        LIST_MOCK.mockImplementation((_config, kind: string) =>
            Promise.resolve(
                kind === 'template'
                    ? Array.from({ length: 100 }, () =>
                        templateWith([patternRef('artisanpack/block', 7)])
                    )
                    : []
            )
        );

        const { result } = renderHook(() =>
            usePatternUsage({ apiConfig: API_CONFIG })
        );

        let usage;
        await act(async () => {
            usage = await result.current.run(7);
        });

        expect(usage?.total).toBe(0);
        expect(result.current.error).not.toBeNull();
    });

    it('ignores references to a different pattern id', async () => {
        LIST_MOCK.mockImplementation((_config, kind: string) =>
            Promise.resolve(
                kind === 'template'
                    ? [templateWith([patternRef('artisanpack/block', 99)])]
                    : []
            )
        );

        const { result } = renderHook(() =>
            usePatternUsage({ apiConfig: API_CONFIG })
        );

        const usage = await result.current.run(7);

        expect(usage.total).toBe(0);
    });

    it('reports an error rather than a lower count when a detail fetch fails', async () => {
        // A transient 500 on one row used to contribute 0 references, which
        // can present an in-use pattern as unused in the delete dialog.
        LIST_MOCK.mockImplementation((_config, kind: string) =>
            Promise.resolve(
                kind === 'template'
                    ? [
                        // No blocks in the summary, so the hook falls
                        // through to the per-row detail fetch.
                        { id: 'page', slug: 'page', content: { raw: '' } },
                    ]
                    : []
            )
        );
        FETCH_MOCK.mockRejectedValue(new Error('detail fetch failed'));

        const { result } = renderHook(() =>
            usePatternUsage({ apiConfig: API_CONFIG })
        );

        let usage = { total: -1 } as Awaited<
            ReturnType<typeof result.current.run>
        >;

        await act(async () => {
            usage = await result.current.run(7);
        });

        expect(usage.total).toBe(0);
        expect(result.current.error).toBe('detail fetch failed');
    });

    it('walks nested innerBlocks to find a reference', async () => {
        LIST_MOCK.mockImplementation((_config, kind: string) =>
            Promise.resolve(
                kind === 'template'
                    ? [
                        templateWith([
                            {
                                name: 'artisanpack/group',
                                attributes: {},
                                innerBlocks: [
                                    patternRef('artisanpack/block', 7),
                                ],
                            },
                        ]),
                    ]
                    : []
            )
        );

        const { result } = renderHook(() =>
            usePatternUsage({ apiConfig: API_CONFIG })
        );

        const usage = await result.current.run(7);

        expect(usage.total).toBe(1);
    });
});
