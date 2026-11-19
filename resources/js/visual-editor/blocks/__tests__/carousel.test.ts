/**
 * Carousel family (#1127): the shared attribute rules the canvas uses to
 * preview what the renderers print, and the save contract that keeps the
 * stored markup valid.
 */

import { describe, expect, it, vi } from 'vitest';

vi.mock('@wordpress/block-editor', () => ({
    useBlockProps: Object.assign(() => ({}), {
        save: (props: { className?: string }) => ({ ...props }),
    }),
    useInnerBlocksProps: Object.assign(() => ({}), {
        save: (blockProps: Record<string, unknown>) => ({ ...blockProps }),
    }),
}));

import carouselSave from '../carousel/save';
import carouselSlideSave from '../carousel-slide/save';
import carouselMetadata from '../carousel/block.json';
import slideMetadata from '../carousel-slide/block.json';
import {
    blockGapValue,
    carouselStyleVariables,
    normalizeAutoplayInterval,
    normalizePlacement,
    positionCount,
    resolveSlidesPerView,
    slidesPerViewAt,
} from '../carousel/settings';

interface SaveElement {
    readonly type: unknown;
    readonly props: {
        readonly className?: string;
        readonly children?: unknown;
    };
}

describe('carousel slides per view', () => {
    it('falls back to at most two slides on tablets and one on phones', () => {
        expect(resolveSlidesPerView(3, undefined)).toEqual({ base: 3, tablet: 2, mobile: 1 });
        expect(resolveSlidesPerView(1, {})).toEqual({ base: 1, tablet: 1, mobile: 1 });
    });

    it('uses the per-breakpoint overrides the responsive attribute stores', () => {
        expect(resolveSlidesPerView(4, { slidesPerView: { tablet: 3, mobile: '2' } })).toEqual({
            base: 4,
            tablet: 3,
            mobile: 2,
        });
    });

    it('clamps to 1–6 and ignores values that are not numbers', () => {
        expect(resolveSlidesPerView(9, { slidesPerView: { tablet: 0, mobile: 'lots' } })).toEqual({
            base: 6,
            tablet: 1,
            mobile: 1,
        });
        expect(resolveSlidesPerView('nope', null).base).toBe(3);
    });

    it('reads the value for the active editor breakpoint', () => {
        const perView = { base: 3, tablet: 2, mobile: 1 };

        expect(slidesPerViewAt(perView, 'base')).toBe(3);
        expect(slidesPerViewAt(perView, 'tablet')).toBe(2);
        expect(slidesPerViewAt(perView, 'mobile')).toBe(1);
        expect(slidesPerViewAt(perView, 'lg')).toBe(3);
    });
});

describe('carousel settings', () => {
    it('counts one dot per first-visible slide', () => {
        expect(positionCount(6, 3)).toBe(4);
        expect(positionCount(2, 3)).toBe(1);
    });

    it('falls back to below for an unknown placement', () => {
        expect(normalizePlacement('split')).toBe('split');
        expect(normalizePlacement('sideways')).toBe('below');
    });

    it('clamps the autoplay interval to 2–30 seconds', () => {
        expect(normalizeAutoplayInterval(1)).toBe(2);
        expect(normalizeAutoplayInterval(45)).toBe(30);
        expect(normalizeAutoplayInterval(undefined)).toBe(5);
    });

    it('prints the custom properties the renderers print, dropping unsafe colours', () => {
        const style = carouselStyleVariables(
            {
                dimInactive: true,
                inactiveOpacity: 40,
                arrowColor: '#112233',
                arrowBackgroundColor: 'red;}body{x:y',
                dotColor: 'var(--wp--preset--color--primary)',
                dotActiveColor: '',
            },
            { base: 6, tablet: 3, mobile: 2 }
        );

        expect(style).toEqual({
            '--ap-carousel-per-view': '6',
            '--ap-carousel-per-view-tablet': '3',
            '--ap-carousel-per-view-mobile': '2',
            '--ap-carousel-inactive-opacity': '0.4',
            '--ap-carousel-arrow-color': '#112233',
            '--ap-carousel-dot-color': 'var(--wp--preset--color--primary)',
        });
    });

    it('expands a spacing preset gap with the declared slug form', () => {
        expect(blockGapValue({ spacing: { blockGap: 'var:preset|spacing|big_gap' } })).toBe('var(--wp--preset--spacing--big-gap)');
        expect(blockGapValue({ spacing: { blockGap: 'var:preset|spacing|40' } })).toBe('var(--wp--preset--spacing--40)');
        expect(blockGapValue({ spacing: { blockGap: '2rem' } })).toBe('2rem');
        expect(blockGapValue(undefined)).toBeNull();
    });

    it('leaves the inactive opacity out unless dimming is on', () => {
        const style = carouselStyleVariables({ dimInactive: false, inactiveOpacity: 40 }, { base: 3, tablet: 2, mobile: 1 });

        expect(style).not.toHaveProperty('--ap-carousel-inactive-opacity');
    });
});

describe('carousel block metadata', () => {
    it('stores slides per view per breakpoint through the responsive support', () => {
        expect(carouselMetadata.supports.artisanpackResponsive.attributes).toEqual(['slidesPerView']);
    });

    it('only lets a slide live inside a carousel', () => {
        expect(slideMetadata.parent).toEqual(['artisanpack/carousel']);
    });
});

describe('carousel save contract', () => {
    it('nests the track inside the wrapper, with no controls chrome', () => {
        const element = carouselSave() as unknown as SaveElement;

        expect(element.type).toBe('div');
        expect(element.props.className).toBe('ap-carousel');

        const child = element.props.children as SaveElement;

        expect(child.type).toBe('div');
        expect(child.props.className).toBe('wp-block-artisanpack-carousel__track');
    });

    it('saves a slide as a single wrapper div', () => {
        const element = carouselSlideSave() as unknown as SaveElement;

        expect(element.type).toBe('div');
        expect(element.props.className).toBe('ap-carousel-slide');
    });
});
