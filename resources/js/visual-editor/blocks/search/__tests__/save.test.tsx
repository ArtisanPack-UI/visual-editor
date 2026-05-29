/**
 * Tests for the `artisanpack/search` block metadata + save contract.
 */

import { describe, it, expect } from 'vitest';

import SearchSave from '../save';
import metadata from '../block.json';

describe('artisanpack/search block.json', () => {
    it('declares the artisanpack namespace and widgets category', () => {
        expect(metadata.name).toBe('artisanpack/search');
        expect(metadata.category).toBe('widgets');
    });

    it('uses the artisanpack textdomain', () => {
        expect(metadata.textdomain).toBe('artisanpack-visual-editor');
    });

    it('keeps the upstream attribute schema', () => {
        expect(metadata.attributes.showLabel.default).toBe(true);
        expect(metadata.attributes.buttonPosition.default).toBe('button-outside');
        expect(metadata.attributes.buttonUseIcon.default).toBe(false);
        expect(metadata.attributes.buttonUseIcon.type).toBe('boolean');
    });
});

describe('SearchSave', () => {
    it('returns null — search is rendered outside save (dynamic)', () => {
        expect(SearchSave()).toBeNull();
    });
});
