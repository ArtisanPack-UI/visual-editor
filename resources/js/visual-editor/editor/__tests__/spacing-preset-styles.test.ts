import { readFileSync } from 'node:fs';
import { resolve } from 'node:path';

import { describe, expect, it, vi } from 'vitest';

// `@wordpress/block-editor` cannot load under vitest; only its `store`
// token is referenced (by the hook, which these tests don't exercise).
vi.mock('@wordpress/block-editor', () => ({ store: {} }));

import { DEFAULT_SPACING_SIZES } from '../../editor-settings';
import { buildSpacingPresetCss, flattenSpacingSizes } from '../spacing-preset-styles';

describe('buildSpacingPresetCss (#814)', () => {
    it('declares every spacing preset on the canvas wrapper', () => {
        expect(
            buildSpacingPresetCss([
                { slug: '20', size: '0.5rem' },
                { slug: '40', size: '1.5rem' },
            ])
        ).toBe(
            '.editor-styles-wrapper { --wp--preset--spacing--20: 0.5rem; --wp--preset--spacing--40: 1.5rem; }'
        );
    });

    it('lets a later entry override an earlier one with the same slug', () => {
        expect(
            buildSpacingPresetCss([
                { slug: '40', size: '1.5rem' },
                { slug: '40', size: '2rem' },
            ])
        ).toBe('.editor-styles-wrapper { --wp--preset--spacing--40: 2rem; }');
    });

    it('slugifies the same way as the Blade compiler', () => {
        expect(buildSpacingPresetCss([{ slug: 'Big Gap', size: '2rem' }])).toContain(
            '--wp--preset--spacing--big-gap: 2rem;'
        );
        expect(buildSpacingPresetCss([{ slug: 'big_gap', size: '2rem' }])).toContain(
            '--wp--preset--spacing--big-gap: 2rem;'
        );
    });

    it('skips unusable or unsafe entries and returns an empty string when none survive', () => {
        expect(
            buildSpacingPresetCss([
                { slug: '', size: '1rem' },
                { slug: 'a', size: '  ' },
                { slug: 'b', size: 12 },
                { slug: 'c', size: '1px; } body { display: none' },
                { slug: 'd', size: '1px</style>' },
                { slug: 'e', size: '1px /* swallow' },
                { slug: 'f', size: '1px\\3b' },
            ])
        ).toBe('');
    });
});

describe('flattenSpacingSizes (#814)', () => {
    it('orders origins default → theme → custom so theme and custom win', () => {
        expect(
            flattenSpacingSizes({
                custom: [{ slug: 'c', size: '3px' }],
                theme: [{ slug: 't', size: '2px' }],
                default: [{ slug: 'd', size: '1px' }],
            })
        ).toEqual([
            { slug: 'd', size: '1px' },
            { slug: 't', size: '2px' },
            { slug: 'c', size: '3px' },
        ]);
    });

    it('accepts a bare list and ignores non-list values', () => {
        expect(flattenSpacingSizes([{ slug: 'x', size: '1px' }])).toEqual([
            { slug: 'x', size: '1px' },
        ]);
        expect(flattenSpacingSizes(undefined)).toEqual([]);
        expect(flattenSpacingSizes({ theme: 'nope' })).toEqual([]);
    });
});

describe('default spacing presets parity (#814)', () => {
    it('matches PresetRegistry::DEFAULT_SPACING_SIZES so the front end declares what the editor offers', () => {
        // Vitest runs from the package root.
        const php = readFileSync(resolve(process.cwd(), 'src/Resources/PresetRegistry.php'), 'utf8');
        const block = php.match(/DEFAULT_SPACING_SIZES = \[([\s\S]*?)\];/)?.[1] ?? '';
        const phpSizes = Array.from(
            block.matchAll(/'slug' => '([^']+)', 'size' => '([^']+)'/g),
            ([, slug, size]) => ({ slug, size })
        );

        expect(phpSizes).toEqual(DEFAULT_SPACING_SIZES.map(({ slug, size }) => ({ slug, size })));
    });
});
