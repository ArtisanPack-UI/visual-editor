/**
 * Locks the (empty) deprecation chain for `artisanpack/preformatted`.
 */

import { describe, it, expect } from 'vitest';

import deprecated from '../deprecated';

describe('preformatted deprecation chain', () => {
    it('matches upstream (empty)', () => {
        expect(Array.isArray(deprecated)).toBe(true);
        expect(deprecated).toHaveLength(0);
    });
});
