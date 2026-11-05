/**
 * Breadcrumbs (#1038) — block.json contract.
 *
 * The schema.org microdata is opt-in, so a page whose head already emits
 * a JSON-LD `BreadcrumbList` doesn't carry the list twice. The renderers
 * apply the same default; their suites cover the markup.
 */

import { describe, it, expect } from 'vitest';

import metadata from '../breadcrumbs/block.json';

describe('artisanpack/breadcrumbs block.json', () => {
    it('leaves schema microdata off by default', () => {
        expect(metadata.attributes.breadcrumbsSchema).toEqual({
            type: 'boolean',
            default: false,
        });
    });
});
