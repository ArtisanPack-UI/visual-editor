/**
 * Shared block-editor settings — #436.
 *
 * The post editor (`editor/editor-app.tsx`) and the site editor
 * (`site-editor/block-editor-boundary.tsx`) both feed a `settings`
 * object into `BlockEditorProvider`. That object decides which
 * inspector panels light up: the Color panel needs a palette + the
 * `color` features, the Typography font-size / font-family pickers
 * need their preset lists, and so on.
 *
 * The site editor used to boot with a minimal `{ alignWide,
 * hasFixedToolbar }` placeholder, so its inspector showed far fewer
 * panels than the post editor. That gap was invisible until #436 fixed
 * the inspector's provider scope — once block selection reached the
 * inspector, the under-configuration became visible. Both editors now
 * share this one object so their inspectors match.
 *
 * `__experimentalFeatures` is intentionally narrow: `link`,
 * `defaultPalette`, `defaultGradients`, and `defaultDuotone` assume
 * WordPress-core preset data this package doesn't ship, and turning
 * them on without that data triggers an infinite render loop inside
 * the color picker during drag. Only enable a feature once the data
 * backing it is also wired. Theme.json integration (B3) will later
 * replace these seeded defaults with the active theme's real presets.
 */

import { __ } from '@wordpress/i18n';

import { mediaUploadSetting } from './media-bridge';
import { TEXT_DOMAIN } from './vendor/i18n';

/**
 * DaisyUI-aligned color palette. Hosts can override by publishing
 * their own settings once theme.json integration (B3) lands; for V1
 * this seeds a coherent default so new installs aren't staring at an
 * empty color picker.
 *
 * Preset labels are wrapped with `__()` so the pot-extraction command
 * picks them up; when translations aren't loaded `__()` returns the
 * English source unchanged.
 */
export const DEFAULT_PALETTE = [
    { name: __('Base content', TEXT_DOMAIN), slug: 'base-content', color: '#1f2937' },
    { name: __('Base muted', TEXT_DOMAIN), slug: 'base-muted', color: '#6b7280' },
    { name: __('Primary', TEXT_DOMAIN), slug: 'primary', color: '#2563eb' },
    { name: __('Secondary', TEXT_DOMAIN), slug: 'secondary', color: '#64748b' },
    { name: __('Accent', TEXT_DOMAIN), slug: 'accent', color: '#9333ea' },
    { name: __('Success', TEXT_DOMAIN), slug: 'success', color: '#16a34a' },
    { name: __('Warning', TEXT_DOMAIN), slug: 'warning', color: '#d97706' },
    { name: __('Error', TEXT_DOMAIN), slug: 'error', color: '#dc2626' },
];

export const DEFAULT_FONT_SIZES = [
    { name: __('Small', TEXT_DOMAIN), slug: 'small', size: '13px' },
    { name: __('Regular', TEXT_DOMAIN), slug: 'regular', size: '16px' },
    { name: __('Medium', TEXT_DOMAIN), slug: 'medium', size: '20px' },
    { name: __('Large', TEXT_DOMAIN), slug: 'large', size: '28px' },
    { name: __('Huge', TEXT_DOMAIN), slug: 'huge', size: '36px' },
];

export const DEFAULT_FONT_FAMILIES = [
    {
        name: __('System', TEXT_DOMAIN),
        slug: 'system',
        fontFamily:
            'system-ui, -apple-system, "Segoe UI", Roboto, "Helvetica Neue", Arial, sans-serif',
    },
    {
        name: __('Serif', TEXT_DOMAIN),
        slug: 'serif',
        fontFamily: 'Georgia, Cambria, "Times New Roman", Times, serif',
    },
    {
        name: __('Monospaced', TEXT_DOMAIN),
        slug: 'mono',
        fontFamily:
            'ui-monospace, SFMono-Regular, "SF Mono", Menlo, Consolas, monospace',
    },
];

/**
 * Default canvas stylesheet. Injected via `settings.styles` so blocks
 * have a sensible typographic baseline before a theme.json kicks in.
 * Themes override these by emitting `styles.typography` /
 * `styles.elements.*` blocks in theme.json — the theme's stylesheet
 * ships later in the `styles` array and wins on cascade.
 *
 * Scoped under `.editor-styles-wrapper` because Gutenberg auto-prepends
 * that selector to every style entry it isn't already scoped to,
 * keeping these rules out of the editor chrome.
 */
