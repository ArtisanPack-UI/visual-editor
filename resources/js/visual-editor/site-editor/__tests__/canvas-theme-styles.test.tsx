/**
 * #679 — regression cover for the `<style>` tag the site-editor canvas
 * injects. The emitter ships theme.json root spacing on `:root`; with
 * the editor mounted inline that selector matches the host document's
 * `<html>`, so the injected CSS must be scoped to the canvas surface
 * before it reaches the DOM.
 */

import { render, screen } from '@testing-library/react';
import { beforeEach, describe, expect, it, vi } from 'vitest';

vi.mock('../use-theme-global-styles-css', () => ({
    useThemeGlobalStylesCss: vi.fn(),
}));

import { useThemeGlobalStylesCss as useThemeGlobalStylesCssRaw } from '../use-theme-global-styles-css';

const USE_THEME_GLOBAL_STYLES_CSS = vi.mocked(useThemeGlobalStylesCssRaw);

import { CanvasThemeStyles } from '../canvas-theme-styles';
import { CANVAS_SCOPE_SELECTOR } from '../scope-global-styles-css';

describe('CanvasThemeStyles', () => {
    beforeEach(() => {
        USE_THEME_GLOBAL_STYLES_CSS.mockReset();
    });

    it('injects the theme CSS with `:root` scoped to the canvas surface', () => {
        USE_THEME_GLOBAL_STYLES_CSS.mockReturnValue(
            ':root { padding: 2rem 1.5rem; --wp--preset--color--primary: #2563eb; }'
        );

        render(<CanvasThemeStyles apiBase="/visual-editor/api" />);

        const style = screen.getByTestId('ap-canvas-theme-styles');

        expect(style.textContent).toBe(
            `${CANVAS_SCOPE_SELECTOR} { padding: 2rem 1.5rem; --wp--preset--color--primary: #2563eb; }`
        );
        expect(style.textContent).not.toContain(':root');
    });

    it('renders nothing while the fetch is in flight', () => {
        USE_THEME_GLOBAL_STYLES_CSS.mockReturnValue(undefined);

        render(<CanvasThemeStyles apiBase="/visual-editor/api" />);

        expect(screen.queryByTestId('ap-canvas-theme-styles')).toBeNull();
    });

    it('renders nothing when the theme has no global styles', () => {
        USE_THEME_GLOBAL_STYLES_CSS.mockReturnValue('');

        render(<CanvasThemeStyles apiBase="/visual-editor/api" />);

        expect(screen.queryByTestId('ap-canvas-theme-styles')).toBeNull();
    });
});
