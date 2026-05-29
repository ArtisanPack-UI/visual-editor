/**
 * Tests for the `artisanpack/latest-posts` block metadata + save contract.
 */

import { describe, it, expect } from 'vitest';

import LatestPostsSave from '../save';
import metadata from '../block.json';

describe('artisanpack/latest-posts block.json', () => {
    it('declares the artisanpack namespace and widgets category', () => {
        expect(metadata.name).toBe('artisanpack/latest-posts');
        expect(metadata.category).toBe('widgets');
    });

    it('uses the artisanpack textdomain', () => {
        expect(metadata.textdomain).toBe('artisanpack-visual-editor');
    });

    it('keeps the upstream attribute defaults', () => {
        expect(metadata.attributes.postsToShow.default).toBe(5);
        expect(metadata.attributes.order.default).toBe('desc');
        expect(metadata.attributes.orderBy.default).toBe('date');
        expect(metadata.attributes.displayPostContentRadio.default).toBe('excerpt');
        expect(metadata.attributes.excerptLength.default).toBe(55);
    });
});

describe('LatestPostsSave', () => {
    it('returns null — latest-posts is server-rendered (dynamic)', () => {
        expect(LatestPostsSave()).toBeNull();
    });
});
