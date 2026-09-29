import { render } from '@testing-library/react';
import { describe, expect, it } from 'vitest';

import '../src/index';
import { LayoutBaseline } from '../src/LayoutBaseline';
import { LAYOUT_BASELINE_CSS } from '../src/support/layoutBaselineCss';

describe('LayoutBaseline', () => {
    it('renders a <style data-ve-layout-baseline> tag', () => {
        const { container } = render(<LayoutBaseline />);

        const style = container.querySelector('style[data-ve-layout-baseline]');

        expect(style).not.toBeNull();
    });

    it('emits the canonical is-layout-flow block-gap rule (issue #539)', () => {
        const { container } = render(<LayoutBaseline />);

        const css = container.querySelector('style[data-ve-layout-baseline]')?.innerHTML ?? '';

        expect(css).toContain(':where(.is-layout-flow) > * + *');
        expect(css).toContain(':where(.is-layout-constrained) > * + *');
        expect(css).toContain('margin-block-start: var(--wp--style--block-gap, 24px)');
    });

    it('emits the flex + grid baselines', () => {
        const { container } = render(<LayoutBaseline />);

        const css = container.querySelector('style[data-ve-layout-baseline]')?.innerHTML ?? '';

        expect(css).toContain('.is-layout-flex { display: flex;');
        expect(css).toContain('.is-layout-grid { display: grid; }');
    });

    it('declares the default spacing presets at zero specificity and the navigation gap default (#814)', () => {
        const css = LAYOUT_BASELINE_CSS;

        expect(css).toContain(
            ':where(:root) { --wp--preset--spacing--20: 0.5rem; --wp--preset--spacing--30: 1rem; --wp--preset--spacing--40: 1.5rem; --wp--preset--spacing--50: 3rem; --wp--preset--spacing--60: 5rem; --wp--preset--spacing--70: 7rem; }'
        );
        expect(css).toContain(':where(.wp-block-navigation) { gap: var(--wp--style--block-gap, 0.5em); }');
    });
});
