/**
 * Phase I5 entity-cluster (#413) — deprecation chains.
 *
 * The five forked entity blocks that carry an upstream deprecation chain
 * (`post-title`, `post-excerpt`, `post-date`, `site-title`, `site-tagline`)
 * are all server-rendered, so every entry's `save` must return `null` and
 * the chain exists purely to migrate legacy attribute shapes. These tests
 * lock the `save`/`isEligible`/`migrate` contract per block.
 */

import { describe, it, expect } from 'vitest';

import postTitleDeprecated from '../post-title/deprecated';
import postExcerptDeprecated from '../post-excerpt/deprecated';
import postDateDeprecated from '../post-date/deprecated';
import siteTitleDeprecated from '../site-title/deprecated';
import siteTaglineDeprecated from '../site-tagline/deprecated';

// eslint-disable-next-line @typescript-eslint/no-explicit-any
type Dep = { save: () => unknown; isEligible?: (a: any) => boolean; migrate?: (a: any) => any };

const CHAINS: Array<[string, Dep[]]> = [
    ['post-title', postTitleDeprecated as Dep[]],
    ['post-excerpt', postExcerptDeprecated as Dep[]],
    ['post-date', postDateDeprecated as Dep[]],
    ['site-title', siteTitleDeprecated as Dep[]],
    ['site-tagline', siteTaglineDeprecated as Dep[]],
];

describe('I5 deprecation chains', () => {
    it.each(CHAINS)('%s: every entry save() returns null (server-rendered)', (_name, chain) => {
        expect(chain.length).toBeGreaterThan(0);
        for (const entry of chain) {
            expect(entry.save()).toBeNull();
        }
    });

    it('post-title v2 is eligible for legacy textAlign and migrates it off the attribute', () => {
        const v2 = postTitleDeprecated[0] as Dep;
        expect(v2.isEligible!({ textAlign: 'center' })).toBe(true);
        expect(v2.isEligible!({})).toBe(false);
        const migrated = v2.migrate!({ textAlign: 'center', level: 2 });
        expect(migrated.textAlign).toBeUndefined();
    });

    it('post-title v1 is eligible only for a custom font family', () => {
        const v1 = postTitleDeprecated[1] as Dep;
        expect(v1.isEligible!({ style: { typography: { fontFamily: 'Inter' } } })).toBe(true);
        expect(v1.isEligible!({ style: {} })).toBe(false);
    });

    it('post-excerpt v1 migrates legacy textAlign', () => {
        const v1 = postExcerptDeprecated[0] as Dep;
        expect(v1.isEligible!({ className: 'has-text-align-left' })).toBe(true);
        const migrated = v1.migrate!({ textAlign: 'right', moreText: 'More' });
        expect(migrated.textAlign).toBeUndefined();
        expect(migrated.moreText).toBe('More');
    });

    it('post-date carries the four-version chain incl. the displayType binding migration', () => {
        expect(postDateDeprecated).toHaveLength(4);
        // v2 = index 2: old blocks with no datetime / binding.
        const v2 = postDateDeprecated[2] as Dep;
        expect(v2.isEligible!({})).toBe(true);
        const migrated = v2.migrate!({ displayType: 'modified', className: 'x' });
        expect(migrated.metadata.bindings.datetime.source).toBe('core/post-data');
        expect(migrated.metadata.bindings.datetime.args.field).toBe('modified');
        expect(migrated.className).toContain('wp-block-post-date__modified-date');
    });

    it('site-title + site-tagline migrate textAlign (v2) and font family (v1)', () => {
        for (const chain of [siteTitleDeprecated, siteTaglineDeprecated]) {
            const v2 = chain[0] as Dep;
            const v1 = chain[1] as Dep;
            expect(v2.isEligible!({ textAlign: 'center' })).toBe(true);
            expect(v1.isEligible!({ style: { typography: { fontFamily: 'Inter' } } })).toBe(true);
            expect(v1.isEligible!({})).toBe(false);
        }
    });
});
