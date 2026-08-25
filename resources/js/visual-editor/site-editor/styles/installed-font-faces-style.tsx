/**
 * Installed-font faces `<style>` (#636).
 *
 * Injects the installed fonts' `@font-face` rules and
 * `--wp--preset--font-family--{slug}` custom properties into the main document
 * so a preview that renders inline — the Styles style-book — can resolve an
 * installed-font preset value and actually paint the face. The block-editor
 * canvas iframe already receives the authoritative bundle through
 * `/global-styles/css`; this covers the surfaces outside that iframe.
 *
 * The rules come from the installed-fonts store, so the moment a font is
 * installed or uninstalled (which reloads the store) the preview updates too.
 * `:root` and `@font-face` are document-global regardless of where the `<style>`
 * sits, so co-locating it with the style-book keeps the wiring local.
 *
 * @package @artisanpack-ui/visual-editor
 * @since 1.7.0
 */

import { useMemo } from 'react';

import { buildInstalledFontFacesCss } from '../../fonts/installed-fonts-css';
import { useInstalledFonts } from '../../fonts/installed-fonts-store';

export function InstalledFontFacesStyle(): JSX.Element | null {
    const fonts = useInstalledFonts();
    const css = useMemo(() => buildInstalledFontFacesCss(fonts), [fonts]);

    if (css === '') {
        return null;
    }

    return <style data-testid="ap-installed-font-faces">{css}</style>;
}
