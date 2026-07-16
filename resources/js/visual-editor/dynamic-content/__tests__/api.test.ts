/**
 * Unit tests for the Dynamic Content editor-side API client.
 *
 * Covers the sources cache, the batched resolver, and the flatten
 * helper. Fetch is mocked; no server involvement.
 *
 * @since 1.4.0
 */

import { beforeEach, describe, expect, it, vi } from 'vitest';

import {
    fetchSources,
    flattenTokens,
    invalidateTokenCache,
    resolveTokens,
} from '../api';

// Reset the module-scoped caches between tests by re-importing.
async function freshImport() {
    vi.resetModules();
    return import('../api');
}

describe('flattenTokens', () => {
    it('produces a flat entry per source × field', () => {
        const rows = flattenTokens([
            {
                slug: 'business_info',
                label: 'Business Info',
                cardinality: 'singleton',
                origin: 'code',
                fields: [
                    { slug: 'phone', label: 'Phone', type: 'phone' },
                    { slug: 'email', label: 'Email', type: 'email' },
                ],
            },
            {
                slug: 'team',
                label: 'Team',
                cardinality: 'collection',
                origin: 'db',
                fields: [{ slug: 'name', label: 'Name', type: 'text' }],
            },
        ]);

        expect(rows.map((r) => r.token)).toEqual([
            'business_info.phone',
            'business_info.email',
            'team.name',
        ]);
        expect(rows[2].cardinality).toBe('collection');
    });
});

describe('fetchSources + resolveTokens', () => {
    beforeEach(() => {
        vi.restoreAllMocks();
    });

    it('caches the sources listing between calls', async () => {
        const { fetchSources } = await freshImport();
        const spy = vi.spyOn(global, 'fetch').mockResolvedValue({
            ok: true,
            json: async () => ({
                sources: [
                    {
                        slug: 'business_info',
                        label: 'Business Info',
                        cardinality: 'singleton',
                        origin: 'code',
                        fields: [{ slug: 'phone', label: 'Phone', type: 'phone' }],
                    },
                ],
            }),
        } as unknown as Response);

        await fetchSources();
        await fetchSources();

        expect(spy).toHaveBeenCalledTimes(1);
    });

    it('batches concurrent resolveTokens calls into one request', async () => {
        const { resolveTokens, invalidateTokenCache } = await freshImport();
        invalidateTokenCache();

        const spy = vi.spyOn(global, 'fetch').mockImplementation(async () => ({
            ok: true,
            json: async () => ({
                values: {
                    'business_info.phone': '(555) 123-4567',
                    'business_info.email': 'hi@example.com',
                },
            }),
        }) as unknown as Response);

        const [a, b] = await Promise.all([
            resolveTokens(['business_info.phone']),
            resolveTokens(['business_info.email']),
        ]);

        expect(spy).toHaveBeenCalledTimes(1);
        expect(a['business_info.phone']).toBe('(555) 123-4567');
        expect(b['business_info.email']).toBe('hi@example.com');
    });

    it('serves cached values without a network call', async () => {
        const { resolveTokens, invalidateTokenCache } = await freshImport();
        invalidateTokenCache();

        const spy = vi.spyOn(global, 'fetch').mockImplementation(async () => ({
            ok: true,
            json: async () => ({ values: { 'business_info.phone': '(555) 123-4567' } }),
        }) as unknown as Response);

        await resolveTokens(['business_info.phone']);
        spy.mockClear();
        const cached = await resolveTokens(['business_info.phone']);

        expect(spy).not.toHaveBeenCalled();
        expect(cached['business_info.phone']).toBe('(555) 123-4567');
    });
});
