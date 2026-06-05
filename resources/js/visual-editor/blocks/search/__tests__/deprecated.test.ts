/**
 * Locks the deprecation chain for `artisanpack/search`.
 *
 * `core/search` ships no deprecations (it is rendered outside save), so
 * the fork carries an empty chain. This test fails if a future port
 * accidentally introduces a deprecation entry that upstream does not have.
 */

import { describe, it, expect } from 'vitest';

import deprecated from '../deprecated';

describe('search deprecation chain', () => {
    it('is empty, matching upstream', () => {
        expect(Array.isArray(deprecated)).toBe(true);
        expect(deprecated).toHaveLength(0);
    });
});
