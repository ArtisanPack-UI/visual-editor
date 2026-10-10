import { mount } from '@vue/test-utils';
import type { VueWrapper } from '@vue/test-utils';
import { nextTick } from 'vue';
import { afterEach, describe, expect, it } from 'vitest';

import '../src/index';
import { BlockTree } from '../src/BlockTree';
import type { TemplatePartRecord } from '../src/templateParts';
import type { Block } from '../src/types';
import { makeBlock } from './helpers';

function navigation(attributes: Record<string, unknown> = {}, clientId = 'nav-1'): Block {
    return makeBlock(
        'core/navigation',
        attributes,
        [makeBlock('core/navigation-link', { label: 'Home', url: '/' }, [], `${clientId}-link`)],
        clientId
    );
}

const OVERLAY_PART: TemplatePartRecord = {
    slug: 'mobile-overlay',
    area: 'navigation-overlay',
    blocks: [makeBlock('core/paragraph', { content: 'Call us today' }, [], 'overlay-p')],
};

const mounted: VueWrapper[] = [];

function mountNav(tree: Block[], templateParts?: TemplatePartRecord[]): VueWrapper {
    const host = document.createElement('div');

    document.body.appendChild(host);

    const wrapper = mount(BlockTree, {
        props: { tree, templateParts },
        attachTo: host,
    });

    mounted.push(wrapper);

    return wrapper;
}

function renderNav(tree: Block[], templateParts?: TemplatePartRecord[]): HTMLElement {
    return mountNav(tree, templateParts).element.parentElement as HTMLElement;
}

afterEach(() => {
    while (mounted.length > 0) {
        mounted.pop()?.unmount();
    }

    document.body.innerHTML = '';
    document.body.style.overflow = '';
});

