import { mount } from '@vue/test-utils';
import { describe, expect, it } from 'vitest';

import '../src/index';
import { BlockTree } from '../src/BlockTree';
import type { Block } from '../src/types';
import { makeBlock } from './helpers';

/*
 * URL matching itself is covered against the shared PHP cases in the
 * React suite (`navigationCurrent.test.tsx`), which exercises this
 * renderer's copy too. These pin the Vue markup (#975).
 */
function link(label: string, url: string): Block {
    return makeBlock('core/navigation-link', { label, url }, [], `link-${label}`);
}


function html(tree: Block[], currentUrl?: string): string {
    const wrapper = mount(BlockTree, { props: { tree, currentUrl } });

    return wrapper.html();
}

describe('navigation current page (#975)', () => {
    it('marks the current link with aria-current and current-menu-item', () => {
        const markup = html([link('Blog', '/blog/'), link('About', '/about')], 'https://example.test/blog?page=2');

        expect(markup).toContain('class="wp-block-navigation-item wp-block-navigation-link current-menu-item"');
        expect(markup).toContain('aria-current="page"');
        expect(markup.match(/aria-current/g)).toHaveLength(1);
    });

    it('gives a parent-path link current-menu-ancestor without aria-current', () => {
        const markup = html([link('Blog', '/blog')], 'https://example.test/blog/hello');

        expect(markup).toContain('class="wp-block-navigation-item wp-block-navigation-link current-menu-ancestor"');
        expect(markup).not.toContain('aria-current');
    });

    it('gives a submenu leading to the current page current-menu-ancestor', () => {
        const submenu = makeBlock('core/navigation-submenu', { label: 'Services', url: '/what-we-do' }, [
            link('Design', '/services/design'),
        ]);
        const markup = html([submenu], 'https://example.test/services/design');

        expect(markup).toContain(
            'class="wp-block-navigation-item wp-block-navigation-submenu has-child current-menu-ancestor"'
        );
        expect(markup.match(/aria-current="page"/g)).toHaveLength(1);
    });

    it('marks nothing without a currentUrl', () => {
        const markup = html([link('Blog', '/blog')]);

        expect(markup).not.toContain('current-menu');
        expect(markup).not.toContain('aria-current');
    });
});
