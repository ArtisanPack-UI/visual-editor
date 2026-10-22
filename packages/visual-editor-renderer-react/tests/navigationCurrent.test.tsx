import { readFileSync } from 'node:fs';
import { dirname, resolve } from 'node:path';
import { fileURLToPath } from 'node:url';
import { render } from '@testing-library/react';
import { describe, expect, it } from 'vitest';

import '../src/index';
import { BlockTree } from '../src/BlockTree';
import { matchNavigationUrl } from '../src/support/navigationCurrent';
import type { Block } from '../src/types';
import { matchNavigationUrl as vueMatchNavigationUrl } from '../../visual-editor-renderer-vue/src/support/navigationCurrent';
import { makeBlock } from './helpers';

/*
 * Shared with the Blade `NavigationCurrentStateTest`: the expected values
 * are what PHP `NavigationCurrentState::match()` returns (#975). React
 * and Vue ship byte-identical copies; both are exercised.
 */
const sharedCases: Array<[string, string, string]> = JSON.parse(
    readFileSync(
        resolve(dirname(fileURLToPath(import.meta.url)), '../../renderer-markup-parity/navigation-current-cases.json'),
        'utf8'
    )
).cases;

describe.each([
    ['react', matchNavigationUrl],
    ['vue', vueMatchNavigationUrl],
])('%s matchNavigationUrl matches PHP NavigationCurrentState::match()', (_renderer, match) => {
    it.each(sharedCases)('%j on %j → %j', (link, current, expected) => {
        expect(match(link, current)).toBe(expected);
    });
});

function link(label: string, url: string): Block {
    return makeBlock('core/navigation-link', { label, url }, [], `link-${label}`);
}

function renderOn(tree: Block[], currentUrl?: string): HTMLElement {
    return render(<BlockTree tree={tree} currentUrl={currentUrl} />).container;
}

describe('navigation current page (#975)', () => {
    it('marks the current link with aria-current and current-menu-item', () => {
        const container = renderOn([link('Blog', '/blog/'), link('About', '/about')], 'https://example.test/blog?page=2');
        const items = container.querySelectorAll('li');

        expect(items[0].className).toBe('wp-block-navigation-item wp-block-navigation-link current-menu-item');
        expect(items[0].querySelector('a')?.getAttribute('aria-current')).toBe('page');
        expect(items[1].className).toBe('wp-block-navigation-item wp-block-navigation-link');
        expect(items[1].querySelector('a')?.hasAttribute('aria-current')).toBe(false);
    });

    it('gives a parent-path link current-menu-ancestor without aria-current', () => {
        const container = renderOn([link('Blog', '/blog')], 'https://example.test/blog/hello');

        expect(container.querySelector('li')?.className).toBe(
            'wp-block-navigation-item wp-block-navigation-link current-menu-ancestor'
        );
        expect(container.querySelector('[aria-current]')).toBeNull();
    });

    it('gives a submenu leading to the current page current-menu-ancestor', () => {
        const submenu = makeBlock('core/navigation-submenu', { label: 'Services', url: '/what-we-do' }, [
            link('Design', '/services/design'),
        ]);
        const container = renderOn([submenu], 'https://example.test/services/design');

        expect(container.querySelector('li')?.className).toBe(
            'wp-block-navigation-item wp-block-navigation-submenu has-child current-menu-ancestor'
        );
        expect(container.querySelectorAll('[aria-current="page"]')).toHaveLength(1);
        expect(container.querySelector('[aria-current="page"]')?.getAttribute('href')).toBe('/services/design');
    });

    it('marks nothing without a currentUrl, even when the tree stores the private flags', () => {
        const stored = makeBlock('core/navigation-link', { label: 'Blog', url: '/blog', _veNavCurrent: 'current' });
        const container = renderOn([stored]);

        expect(container.querySelector('li')?.className).toBe('wp-block-navigation-item wp-block-navigation-link');
        expect(container.querySelector('[aria-current]')).toBeNull();
    });
});
