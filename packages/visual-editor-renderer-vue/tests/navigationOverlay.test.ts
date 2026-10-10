import { mount } from '@vue/test-utils';
import type { VueWrapper } from '@vue/test-utils';
import { nextTick } from 'vue';
import { afterEach, describe, expect, it } from 'vitest';

import '../src/index';
import { BlockTree } from '../src/BlockTree';
import type { PatternRecord } from '../src/patterns';
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

function renderWithPatterns(tree: Block[], patterns: PatternRecord[], templateParts?: TemplatePartRecord[]): HTMLElement {
    const host = document.createElement('div');

    document.body.appendChild(host);

    const wrapper = mount(BlockTree, {
        props: { tree, templateParts, patterns },
        attachTo: host,
    });

    mounted.push(wrapper);

    return host;
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
        expect(open?.getAttribute('aria-expanded')).toBe('false');
        // No SSR-stable id source on the `^3.3` peer range, so no aria-controls.
        expect(open?.hasAttribute('aria-controls')).toBe(false);
        // RN-1: the closed container doubles as the inline desktop menu,
        // so it is never aria-hidden and carries no dialog semantics.
        expect(overlay?.hasAttribute('aria-hidden')).toBe(false);
        expect(container.querySelector('[aria-hidden="true"]:not(svg)')).toBeNull();
        expect(container.querySelector('[role="dialog"]')).toBeNull();
        expect(container.querySelector('[aria-modal]')).toBeNull();
        expect(overlay?.className).toBe('wp-block-navigation__responsive-container');
        expect(
            container.querySelector(
                '.wp-block-navigation__responsive-dialog .wp-block-navigation__responsive-container-content > ul.wp-block-navigation__container a[href="/"]'
            )
        ).not.toBeNull();
        expect(container.querySelector('.wp-block-navigation__overlay-content')).toBeNull();
    });

    it('labels the open button and dialog with the block aria-label', async () => {
        const wrapper = mountNav([navigation({ ariaLabel: 'Primary' })]);

        expect(wrapper.find('.wp-block-navigation__responsive-container-open').attributes('aria-label')).toBe('Primary');

        await wrapper.find('.wp-block-navigation__responsive-container-open').trigger('click');

        expect(wrapper.find('[role="dialog"]').attributes('aria-label')).toBe('Primary');
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

    it('reduces overlay preset slugs to a single sanitized class token (RN-7)', () => {
        const container = renderNav([
            navigation({ overlayBackgroundColor: 'x is-menu-open', overlayTextColor: '"Brand_Red"' }),
        ]);
        const overlay = container.querySelector('.wp-block-navigation__responsive-container');

        expect(overlay?.className).toBe(
            'wp-block-navigation__responsive-container has-x-is-menu-open-background-color has-background has-brand-red-color has-text-color'
        );
        expect(overlay?.classList.contains('is-menu-open')).toBe(false);
    });

    it('drops an overlay preset class whose slug sanitizes to nothing (RN-7)', () => {
        const container = renderNav([navigation({ overlayBackgroundColor: '"; !', customOverlayBackgroundColor: '#111111' })]);
        const overlay = container.querySelector<HTMLElement>('.wp-block-navigation__responsive-container');

        // An empty slug falls through to the custom color, as a blank one does.
        expect(overlay?.className).toBe('wp-block-navigation__responsive-container has-background');
        expect(overlay?.style.backgroundColor).toBe('rgb(17, 17, 17)');
    });

    it('trims overlay colors the way PHP trim() does (RN-8)', () => {
        // PHP trim() leaves the NBSP in place, so Blade rejects the value
        // and so must we; plain ASCII whitespace is still trimmed.
        const container = renderNav([
            navigation({ customOverlayBackgroundColor: '#111111\u00a0', customOverlayTextColor: ' #eeeeee\t' }),
        ]);
        const overlay = container.querySelector<HTMLElement>('.wp-block-navigation__responsive-container');

        expect(overlay?.className).toBe('wp-block-navigation__responsive-container has-text-color');
        expect(overlay?.style.backgroundColor).toBe('');
        expect(overlay?.style.color).toBe('rgb(238, 238, 238)');
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

describe('NavigationBlock overlay inside synced patterns + stored overlay blocks (RN-9 · RN-10)', () => {
    const storedOverlay = (): Block =>
        makeBlock('artisanpack/navigation-overlay-content', { _slot: 'overlay' }, [
            makeBlock('core/paragraph', { content: 'Injected overlay' }, [], 'stored-p'),
        ], 'stored-overlay');

    it('resolves the overlay of a navigation that only appears inside a synced pattern', () => {
        const container = renderWithPatterns(
            [makeBlock('core/block', { ref: 7 }, [], 'pattern-ref')],
            [{ id: 7, blocks: [navigation({ overlay: 'mobile-overlay' })] }],
            [OVERLAY_PART]
        );

        expect(container.querySelectorAll('.wp-block-navigation__overlay-content')).toHaveLength(1);
        expect(container.querySelector('.wp-block-navigation__overlay-content')?.textContent).toContain('Call us today');
        expect(container.querySelector('.wp-block-navigation__responsive-container-content')?.className).toContain('has-overlay-template');
    });

    it('never renders a stored overlay-content block from the saved tree', () => {
        const nav = navigation();
        const container = renderNav([{ ...nav, innerBlocks: [...(nav.innerBlocks ?? []), storedOverlay()] }]);

        expect(container.textContent).not.toContain('Injected overlay');
        expect(container.querySelector('.wp-block-navigation__overlay-content')).toBeNull();
        expect(container.querySelector('.has-overlay-template')).toBeNull();
    });

    it('replaces a stored overlay-content block with the resolved part instead of duplicating it', () => {
        const nav = navigation({ overlay: 'mobile-overlay' });
        const container = renderNav([{ ...nav, innerBlocks: [...(nav.innerBlocks ?? []), storedOverlay()] }], [OVERLAY_PART]);

        expect(container.querySelectorAll('.wp-block-navigation__overlay-content')).toHaveLength(1);
        expect(container.textContent).toContain('Call us today');
        expect(container.textContent).not.toContain('Injected overlay');
    });

    it('never renders a stored overlay-content block reached through a synced pattern', () => {
        const nav = navigation();
        const patternTree = [{ ...nav, innerBlocks: [...(nav.innerBlocks ?? []), storedOverlay()] }];

        for (const templateParts of [undefined, [OVERLAY_PART]]) {
            const container = renderWithPatterns(
                [makeBlock('core/block', { ref: 8 }, [], 'pattern-ref-2')],
                [{ id: 8, blocks: patternTree }],
                templateParts
            );

            expect(container.textContent).not.toContain('Injected overlay');
            expect(container.querySelector('.wp-block-navigation__overlay-content')).toBeNull();
        }
    });
});

describe('NavigationBlock overlay interaction (#804)', () => {
    it('opens the drawer, locks scroll and focuses the close button', async () => {
        const wrapper = mountNav([navigation()]);

        await wrapper.find('.wp-block-navigation__responsive-container-open').trigger('click');

        const overlay = wrapper.find('.wp-block-navigation__responsive-container');

        expect(overlay.classes()).toContain('is-menu-open');
        expect(overlay.attributes('aria-hidden')).toBeUndefined();
        expect(wrapper.find('.wp-block-navigation__responsive-container-open').attributes('aria-expanded')).toBe('true');

        const dialog = wrapper.find('.wp-block-navigation__responsive-dialog');

        expect(dialog.attributes('role')).toBe('dialog');
        expect(dialog.attributes('aria-modal')).toBe('true');
        expect(dialog.attributes('aria-label')).toBe('Menu');
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
        expect(overlay.attributes('aria-hidden')).toBeUndefined();
        expect(open.attributes('aria-expanded')).toBe('false');
        expect(wrapper.find('[role="dialog"]').exists()).toBe(false);
        expect(wrapper.find('[aria-modal]').exists()).toBe(false);
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
        await wrapper.find('.wp-block-navigation__responsive-container-content').trigger('click');

        expect(wrapper.find('.wp-block-navigation__responsive-container').classes()).toContain('is-menu-open');

        await wrapper.find('.wp-block-navigation__responsive-close').trigger('click');

        expect(wrapper.find('.wp-block-navigation__responsive-container').classes()).not.toContain('is-menu-open');
    });

    it('closes when a link inside the open drawer is clicked (RN-6)', async () => {
        const wrapper = mountNav([navigation()]);
        const click = new MouseEvent('click', { bubbles: true, cancelable: true });

        await wrapper.find('.wp-block-navigation__responsive-container-open').trigger('click');
        expect(document.body.style.overflow).toBe('hidden');

        wrapper.find('a[href="/"]').element.dispatchEvent(click);
        await nextTick();
        await nextTick();

        expect(wrapper.find('.wp-block-navigation__responsive-container').classes()).not.toContain('is-menu-open');
        expect(document.body.style.overflow).toBe('');
        // The link's own navigation is left alone.
        expect(click.defaultPrevented).toBe(false);
    });

    it('wraps Tab focus inside the open drawer (RN-5)', async () => {
        const wrapper = mountNav([navigation()]);
        const close = wrapper.find('.wp-block-navigation__responsive-container-close').element as HTMLButtonElement;
        const link = wrapper.find('a[href="/"]').element as HTMLAnchorElement;

        await wrapper.find('.wp-block-navigation__responsive-container-open').trigger('click');
        expect(document.activeElement).toBe(close);

        link.focus();
        const tab = new KeyboardEvent('keydown', { key: 'Tab', bubbles: true, cancelable: true });
        document.dispatchEvent(tab);

        expect(tab.defaultPrevented).toBe(true);
        expect(document.activeElement).toBe(close);

        const shiftTab = new KeyboardEvent('keydown', { key: 'Tab', shiftKey: true, bubbles: true, cancelable: true });
        document.dispatchEvent(shiftTab);

        expect(shiftTab.defaultPrevented).toBe(true);
        expect(document.activeElement).toBe(link);
    });

    it('lets Tab move normally between elements in the middle of the drawer (RN-5)', async () => {
        const wrapper = mountNav([navigation()]);

        await wrapper.find('.wp-block-navigation__responsive-container-open').trigger('click');

        const tab = new KeyboardEvent('keydown', { key: 'Tab', bubbles: true, cancelable: true });
        document.dispatchEvent(tab);

        // Focus is on the close button (first of two); the browser moves on.
        expect(tab.defaultPrevented).toBe(false);
    });

    it('skips the swapped-out menu links when wrapping focus with an overlay part (RN-5)', async () => {
        const part: TemplatePartRecord = {
            ...OVERLAY_PART,
            blocks: [makeBlock('core/paragraph', { content: '<a href="/call">Call</a>' }, [], 'overlay-link-p')],
        };
        const wrapper = mountNav([navigation({ overlay: 'mobile-overlay' })], [part]);

        await wrapper.find('.wp-block-navigation__responsive-container-open').trigger('click');

        const shiftTab = new KeyboardEvent('keydown', { key: 'Tab', shiftKey: true, bubbles: true, cancelable: true });
        document.dispatchEvent(shiftTab);

        expect(document.activeElement).toBe(wrapper.find('.wp-block-navigation__overlay-content a[href="/call"]').element);
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
