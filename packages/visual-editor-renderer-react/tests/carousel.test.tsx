/**
 * Carousel slides and Block Visibility (#1127).
 *
 * The carousel rebuilds its slides from `innerBlocks`, so a slide the host
 * hid must not be counted, and a screen-size scope has to land on the
 * slide itself: the track lays out and steps through its direct children.
 */

import { renderToStaticMarkup } from 'react-dom/server';
import { describe, expect, it } from 'vitest';

import '../src/index';
import { BlockTree } from '../src/BlockTree';
import type { Block } from '../src/types';
import { makeBlock } from './helpers';

function slide(text: string, attributes: Record<string, unknown> = {}): Block {
    return makeBlock('artisanpack/carousel-slide', attributes, [makeBlock('core/paragraph', { content: text })]);
}

function render(slides: Block[]): string {
    return renderToStaticMarkup(<BlockTree tree={[makeBlock('artisanpack/carousel', {}, slides)]} />);
}

describe('carousel slides and visibility', () => {
    it('drops a hidden slide and numbers only the slides that render', () => {
        const html = render([slide('One'), slide('Two', { _veHidden: true }), slide('Three')]);

        expect(html).not.toContain('Two');
        expect(html).toContain('aria-label="1 of 2"');
        expect(html).toContain('aria-label="2 of 2"');
    });

    it('puts a screen-size scope on the slide itself and keeps its content', () => {
        const html = render([slide('One'), slide('Two', { _veHiddenBreakpoints: ['mobile'] })]);

        expect(html).toMatch(/<div class="wp-block-artisanpack-carousel-slide ap-carousel-slide ve-vis-[^"]+" role="group"/);
        expect(html).not.toContain('data-ve-vis-scope');
        expect(html).toContain('<p class="wp-block-paragraph">Two</p>');
    });

    it('prints the scope once on a slide rendered on its own', () => {
        const html = renderToStaticMarkup(<BlockTree tree={[slide('Solo', { _veHiddenBreakpoints: ['mobile'] })]} />);
        const classAttribute = /class="([^"]*wp-block-artisanpack-carousel-slide[^"]*)"/.exec(html)?.[1] ?? '';

        expect(classAttribute.match(/ve-vis-/g)).toHaveLength(1);
    });
});
