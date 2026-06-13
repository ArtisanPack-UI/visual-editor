/**
 * Skills Slider contract (#503).
 *
 * block.json + save contract + clamp helper coverage for the
 * `artisanpack/skills-slider` block. Static block — `save` returns a
 * non-null element so the persisted markup round-trips through
 * Gutenberg's parser.
 */

import { describe, expect, it, vi } from 'vitest';

vi.mock('@wordpress/block-editor', () => ({
    InspectorControls: () => null,
    PanelColorSettings: () => null,
    useBlockProps: Object.assign(() => ({}), { save: () => ({}) }),
}));

import meta from '../skills-slider/block.json';
import save from '../skills-slider/save';
import {
    BAR_HEIGHT_DEFAULT,
    SKILL_LEVEL_DEFAULT,
    clampBarHeight,
    clampSkillLevel,
} from '../skills-slider/clamp';

describe('skills-slider block.json', () => {
    it('declares the artisanpack namespace + textdomain + category', () => {
        expect(meta.name).toBe('artisanpack/skills-slider');
        expect(meta.textdomain).toBe('artisanpack-visual-editor');
        expect(meta.category).toBe('widgets');
    });

    it('declares the skillLevel / barHeight / colour / aria attributes with safe defaults', () => {
        const attrs = (meta as {
            attributes?: Record<string, { default?: unknown; type?: unknown }>;
        }).attributes;

        expect(attrs?.skillLevel?.default).toBe(50);
        expect(attrs?.skillLevel?.type).toBe('number');
        expect(attrs?.barHeight?.default).toBe(5);
        expect(attrs?.barHeight?.type).toBe('number');
        expect(attrs?.barColor?.default).toBe('');
        expect(attrs?.trackColor?.default).toBe('');
        expect(attrs?.ariaLabel?.default).toBe('');
    });
});

describe('skills-slider save contract', () => {
    it('save returns a non-null element (static block)', () => {
        const element = (save as (props: {
            attributes: {
                skillLevel: number;
                barHeight: number;
                barColor: string;
                trackColor: string;
                ariaLabel: string;
            };
        }) => unknown)({
            attributes: {
                skillLevel: 75,
                barHeight: 8,
                barColor: '#ff0000',
                trackColor: '#eeeeee',
                ariaLabel: 'PHP proficiency',
            },
        });
        expect(element).not.toBeNull();
    });
});

describe('skills-slider clamp helpers', () => {
    it('clampSkillLevel snaps to the 1..100 range and falls back to the default', () => {
        expect(clampSkillLevel(50)).toBe(50);
        expect(clampSkillLevel(0)).toBe(1);
        expect(clampSkillLevel(150)).toBe(100);
        expect(clampSkillLevel(50.6)).toBe(51);
        expect(clampSkillLevel(undefined)).toBe(SKILL_LEVEL_DEFAULT);
        expect(clampSkillLevel(Number.NaN)).toBe(SKILL_LEVEL_DEFAULT);
    });

    it('clampBarHeight snaps to the 1..100 range and falls back to the default', () => {
        expect(clampBarHeight(5)).toBe(5);
        expect(clampBarHeight(0)).toBe(1);
        expect(clampBarHeight(500)).toBe(100);
        expect(clampBarHeight(undefined)).toBe(BAR_HEIGHT_DEFAULT);
        expect(clampBarHeight(Number.NaN)).toBe(BAR_HEIGHT_DEFAULT);
    });
});
