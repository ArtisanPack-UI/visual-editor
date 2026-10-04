/**
 * #821 — the front end declares the font-size and palette presets the
 * editor's pickers offer when a theme ships none, from
 * `PresetRegistry::DEFAULT_FONT_SIZES` / `DEFAULT_PALETTE`. These guards
 * keep the PHP lists (and the default layout sizes) in sync with the
 * editor's.
 */

import { readFileSync } from 'node:fs';
import { resolve } from 'node:path';

import { describe, expect, it } from 'vitest';

import { DEFAULT_FONT_SIZES, DEFAULT_PALETTE, editorSettings } from '../../editor-settings';

const php = readFileSync(resolve(process.cwd(), 'src/Resources/PresetRegistry.php'), 'utf8');

function phpList(constant: string, valueKey: string): Array<{ slug: string; value: string }> {
    const block = php.match(new RegExp(`${constant} = \\[([\\s\\S]*?)\\];`))?.[1] ?? '';

    return Array.from(block.matchAll(new RegExp(`'slug' => '([^']+)', '${valueKey}' => '([^']+)'`, 'g')), ([, slug, value]) => ({
        slug,
        value,
    }));
}

describe('default presets stay in sync with PresetRegistry (#821)', () => {
    it('matches DEFAULT_FONT_SIZES', () => {
        expect(phpList('DEFAULT_FONT_SIZES', 'size')).toEqual(
            DEFAULT_FONT_SIZES.map(({ slug, size }) => ({ slug, value: size }))
        );
    });

    it('matches DEFAULT_PALETTE', () => {
        expect(phpList('DEFAULT_PALETTE', 'color')).toEqual(
            DEFAULT_PALETTE.map(({ slug, color }) => ({ slug, value: color }))
        );
    });

    it('matches the editor layout sizes', () => {
        const layout = php.match(/DEFAULT_LAYOUT = \[([\s\S]*?)\];/)?.[1] ?? '';
        const features = editorSettings.__experimentalFeatures.layout;

        expect(layout).toContain(`'contentSize' => '${features.contentSize}'`);
        expect(layout).toContain(`'wideSize'    => '${features.wideSize}'`);
    });
});
