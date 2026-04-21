/**
 * Verifies that the canonical B2 sample-content fixtures round-trip
 * cleanly through the B1 core-data shim. If the JSON shape in
 * `tests/Fixtures/sample-content/` drifts away from what the shim
 * caches, this test is the first canary — Phase C/D will otherwise
 * see silent type mismatches in the store.
 */

import { readFileSync, readdirSync } from 'node:fs';
import { resolve } from 'node:path';

import { afterEach, beforeEach, describe, expect, it, vi } from 'vitest';
import { dispatch, select } from '@wordpress/data';

import {
    __resetCoreDataShimConfig,
    configureCoreDataShim,
    type EntityKey,
    type EntityRecord,
} from '../core-data-shim';

type CoreSelect = Record<string, (...args: unknown[]) => unknown>;
type CoreDispatch = Record<string, (...args: unknown[]) => unknown>;

const coreSelect = (): CoreSelect => select('core') as CoreSelect;
const coreDispatch = (): CoreDispatch => dispatch('core') as CoreDispatch;

interface FixtureEntity {
    readonly kind: string;
    readonly name: string;
    readonly directory: string;
}

const FIXTURE_ROOT = resolve(
    __dirname,
    '../../../../../tests/Fixtures/sample-content',
);

const FIXTURE_ENTITIES: readonly FixtureEntity[] = [
    { kind: 'postType', name: 'wp_template', directory: 'templates' },
    { kind: 'postType', name: 'wp_template_part', directory: 'template-parts' },
    { kind: 'postType', name: 'wp_navigation', directory: 'navigation' },
    { kind: 'postType', name: 'wp_block', directory: 'patterns' },
    { kind: 'root', name: 'globalStyles', directory: 'global-styles' },
];

function loadFixtures(directory: string): readonly (EntityRecord & { id: EntityKey })[] {
    const dir = resolve(FIXTURE_ROOT, directory);
    const files = readdirSync(dir).filter((name) => name.endsWith('.json')).sort();

    return files.map((file) => {
        const record = JSON.parse(readFileSync(resolve(dir, file), 'utf-8')) as Record<
            string,
            unknown
        >;

        if (typeof record.id !== 'number' && typeof record.id !== 'string') {
            throw new Error(`Fixture ${directory}/${file} is missing a primary key.`);
        }

        return record as EntityRecord & { id: EntityKey };
    });
}

function mockFetcher(
    impl: (input: string, init: RequestInit) => Promise<Response>,
): typeof fetch {
    return vi.fn(async (input: RequestInfo | URL, init?: RequestInit) => {
        const url = typeof input === 'string' ? input : String(input);

        return impl(url, init ?? {});
    }) as unknown as typeof fetch;
}

function jsonResponse(body: unknown, status = 200): Response {
    return new Response(JSON.stringify(body), {
        status,
        headers: { 'Content-Type': 'application/json' },
    });
}

beforeEach(() => {
    coreDispatch().reset();
    __resetCoreDataShimConfig();
});

afterEach(() => {
    coreDispatch().reset();
    __resetCoreDataShimConfig();
});

describe('B2 sample-content fixtures', () => {
    it.each(FIXTURE_ENTITIES)(
        'round-trips $name fixtures through fetchEntityRecord → getEntityRecord',
        async ({ kind, name, directory }) => {
            const fixtures = loadFixtures(directory);

            expect(fixtures.length).toBeGreaterThan(0);

            for (const record of fixtures) {
                configureCoreDataShim({
                    apiBase: '/visual-editor/api',
                    fetcher: mockFetcher(async () => jsonResponse(record)),
                });

                const fetched = await coreDispatch().fetchEntityRecord(
                    kind,
                    name,
                    record.id,
                );

                expect(fetched).toEqual(record);
                expect(coreSelect().getEntityRecord(kind, name, record.id)).toEqual(record);
            }
        },
    );

    it('covers every B1 default entity at least once', () => {
        const countsByFragment = FIXTURE_ENTITIES.map(({ directory }) => ({
            directory,
            count: loadFixtures(directory).length,
        }));

        // Acceptance criteria from #354: 3–4 templates, 3 template parts,
        // 2–3 navigation records, 3+ patterns, 1 global styles record.
        const byDirectory = Object.fromEntries(
            countsByFragment.map(({ directory, count }) => [directory, count]),
        );

        expect(byDirectory.templates).toBeGreaterThanOrEqual(3);
        expect(byDirectory['template-parts']).toBeGreaterThanOrEqual(3);
        expect(byDirectory.navigation).toBeGreaterThanOrEqual(2);
        expect(byDirectory.patterns).toBeGreaterThanOrEqual(3);
        expect(byDirectory['global-styles']).toBe(1);
    });

    it('includes both synced and unsynced patterns so Phase D5 has a starting point', () => {
        const patterns = loadFixtures('patterns');
        const synced = patterns.filter((pattern) => pattern.synced === true);
        const unsynced = patterns.filter((pattern) => pattern.synced === false);

        expect(synced.length).toBeGreaterThan(0);
        expect(unsynced.length).toBeGreaterThan(0);
    });
});
