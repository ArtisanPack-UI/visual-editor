/**
 * Carousel behaviour of the Blade package's front-end `interactivity.js`
 * (#1127).
 *
 * The renderers print the region, the labelled slides and hidden controls
 * with an empty dots host. The script reveals the controls, builds one dot
 * per position, slides the track and keeps the slides outside the view
 * inert so focus never lands in one.
 */

import { readFileSync } from 'node:fs';
import { dirname, resolve } from 'node:path';
import { fileURLToPath } from 'node:url';

import { afterEach, beforeEach, describe, expect, it, vi } from 'vitest';

const here = dirname(fileURLToPath(import.meta.url));
const script = readFileSync(resolve(here, '../../resources/assets/frontend/interactivity.js'), 'utf8');

const SLIDE_WIDTH = 100;

interface CarouselOptions {
    readonly slides?: number;
    readonly perView?: number;
    readonly loop?: boolean;
    readonly autoplay?: number;
    readonly controls?: boolean;
}

function renderCarousel({ slides = 5, perView = 2, loop = false, autoplay, controls = true }: CarouselOptions = {}): HTMLElement {
    const slideMarkup = Array.from(
        { length: slides },
        (_unused, index) =>
            `<div class="wp-block-artisanpack-carousel-slide ap-carousel-slide" role="group" aria-roledescription="slide" aria-label="${index + 1} of ${slides}"><a href="#s${index}">Slide ${index + 1}</a></div>`
    ).join('');

    const controlsMarkup = controls
        ? `<div class="wp-block-artisanpack-carousel__controls" hidden>
            ${autoplay ? '<button type="button" class="wp-block-artisanpack-carousel__autoplay" data-label-pause="Pause" data-label-play="Play">Pause</button>' : ''}
            <button type="button" class="wp-block-artisanpack-carousel__arrow wp-block-artisanpack-carousel__arrow--previous" aria-label="Previous slide"></button>
            <div class="wp-block-artisanpack-carousel__dots" role="group" aria-label="Choose a slide" data-dot-label="Go to slide :index"></div>
            <button type="button" class="wp-block-artisanpack-carousel__arrow wp-block-artisanpack-carousel__arrow--next" aria-label="Next slide"></button>
        </div>`
        : '';

    document.body.innerHTML = `
        <div class="wp-block-artisanpack-carousel ap-carousel is-controls-below" role="region" aria-roledescription="carousel" aria-label="Carousel" data-ap-carousel=""${loop ? ' data-loop="true"' : ''}${autoplay ? ` data-autoplay="${autoplay}"` : ''} style="--ap-carousel-columns: ${perView}">
            <div class="wp-block-artisanpack-carousel__viewport">
                <div class="wp-block-artisanpack-carousel__track" aria-live="polite">${slideMarkup}</div>
            </div>
            ${controlsMarkup}
        </div>`;

    // jsdom has no layout: place each slide one width further along.
    document.querySelectorAll<HTMLElement>('.wp-block-artisanpack-carousel-slide').forEach((slide, index) => {
        slide.getBoundingClientRect = () =>
            ({ left: index * SLIDE_WIDTH, right: (index + 1) * SLIDE_WIDTH, width: SLIDE_WIDTH, top: 0, bottom: 10, height: 10 }) as DOMRect;
    });

    return document.querySelector<HTMLElement>('[data-ap-carousel]') as HTMLElement;
}

function runScript(): void {
    // eslint-disable-next-line no-new-func
    new Function(script)();
}

function query<T extends Element = HTMLElement>(selector: string): T {
    return document.querySelector(selector) as T;
}

function dots(): HTMLButtonElement[] {
    return Array.from(document.querySelectorAll<HTMLButtonElement>('.wp-block-artisanpack-carousel__dot'));
}

function slides(): HTMLElement[] {
    return Array.from(document.querySelectorAll<HTMLElement>('.wp-block-artisanpack-carousel-slide'));
}

function track(): HTMLElement {
    return query('.wp-block-artisanpack-carousel__track');
}