export const DEFAULT_CANVAS_STYLES = `
.editor-styles-wrapper {
    font-family: system-ui, -apple-system, "Segoe UI", Roboto, "Helvetica Neue", Arial, sans-serif;
    font-size: 16px;
    line-height: 1.6;
    color: #1f2937;
}

.editor-styles-wrapper .block-editor-block-list__layout {
    max-width: 720px;
    margin-left: auto;
    margin-right: auto;
    padding: 48px 24px 96px;
}

.editor-styles-wrapper .wp-block[data-align="wide"] {
    max-width: 1080px;
    margin-left: auto;
    margin-right: auto;
}

.editor-styles-wrapper .wp-block[data-align="full"] {
    max-width: none;
}

.editor-styles-wrapper p {
    margin: 0 0 1em;
    font-size: 1rem;
    line-height: 1.6;
}

.editor-styles-wrapper h1,
.editor-styles-wrapper h2,
.editor-styles-wrapper h3,
.editor-styles-wrapper h4,
.editor-styles-wrapper h5,
.editor-styles-wrapper h6 {
    font-family: inherit;
    font-weight: 700;
    line-height: 1.2;
    margin: 1.4em 0 0.6em;
    color: #111827;
}

.editor-styles-wrapper h1 { font-size: 2.5rem; }
.editor-styles-wrapper h2 { font-size: 2rem; }
.editor-styles-wrapper h3 { font-size: 1.5rem; }
.editor-styles-wrapper h4 { font-size: 1.25rem; }
.editor-styles-wrapper h5 { font-size: 1.125rem; }
.editor-styles-wrapper h6 { font-size: 1rem; text-transform: uppercase; letter-spacing: 0.05em; }

.editor-styles-wrapper a {
    color: #2563eb;
    text-decoration: underline;
}

.editor-styles-wrapper blockquote {
    border-left: 4px solid #e5e7eb;
    margin: 1.5em 0;
    padding: 0 0 0 1em;
    font-style: italic;
    color: #4b5563;
}

.editor-styles-wrapper ul,
.editor-styles-wrapper ol {
    margin: 0 0 1em;
    padding-left: 1.5em;
}

.editor-styles-wrapper code {
    font-family: ui-monospace, SFMono-Regular, "SF Mono", Menlo, Consolas, monospace;
    font-size: 0.9em;
    background: #f3f4f6;
    padding: 0.1em 0.35em;
    border-radius: 4px;
}

.editor-styles-wrapper pre {
    background: #f3f4f6;
    padding: 1em;
    border-radius: 6px;
    overflow-x: auto;
    margin: 0 0 1em;
}

.editor-styles-wrapper hr {
    border: 0;
    border-top: 1px solid #e5e7eb;
    margin: 2em 0;
}
`;

/**
 * Block-editor settings seeded with the block-support features needed
 * to light up the inspector panels (Color, Typography, Dimensions,
 * Border, Layout) and the toolbar text-align control. Fed into
 * `BlockEditorProvider` by both the post editor and the site editor.
 */
export const editorSettings = {
    mediaUpload: mediaUploadSetting,
    alignWide: true,
    /*
     * Default canvas stylesheet (`{ css }`) gives blocks a typographic
     * baseline so headings/paragraphs/links visually differentiate
     * without a theme.json. Themes ship their own `{ css, baseURL }`
     * entries via the theme.json bridge (planned) — they're appended
     * to this array and win on cascade.
     */
    styles: [{ css: DEFAULT_CANVAS_STYLES }],
    // Top-level legacy keys are still read by some core blocks that
    // haven't migrated to `__experimentalFeatures`.
    colors: DEFAULT_PALETTE,
    fontSizes: DEFAULT_FONT_SIZES,
    // Minimal `__experimentalFeatures` — only the keys needed for the
    // Typography panel's font-family picker and the text-alignment
    // toolbar. Turning on more keys (border, spacing, duotone, default
    // palettes) re-introduces the color-picker drag loop we're hunting;
    // keep this config narrow until the upstream fix lands.
    __experimentalFeatures: {
        layout: {
            contentSize: '720px',
            wideSize: '1080px',
        },
        color: {
            custom: true,
            text: true,
            background: true,
        },
        typography: {
            customFontSize: true,
            fontStyle: true,
            fontWeight: true,
            letterSpacing: true,
            lineHeight: true,
            textAlign: true,
            textDecoration: true,
            textTransform: true,
            fontSizes: { custom: DEFAULT_FONT_SIZES },
            fontFamilies: { custom: DEFAULT_FONT_FAMILIES },
        },
    },
};
