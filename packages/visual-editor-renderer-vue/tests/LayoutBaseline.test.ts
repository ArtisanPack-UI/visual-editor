import { mount } from '@vue/test-utils';
import { describe, expect, it } from 'vitest';

import '../src/index';
import { LayoutBaseline } from '../src/LayoutBaseline';
import { LAYOUT_BASELINE_CSS, colorUtilityRules, colorUtilitySelector, presetPickClass } from '../src/support/layoutBaselineCss';

describe('LayoutBaseline', () => {
    it('renders a <style data-ve-layout-baseline> tag', () => {
        const wrapper = mount(LayoutBaseline);

        expect(wrapper.find('style[data-ve-layout-baseline]').exists()).toBe(true);
    });

    it('emits the canonical is-layout-flow block-gap rule (issue #539)', () => {
        const wrapper = mount(LayoutBaseline);
        const css = wrapper.find('style[data-ve-layout-baseline]').element.innerHTML;

        expect(css).toContain(':where(.is-layout-flow) > * + *');
        expect(css).toContain(':where(.is-layout-constrained) > * + *');
        expect(css).toContain('margin-block-start: var(--wp--style--block-gap, 24px)');
    });

    it('emits the flex + grid baselines', () => {
        const wrapper = mount(LayoutBaseline);
        const css = wrapper.find('style[data-ve-layout-baseline]').element.innerHTML;

        expect(css).toContain('.is-layout-flex { display: flex;');
        expect(css).toContain('.is-layout-grid { display: grid; }');
        // #883 — flex/grid children are spaced by the block gap.
        expect(css).toContain(':where(.is-layout-flex) { gap: var(--wp--style--block-gap, 0.5em); }');
        expect(css).toContain(':where(.is-layout-grid) { gap: var(--wp--style--block-gap, 0.5em); }');
    });

    it('declares the default spacing presets at zero specificity and the navigation gap default (#814)', () => {
        const css = LAYOUT_BASELINE_CSS;

        expect(css).toContain(
            ':where(:root) { --wp--preset--spacing--20: 0.5rem; --wp--preset--spacing--30: 1rem; --wp--preset--spacing--40: 1.5rem; --wp--preset--spacing--50: 3rem; --wp--preset--spacing--60: 5rem; --wp--preset--spacing--70: 7rem; }'
        );
        expect(css).toContain(':where(.wp-block-navigation) { gap: var(--wp--style--block-gap, 0.5em); }');
    });
});

describe('colorUtilitySelector (mirrors PresetRegistry::colorUtilitySelector)', () => {
    const inline = '[style^="color:"], [style*=" color:"], [style*=";color:"]';

    it('keeps a plain class selector for ordinary slugs', () => {
        expect(colorUtilitySelector('base')).toBe('.has-base-color');
        expect(colorUtilitySelector('text-muted')).toBe('.has-text-muted-color');
    });

    it('scopes the slugs that collide with block-support markers to real picks', () => {
        expect(colorUtilitySelector('text')).toBe(`:where(.has-text-color:not(${inline}))`);
        expect(colorUtilitySelector('link')).toBe(`:where(.has-link-color.has-text-color:not(${inline}))`);
        expect(colorUtilitySelector('border')).toBe(`:where(.has-border-color.has-text-color:not(${inline}))`);
    });

    it('names an exact pick class only for the marker slugs', () => {
        expect(presetPickClass('text')).toBe('has-text-color-preset');
        expect(presetPickClass('link')).toBe('has-link-color-preset');
        expect(presetPickClass('base')).toBeNull();
    });

    it('orders plain utilities, exact picks, then fallbacks with text last (mirrors colorUtilityRules)', () => {
        expect(colorUtilityRules(['text', 'base', 'link'])).toEqual([
            '.has-base-color { color: var(--wp--preset--color--base) !important; }',
            '.has-link-color-preset { color: var(--wp--preset--color--link) !important; }',
            '.has-text-color-preset { color: var(--wp--preset--color--text) !important; }',
            `:where(.has-link-color.has-text-color:not(${inline})) { color: var(--wp--preset--color--link) !important; }`,
            `:where(.has-text-color:not(${inline})) { color: var(--wp--preset--color--text) !important; }`,
        ]);
    });
});