/** jsdom has no PointerEvent: a MouseEvent carrying the pointer fields. */
function pointer(type: string, clientX: number, pointerType = 'mouse'): MouseEvent {
    const event = new MouseEvent(type, { bubbles: !type.startsWith('pointerenter') && !type.startsWith('pointerleave'), cancelable: true, clientX, clientY: 0, button: 0 });
    Object.defineProperty(event, 'pointerId', { value: 1 });
    Object.defineProperty(event, 'pointerType', { value: pointerType });

    return event;
}

function setReducedMotion(reduce: boolean): void {
    Object.defineProperty(window, 'matchMedia', {
        configurable: true,
        writable: true,
        value: (query: string) => ({ matches: reduce && query.includes('reduce'), media: query }),
    });
}

describe('interactivity.js carousel', () => {
    beforeEach(() => {
        vi.useFakeTimers();
        setReducedMotion(false);
    });

    afterEach(() => {
        vi.useRealTimers();
        document.body.innerHTML = '';
        Reflect.deleteProperty(window, 'matchMedia');
    });

    it('reveals the controls and builds one dot per position', () => {
        const root = renderCarousel({ slides: 5, perView: 2 });
        runScript();

        expect(root.classList.contains('is-ready')).toBe(true);
        expect(query('.wp-block-artisanpack-carousel__controls').hidden).toBe(false);
        expect(dots()).toHaveLength(4);
        expect(dots().map((dot) => dot.getAttribute('aria-label'))).toEqual([
            'Go to slide 1',
            'Go to slide 2',
            'Go to slide 3',
            'Go to slide 4',
        ]);
        expect(dots()[0].getAttribute('aria-current')).toBe('true');
        expect(dots()[0].classList.contains('is-active')).toBe(true);
        expect(dots()[1].hasAttribute('aria-current')).toBe(false);
    });

    it('keeps the slides outside the view inert', () => {
        renderCarousel({ slides: 4, perView: 2 });
        runScript();

        expect(slides().map((slide) => slide.hasAttribute('inert'))).toEqual([false, false, true, true]);
        expect(slides()[2].classList.contains('is-inactive')).toBe(true);
        expect(slides()[2].getAttribute('aria-hidden')).toBe('true');
    });

    it('moves one slide per arrow click and stops at the ends without loop', () => {
        renderCarousel({ slides: 4, perView: 2 });
        runScript();

        const previous = query<HTMLButtonElement>('.wp-block-artisanpack-carousel__arrow--previous');
        const next = query<HTMLButtonElement>('.wp-block-artisanpack-carousel__arrow--next');

        expect(previous.getAttribute('aria-disabled')).toBe('true');

        next.click();

        expect(track().style.transform).toBe('translateX(-100px)');
        expect(dots()[1].getAttribute('aria-current')).toBe('true');
        expect(slides().map((slide) => slide.hasAttribute('inert'))).toEqual([true, false, false, true]);

        next.click();
        next.click();

        expect(track().style.transform).toBe('translateX(-200px)');
        expect(next.getAttribute('aria-disabled')).toBe('true');
        expect(previous.getAttribute('aria-disabled')).toBe('false');
    });

    it('wraps around with loop on', () => {
        renderCarousel({ slides: 3, perView: 1, loop: true });
        runScript();

        query<HTMLButtonElement>('.wp-block-artisanpack-carousel__arrow--previous').click();

        expect(dots()[2].getAttribute('aria-current')).toBe('true');

        query<HTMLButtonElement>('.wp-block-artisanpack-carousel__arrow--next').click();

        expect(dots()[0].getAttribute('aria-current')).toBe('true');
    });

    it('jumps to a position from its dot', () => {
        renderCarousel({ slides: 5, perView: 2 });
        runScript();

        dots()[3].click();

        expect(track().style.transform).toBe('translateX(-300px)');
    });

    it('moves with the arrow keys, Home and End, keeping focus on the active dot', () => {
        renderCarousel({ slides: 5, perView: 2 });
        runScript();

        dots()[0].focus();
        dots()[0].dispatchEvent(new KeyboardEvent('keydown', { key: 'ArrowRight', bubbles: true, cancelable: true }));

        expect(dots()[1].getAttribute('aria-current')).toBe('true');
        expect(document.activeElement).toBe(dots()[1]);

        dots()[1].dispatchEvent(new KeyboardEvent('keydown', { key: 'End', bubbles: true, cancelable: true }));

        expect(dots()[3].getAttribute('aria-current')).toBe('true');

        dots()[3].dispatchEvent(new KeyboardEvent('keydown', { key: 'Home', bubbles: true, cancelable: true }));

        expect(dots()[0].getAttribute('aria-current')).toBe('true');
    });

    it('moves focus out of a slide that leaves the view', () => {
        renderCarousel({ slides: 4, perView: 1 });
        runScript();

        const link = slides()[0].querySelector('a') as HTMLAnchorElement;
        link.focus();
        link.dispatchEvent(new KeyboardEvent('keydown', { key: 'ArrowRight', bubbles: true, cancelable: true }));

        expect(slides()[0].hasAttribute('inert')).toBe(true);
        expect(document.activeElement).toBe(slides()[1]);
    });

    it('ignores arrow keys typed into a form field', () => {
        renderCarousel({ slides: 4, perView: 1 });
        slides()[0].insertAdjacentHTML('beforeend', '<input type="text">');
        runScript();

        query('input').dispatchEvent(new KeyboardEvent('keydown', { key: 'ArrowRight', bubbles: true, cancelable: true }));

        expect(dots()[0].getAttribute('aria-current')).toBe('true');
    });

    it('autoplays, wraps to the start and stops from the pause button', () => {
        renderCarousel({ slides: 3, perView: 1, autoplay: 3 });
        runScript();

        expect(track().getAttribute('aria-live')).toBe('off');

        vi.advanceTimersByTime(3000);
        expect(dots()[1].getAttribute('aria-current')).toBe('true');

        vi.advanceTimersByTime(6000);
        expect(dots()[0].getAttribute('aria-current')).toBe('true');

        const toggle = query<HTMLButtonElement>('.wp-block-artisanpack-carousel__autoplay');
        toggle.click();

        expect(toggle.textContent).toBe('Play');
        expect(track().getAttribute('aria-live')).toBe('polite');

        vi.advanceTimersByTime(9000);
        expect(dots()[0].getAttribute('aria-current')).toBe('true');

        toggle.click();
        expect(toggle.textContent).toBe('Pause');
        vi.advanceTimersByTime(3000);
        expect(dots()[1].getAttribute('aria-current')).toBe('true');
    });

    it('stops autoplay when something in the carousel gets focus', () => {
        renderCarousel({ slides: 3, perView: 1, autoplay: 3 });
        runScript();

        dots()[0].focus();
        vi.advanceTimersByTime(9000);

        expect(dots()[0].getAttribute('aria-current')).toBe('true');
        expect(query('.wp-block-artisanpack-carousel__autoplay').textContent).toBe('Play');
    });

    it('never autoplays under prefers-reduced-motion and drops the pause button', () => {
        setReducedMotion(true);
        renderCarousel({ slides: 3, perView: 1, autoplay: 3 });
        runScript();

        vi.advanceTimersByTime(9000);

        expect(dots()[0].getAttribute('aria-current')).toBe('true');
        expect(document.querySelector('.wp-block-artisanpack-carousel__autoplay')).toBeNull();
        expect(track().getAttribute('aria-live')).toBe('polite');
    });

    it('hides the dots when every slide fits in the view', () => {
        renderCarousel({ slides: 2, perView: 3 });
        runScript();

        expect(dots()).toHaveLength(1);
        expect(query('.wp-block-artisanpack-carousel__dots').hidden).toBe(true);
        expect(query('.wp-block-artisanpack-carousel__arrow--next').getAttribute('aria-disabled')).toBe('true');
    });

    it('binds a carousel with no controls', () => {
        const root = renderCarousel({ slides: 3, perView: 1, controls: false });
        runScript();

        expect(root.classList.contains('is-ready')).toBe(true);
        expect(slides()[1].hasAttribute('inert')).toBe(true);
    });

    it('moves on after a drag past the threshold and lets the next click through', () => {
        renderCarousel({ slides: 4, perView: 1 });
        runScript();

        const link = slides()[0].querySelector('a') as HTMLAnchorElement;

        link.dispatchEvent(pointer('pointerdown', 300));
        link.dispatchEvent(pointer('pointermove', 260));
        link.dispatchEvent(pointer('pointermove', 200));
        link.dispatchEvent(pointer('pointerup', 200));

        expect(dots()[1].getAttribute('aria-current')).toBe('true');

        // The drag's own click is swallowed so the link isn't followed.
        const dragClick = new MouseEvent('click', { bubbles: true, cancelable: true });
        link.dispatchEvent(dragClick);
        expect(dragClick.defaultPrevented).toBe(true);

        // A touch drag fires no click: the guard must not eat the next one.
        vi.advanceTimersByTime(0);
        const laterClick = new MouseEvent('click', { bubbles: true, cancelable: true });
        slides()[1].querySelector('a')?.dispatchEvent(laterClick);
        expect(laterClick.defaultPrevented).toBe(false);
    });

    it('snaps back from a drag shorter than the threshold', () => {
        renderCarousel({ slides: 4, perView: 1 });
        runScript();

        const slide = slides()[0];

        slide.dispatchEvent(pointer('pointerdown', 300));
        slide.dispatchEvent(pointer('pointermove', 290));
        slide.dispatchEvent(pointer('pointerup', 290));

        expect(dots()[0].getAttribute('aria-current')).toBe('true');
        expect(track().style.transform).toBe('translateX(0px)');
    });

    it('leaves the outer carousel alone when a nested one is dragged', () => {
        const outer = renderCarousel({ slides: 3, perView: 1 });
        const inner = outer.cloneNode(true) as HTMLElement;
        slides()[0].appendChild(inner);
        runScript();

        const innerSlide = inner.querySelector('.wp-block-artisanpack-carousel-slide') as HTMLElement;
        innerSlide.dispatchEvent(pointer('pointerdown', 300));
        innerSlide.dispatchEvent(pointer('pointermove', 200));
        innerSlide.dispatchEvent(pointer('pointerup', 200));

        // The outer carousel's own controls are its last child.
        const outerDots = (outer.lastElementChild as HTMLElement).querySelectorAll('.wp-block-artisanpack-carousel__dot');

        expect(outerDots).toHaveLength(3);
        expect((outer.querySelector('.wp-block-artisanpack-carousel__track') as HTMLElement).style.transform).toBe('translateX(0px)');
        expect(outerDots[0].getAttribute('aria-current')).toBe('true');
    });

    it('pauses autoplay while a mouse hovers but not after a touch', () => {
        const root = renderCarousel({ slides: 3, perView: 1, autoplay: 3 });
        runScript();

        root.dispatchEvent(pointer('pointerenter', 0, 'touch'));
        vi.advanceTimersByTime(3000);
        expect(dots()[1].getAttribute('aria-current')).toBe('true');

        root.dispatchEvent(pointer('pointerenter', 0, 'mouse'));
        vi.advanceTimersByTime(6000);
        expect(dots()[1].getAttribute('aria-current')).toBe('true');

        root.dispatchEvent(pointer('pointerleave', 0, 'mouse'));
        vi.advanceTimersByTime(3000);
        expect(dots()[2].getAttribute('aria-current')).toBe('true');
    });

    it('is idempotent across re-runs', () => {
        renderCarousel({ slides: 4, perView: 2 });
        runScript();
        window.dispatchEvent(new Event('ap:rebind'));

        expect(dots()).toHaveLength(3);

        query<HTMLButtonElement>('.wp-block-artisanpack-carousel__arrow--next').click();

        expect(dots()[1].getAttribute('aria-current')).toBe('true');
    });
});
