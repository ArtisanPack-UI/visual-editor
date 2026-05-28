/**
 * Locks the deprecation chain for `artisanpack/details`.
 *
 * Upstream `core/details` ships no `deprecated.js` (v9.43.0), so the
 * fork exports an empty array. We assert the contract by reading
 * `index.ts` as source rather than evaluating it, which avoids
 * pulling in the heavy `@wordpress/block-editor` chain just to
 * verify a `[]` literal.
 */

import { describe, it, expect } from 'vitest';
import { readFileSync } from 'node:fs';
import { resolve } from 'node:path';

describe('details deprecation chain', () => {
    const indexSource = readFileSync(
        resolve(__dirname, '..', 'index.ts'),
        'utf8'
    );

    it('exports a deprecated symbol as an empty array', () => {
        expect(indexSource).toMatch(/const\s+deprecated[^\n]*=\s*\[\s*\]/);
        expect(indexSource).toMatch(/export\s*\{[^}]*deprecated/);
    });

    it('does not import a deprecated module', () => {
        expect(indexSource).not.toMatch(/from\s+['"]\.\/deprecated['"]/);
    });
});