describe('NavigationBlock overlay markup (#804)', () => {
    it('renders the open button and a closed responsive container around the menu by default', () => {
        const container = renderNav([navigation()]);

        const open = container.querySelector('button.wp-block-navigation__responsive-container-open');
        const overlay = container.querySelector('.wp-block-navigation__responsive-container');

        expect(container.querySelector('nav')?.className).toContain('is-responsive');
        expect(open?.getAttribute('aria-haspopup')).toBe('dialog');
        expect(open?.getAttribute('aria-label')).toBe('Menu');
        expect(open?.className).toBe('wp-block-navigation__responsive-container-open');
        expect(overlay?.getAttribute('aria-hidden')).toBe('true');
        expect(overlay?.className).toBe('wp-block-navigation__responsive-container');
        expect(
            container.querySelector(
                '.wp-block-navigation__responsive-dialog[role="dialog"][aria-modal="true"] .wp-block-navigation__responsive-container-content > ul.wp-block-navigation__container a[href="/"]'
            )
        ).not.toBeNull();
        expect(container.querySelector('.wp-block-navigation__overlay-content')).toBeNull();
    });

    it('labels the open button and dialog with the block aria-label', () => {
        const container = renderNav([navigation({ ariaLabel: 'Primary' })]);

        expect(container.querySelector('.wp-block-navigation__responsive-container-open')?.getAttribute('aria-label')).toBe('Primary');
        expect(container.querySelector('[role="dialog"]')?.getAttribute('aria-label')).toBe('Primary');
    });

    it('renders a bare menu with no overlay scaffolding or CSS when overlayMenu is never', () => {
        const container = renderNav([navigation({ overlayMenu: 'never', overlay: 'mobile-overlay' })], [OVERLAY_PART]);

        expect(container.querySelector('nav')?.className).not.toContain('is-responsive');
        expect(container.querySelector('nav > ul.wp-block-navigation__container')).not.toBeNull();
        expect(container.querySelector('.wp-block-navigation__responsive-container')).toBeNull();
        expect(container.querySelector('style[data-ve-navigation-overlay]')).toBeNull();
        expect(container.textContent).not.toContain('Call us today');
    });

    it('keeps the drawer collapsed at every width when overlayMenu is always', () => {
        const container = renderNav([navigation({ overlayMenu: 'always' })]);

        expect(container.querySelector('.wp-block-navigation__responsive-container')?.className).toBe(
            'wp-block-navigation__responsive-container is-always-overlay hidden-by-default'
        );
        expect(container.querySelector('.wp-block-navigation__responsive-container-open')?.className).toBe(
            'wp-block-navigation__responsive-container-open always-shown'
        );
    });

    it('applies overlay preset colors as classes and custom colors as inline styles', () => {
        const presets = renderNav([navigation({ overlayBackgroundColor: 'primary', overlayTextColor: 'base-content' })]);

        expect(presets.querySelector('.wp-block-navigation__responsive-container')?.className).toBe(
            'wp-block-navigation__responsive-container has-primary-background-color has-background has-base-content-color has-text-color'
        );

        const custom = renderNav([navigation({ customOverlayBackgroundColor: '#111111', customOverlayTextColor: '#eeeeee' })]);
        const overlay = custom.querySelector<HTMLElement>('.wp-block-navigation__responsive-container');

        expect(overlay?.className).toBe('wp-block-navigation__responsive-container has-background has-text-color');
        expect(overlay?.style.backgroundColor).toBe('rgb(17, 17, 17)');
        expect(overlay?.style.color).toBe('rgb(238, 238, 238)');
    });

    it('drops custom overlay colors that would inject extra declarations', () => {
        const container = renderNav([
            navigation({
                customOverlayBackgroundColor: '#000; position: fixed; inset: 0',
                customOverlayTextColor: 'red;background-image:url(x)',
            }),
        ]);
        const overlay = container.querySelector<HTMLElement>('.wp-block-navigation__responsive-container');

        expect(overlay?.className).toBe('wp-block-navigation__responsive-container');
        expect(overlay?.getAttribute('style')).toBeNull();
    });

    it('renders a stray _slot attribute on stored content in place', () => {
        const container = renderNav([
            makeBlock(
                'core/navigation',
                {},
                [makeBlock('core/navigation-link', { label: 'Pricing', url: '/pricing', _slot: 'overlay' }, [], 'stray-link')],
                'nav-stray'
            ),
        ]);

        expect(container.querySelector('ul.wp-block-navigation__container a[href="/pricing"]')).not.toBeNull();
        expect(container.querySelector('.wp-block-navigation__overlay-content')).toBeNull();
    });

    it('emits the overlay stylesheet once for any number of overlay navigations', () => {
        const container = renderNav([navigation({}, 'nav-1'), navigation({ overlayMenu: 'always' }, 'nav-2')]);
        const styles = container.querySelectorAll('style[data-ve-navigation-overlay]');

        expect(styles).toHaveLength(1);
        expect(styles[0].textContent).toContain('.wp-block-navigation__responsive-container-open:not(.always-shown) { display: none; }');
        expect(styles[0].textContent).toContain('.has-overlay-template .wp-block-navigation__overlay-content { display: block; }');
    });
});

