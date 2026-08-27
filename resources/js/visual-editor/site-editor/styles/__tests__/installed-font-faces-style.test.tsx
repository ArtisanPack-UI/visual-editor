/**
 * InstalledFontFacesStyle — unit tests (#636).
 *
 * The style-book preview renders inline in the main document, so the installed
 * fonts' `@font-face` rules and preset custom properties have to be injected
 * there. These tests confirm the component renders nothing when nothing is
 * installed and a populated `<style>` once fonts exist.
 *
 * @since 1.7.0
 */

import { render } from '@testing-library/react';
import { beforeEach, describe, expect, it } from 'vitest';

import {
    resetInstalledFontsStore,
    setInstalledFonts,
} from '../../../fonts/installed-fonts-store';
import { InstalledFontFacesStyle } from '../installed-font-faces-style';

beforeEach(() => {
    resetInstalledFontsStore();
});

describe('InstalledFontFacesStyle', () => {
    it('renders nothing when no fonts are installed', () => {
        // Mark the store ready-but-empty so the mount effect does not fetch.
        setInstalledFonts([]);

        const { container } = render(<InstalledFontFacesStyle />);

        expect(
            container.querySelector('[data-testid="ap-installed-font-faces"]')
        ).toBeNull();
    });

    it('injects @font-face rules and preset properties for installed fonts', () => {
        setInstalledFonts([
            {
                id: 1,
                provider: 'google',
                family: 'Roboto Slab',
                slug: 'roboto-slab',
                is_variable: false,
                license: null,
                source_url: null,
                installed_at: null,
                faces: [
                    {
                        id: 1,
                        weight: 400,
                        style: 'normal',
                        format: 'woff2',
                        axes: null,
                        url: 'https://example.test/roboto-slab/400.woff2',
                    },
                ],
            },
        ]);

        const { getByTestId } = render(<InstalledFontFacesStyle />);
        const style = getByTestId('ap-installed-font-faces');

        expect(style.textContent).toContain('@font-face');
        expect(style.textContent).toContain('font-family: "Roboto Slab"');
        expect(style.textContent).toContain(
            '--wp--preset--font-family--roboto-slab'
        );
    });
});
