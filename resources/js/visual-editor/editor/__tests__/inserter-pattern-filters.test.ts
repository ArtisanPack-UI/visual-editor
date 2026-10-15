import { describe, expect, it } from 'vitest';

import type { PatternRecord } from '../../site-editor/patterns/api-client';
import { isInserterPattern, patternMatchesSearch } from '../inserter-pattern-filters';

function makePattern(overrides: Partial<PatternRecord> = {}): PatternRecord {
    return {
        id: 1,
        slug: 'theme/hero-banner',
        title: { rendered: 'Hero banner', raw: 'Hero banner' },
        content: { raw: '', blocks: [] },
        synced: false,
        categories: ['featured'],
        status: 'publish',
        type: 'wp_block',
        ...overrides,
    };
}

describe('isInserterPattern', () => {
    it('hides only patterns registered with inserter: false', () => {
        expect(isInserterPattern(makePattern({ inserter: false }))).toBe(false);
        expect(isInserterPattern(makePattern({ inserter: true }))).toBe(true);
        expect(isInserterPattern(makePattern())).toBe(true);
    });
});

describe('patternMatchesSearch', () => {
    it('matches every pattern for an empty or blank query', () => {
        expect(patternMatchesSearch(makePattern(), '')).toBe(true);
        expect(patternMatchesSearch(makePattern(), '   ')).toBe(true);
    });

    it('matches the title, slug and categories', () => {
        expect(patternMatchesSearch(makePattern(), 'banner')).toBe(true);
        expect(patternMatchesSearch(makePattern(), 'theme/hero')).toBe(true);
        expect(patternMatchesSearch(makePattern(), 'featured')).toBe(true);
    });

    it('matches keywords', () => {
        const pattern = makePattern({ keywords: ['starter', 'landing page'] });

        expect(patternMatchesSearch(pattern, 'starter')).toBe(true);
        expect(patternMatchesSearch(pattern, 'landing')).toBe(true);
        expect(patternMatchesSearch(makePattern(), 'starter')).toBe(false);
    });

    it('requires every term to match, case- and accent-insensitively', () => {
        const pattern = makePattern({ keywords: ['Café'] });

        expect(patternMatchesSearch(pattern, 'HERO cafe')).toBe(true);
        expect(patternMatchesSearch(pattern, 'hero footer')).toBe(false);
    });

    it('falls back to the rendered title when there is no raw title', () => {
        expect(patternMatchesSearch(makePattern({ title: { rendered: 'Pricing table' } }), 'pricing')).toBe(true);
    });
});
