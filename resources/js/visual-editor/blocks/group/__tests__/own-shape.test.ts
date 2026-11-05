/**
 * Which empty Groups draw something of their own (#1039).
 */

import { describe, expect, it } from 'vitest';

import { hasOwnShape } from '../own-shape';

describe('hasOwnShape', () => {
    it.each([
        ['a preset background', { backgroundColor: 'primary' }],
        ['a preset gradient', { gradient: 'sunset' }],
        ['a border color', { borderColor: 'primary' }],
        ['a custom background', { style: { color: { background: '#000' } } }],
        ['a custom gradient', { style: { color: { gradient: 'linear-gradient(red, blue)' } } }],
        ['a background image', { style: { background: { backgroundImage: { url: 'a.png' } } } }],
        ['a border', { style: { border: { radius: '999px' } } }],
        ['a size', { style: { dimensions: { minHeight: '14px' } } }],
        ['padding', { style: { spacing: { padding: { top: '1rem' } } } }],
        ['a flex basis', { artisanpackFlex: { item: { basis: { base: '14px' } } } }],
    ])('is true for %s', (_label, attributes) => {
        expect(hasOwnShape(attributes as never)).toBe(true);
    });

    it.each([
        ['no attributes', {}],
        ['only text styles', { style: { typography: { fontSize: '2rem' } } }],
        ['only a margin', { style: { spacing: { margin: { top: '1rem' } } } }],
        ['only container flex settings', { artisanpackFlex: { container: { enabled: { base: true } } } }],
    ])('is false for %s', (_label, attributes) => {
        expect(hasOwnShape(attributes as never)).toBe(false);
    });
});