describe('NavigationBlock overlay template part (#804)', () => {
    it('renders the resolved overlay part next to the menu and marks the content for the swap', () => {
        const container = renderNav([navigation({ overlay: 'mobile-overlay' })], [OVERLAY_PART]);
        const content = container.querySelector('.wp-block-navigation__responsive-container-content');

        expect(content?.className).toBe('wp-block-navigation__responsive-container-content has-overlay-template');
        expect(content?.querySelector(':scope > ul.wp-block-navigation__container a[href="/"]')).not.toBeNull();
        expect(content?.querySelector(':scope > .wp-block-navigation__overlay-content')?.innerHTML).toContain('Call us today');
        // The overlay content never leaks into the menu list.
        expect(container.querySelector('ul.wp-block-navigation__container')?.textContent).not.toContain('Call us today');
    });

    it('falls back to the menu when the part sits outside the navigation-overlay area', () => {
        const container = renderNav([navigation({ overlay: 'mobile-overlay' })], [{ ...OVERLAY_PART, area: 'header' }]);

        expect(container.querySelector('.wp-block-navigation__responsive-container-content')?.className).toBe(
            'wp-block-navigation__responsive-container-content'
        );
        expect(container.textContent).not.toContain('Call us today');
    });

    it('falls back to the menu when no template parts are supplied', () => {
        const container = renderNav([navigation({ overlay: 'mobile-overlay' })]);

        expect(container.querySelector('.wp-block-navigation__overlay-content')).toBeNull();
    });

    it('falls back to the menu when every overlay block is hidden by visibility', () => {
        const hidden = makeBlock('core/paragraph', { content: 'Never shown', _veHidden: true }, [], 'hidden-p');
        const container = renderNav([navigation({ overlay: 'mobile-overlay' })], [{ ...OVERLAY_PART, blocks: [hidden] }]);

        expect(container.textContent).not.toContain('Never shown');
    });
});

describe('NavigationBlock overlay interaction (#804)', () => {
    it('opens the drawer, locks scroll and focuses the close button', async () => {
        const wrapper = mountNav([navigation()]);

        await wrapper.find('.wp-block-navigation__responsive-container-open').trigger('click');

        const overlay = wrapper.find('.wp-block-navigation__responsive-container');

        expect(overlay.classes()).toContain('is-menu-open');
        expect(overlay.attributes('aria-hidden')).toBe('false');
        expect(document.body.style.overflow).toBe('hidden');
        expect(document.activeElement).toBe(wrapper.find('.wp-block-navigation__responsive-container-close').element);
    });

    it('closes from the close button and returns focus to the open button', async () => {
        const wrapper = mountNav([navigation()]);
        const open = wrapper.find('.wp-block-navigation__responsive-container-open');

        await open.trigger('click');
        await wrapper.find('.wp-block-navigation__responsive-container-close').trigger('click');
        await nextTick();

        const overlay = wrapper.find('.wp-block-navigation__responsive-container');

        expect(overlay.classes()).not.toContain('is-menu-open');
        expect(overlay.attributes('aria-hidden')).toBe('true');
        expect(document.body.style.overflow).toBe('');
        expect(document.activeElement).toBe(open.element);
    });

    it('closes on Escape', async () => {
        const wrapper = mountNav([navigation()]);

        await wrapper.find('.wp-block-navigation__responsive-container-open').trigger('click');
        document.dispatchEvent(new KeyboardEvent('keydown', { key: 'Escape', bubbles: true }));
        await nextTick();

        expect(wrapper.find('.wp-block-navigation__responsive-container').classes()).not.toContain('is-menu-open');
        expect(document.activeElement).toBe(wrapper.find('.wp-block-navigation__responsive-container-open').element);
    });

    it('closes on a backdrop click but not on a click inside the dialog', async () => {
        const wrapper = mountNav([navigation()]);

        await wrapper.find('.wp-block-navigation__responsive-container-open').trigger('click');
        await wrapper.find('a[href="/"]').trigger('click');

        expect(wrapper.find('.wp-block-navigation__responsive-container').classes()).toContain('is-menu-open');

        await wrapper.find('.wp-block-navigation__responsive-close').trigger('click');

        expect(wrapper.find('.wp-block-navigation__responsive-container').classes()).not.toContain('is-menu-open');
    });

    it('restores page scroll when unmounted while open', async () => {
        const wrapper = mountNav([navigation()]);

        await wrapper.find('.wp-block-navigation__responsive-container-open').trigger('click');
        await nextTick();
        expect(document.body.style.overflow).toBe('hidden');

        mounted.splice(mounted.indexOf(wrapper), 1);
        wrapper.unmount();

        expect(document.body.style.overflow).toBe('');
    });
});
