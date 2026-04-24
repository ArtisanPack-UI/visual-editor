import { describe, expect, it } from 'vitest';

import {
    fallbackChainForSlug,
    getTemplateKindOptions,
} from '../fallback-chain';

describe('fallbackChainForSlug', () => {
    it('puts the primary slug first and always ends at index', () => {
        const chain = fallbackChainForSlug('front-page');

        expect(chain[0]).toBe('front-page');
        expect(chain[chain.length - 1]).toBe('index');
    });

    it('walks single-specific slugs to single → singular → index', () => {
        const chain = fallbackChainForSlug('single-post');

        expect(chain).toEqual(['single-post', 'single', 'singular', 'index']);
    });

    it('walks page-specific slugs to page → singular → index', () => {
        const chain = fallbackChainForSlug('page-about');

        expect(chain).toEqual(['page-about', 'page', 'singular', 'index']);
    });

    it('falls back from archive variants to archive → index', () => {
        const chain = fallbackChainForSlug('archive-book');

        expect(chain).toEqual(['archive-book', 'archive', 'index']);
    });

    it('treats custom slugs as falling back to index only', () => {
        const chain = fallbackChainForSlug('my-custom-template');

        expect(chain).toEqual(['my-custom-template', 'index']);
    });

    it('uses only index when the slug is empty', () => {
        const chain = fallbackChainForSlug('');

        expect(chain).toEqual(['index']);
    });
});

describe('getTemplateKindOptions', () => {
    it('exposes the known kinds in priority order', () => {
        const options = getTemplateKindOptions();
        const slugs = options.map((option) => option.slug);

        expect(slugs).toContain('front-page');
        expect(slugs).toContain('single');
        expect(slugs).toContain('page');
        expect(slugs).toContain('archive');
        expect(slugs).toContain('index');
        expect(slugs.indexOf('front-page')).toBeLessThan(slugs.indexOf('index'));
    });
});
