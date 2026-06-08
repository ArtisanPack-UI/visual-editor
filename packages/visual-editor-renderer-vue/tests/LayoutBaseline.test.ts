import { mount } from '@vue/test-utils';
import { describe, expect, it } from 'vitest';

import '../src/index';
import { LayoutBaseline } from '../src/LayoutBaseline';

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
    });
});
