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

describe('LayoutBaseline theme-less defaults (#819 / #821)', () => {
    it('falls back to the default content and wide sizes', () => {
        expect(LAYOUT_BASELINE_CSS).toContain('max-width: var(--wp--style--global--content-size, 720px)');
        expect(LAYOUT_BASELINE_CSS).toContain('max-width: var(--wp--style--global--wide-size, 1080px)');
    });

    it('floats alignleft / alignright children of constrained groups', () => {
        expect(LAYOUT_BASELINE_CSS).toContain('.wp-block-group.wp-block-group-is-layout-constrained > .alignleft { float: left;');
        expect(LAYOUT_BASELINE_CSS).toContain('.wp-block-group.wp-block-group-is-layout-constrained > .alignright { float: right;');
    });

    it('declares default font sizes, palette and layout tokens at zero specificity', () => {
        expect(LAYOUT_BASELINE_CSS).toContain('--wp--preset--font-size--large: 28px;');
        expect(LAYOUT_BASELINE_CSS).toContain('--wp--preset--color--primary: #2563eb;');
        expect(LAYOUT_BASELINE_CSS).toContain('--wp--style--block-gap: 24px;');
        expect(LAYOUT_BASELINE_CSS).toContain('.has-large-font-size { font-size: var(--wp--preset--font-size--large) !important; }');
    });

    it('keeps the default presets in sync with PresetRegistry', async () => {
        const { readFileSync } = await import('node:fs');
        const { resolve } = await import('node:path');
        const { DEFAULT_FONT_SIZES, DEFAULT_PALETTE } = await import('../src/support/layoutBaselineCss');
        const php = readFileSync(resolve(__dirname, '../../../src/Resources/PresetRegistry.php'), 'utf8');

        for (const { slug, size } of DEFAULT_FONT_SIZES) {
            expect(php).toContain(`[ 'slug' => '${slug}', 'size' => '${size}' ]`);
        }
        for (const { slug, color } of DEFAULT_PALETTE) {
            expect(php).toContain(`[ 'slug' => '${slug}', 'color' => '${color}' ]`);
        }
    });
});

describe('LayoutBaseline generic floats (#819)', () => {
    it('floats aligned children of any flow or constrained layout, matching Blade', async () => {
        const { readFileSync } = await import('node:fs');
        const { resolve } = await import('node:path');
        const blade = readFileSync(
            resolve(__dirname, '../../visual-editor-renderer-blade/resources/views/components/blocks-styles.blade.php'),
            'utf8'
        );

        for (const rule of LAYOUT_BASELINE_CSS.split('\n').filter((line) => line.includes(':where(.is-layout-constrained, .is-layout-flow)') || line.includes('size-full'))) {
            expect(blade).toContain(rule);
        }
        expect(LAYOUT_BASELINE_CSS).toContain(':where(.is-layout-constrained, .is-layout-flow) > .alignleft { float: left;');
        expect(LAYOUT_BASELINE_CSS).toContain(':where(.wp-block-image.size-full) { width: auto; height: auto; }');
    });
});
