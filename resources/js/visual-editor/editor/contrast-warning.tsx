/**
 * Contrast warning fill.
 *
 * Replaces Gutenberg's built-in `BlockColorContrastChecker`, which we
 * disabled in #343/A1 (see `disableContrastCheckerOnBlocks` in
 * `editor-app.tsx`) because its deps-less `useLayoutEffect` +
 * `requestAnimationFrame` chain triggered the color-picker drag crash.
 *
 * This implementation reads only block attributes — never computed
 * styles — so the render loop that crashed the upstream component
 * cannot reappear. The math comes from the
 * `artisanpack-ui/accessibility` PHP package's WCAG helpers, ported to
 * TypeScript in `wcag-contrast.ts`.
 *
 * Wiring: an `editor.BlockEdit` HOC wraps every block whose type has
 * color support. The wrapper renders an `<InspectorControls
 * group="color">` fill containing a warning `<Notice>` whenever the
 * resolved foreground/background pair fails WCAG AA (≥ 4.5:1). The fill
 * lands directly under the Color panel because `group="color"` targets
 * Gutenberg's named color slot.
 */

import { hasBlockSupport } from '@wordpress/blocks';
import { InspectorControls } from '@wordpress/block-editor';
import { Notice } from '@wordpress/components';
import { useSelect } from '@wordpress/data';
import { addFilter } from '@wordpress/hooks';
import { __, sprintf } from '@wordpress/i18n';
import { createElement, type ComponentType } from 'react';

import { TEXT_DOMAIN } from '../vendor/i18n';

import { getContrastRatio, WCAG_AA_NORMAL_TEXT_RATIO } from './wcag-contrast';

import './contrast-warning.css';

const FILTER_HOOK = 'editor.BlockEdit';
const FILTER_NAMESPACE = 'artisanpack-ui/visual-editor/contrast-warning';

// Page-global sentinel so the filter is registered once even when the
// module is imported by multiple bundles (post + site editor entries
// can both call `registerContrastWarning()`). Mirrors the pattern in
// `synced-pattern-indicator.tsx`.
const REGISTERED_KEY = Symbol.for(
    'artisanpack-ui.visual-editor.contrast-warning.registered'
);

interface GlobalSentinelHost {
    [REGISTERED_KEY]?: boolean;
}

export interface PaletteColor {
    readonly name?: string;
    readonly slug?: string;
    readonly color?: string;
}

interface BlockEditAttributes {
    readonly textColor?: string;
    readonly backgroundColor?: string;
    readonly style?: {
        readonly color?: {
            readonly text?: string;
            readonly background?: string;
        };
    };
}

interface BlockEditProps {
    readonly name: string;
    readonly attributes?: BlockEditAttributes;
    readonly [key: string]: unknown;
}

const PRESET_COLOR_PATTERN = /^var:preset\|color\|(.+)$/;

function resolvePresetSlug(
    slug: string | undefined,
    palette: ReadonlyArray<PaletteColor>
): string | undefined {
    if (slug === undefined || slug === '') {
        return undefined;
    }

    const match = palette.find((entry) => entry.slug === slug);

    return match?.color;
}

/**
 * Normalises an attribute color value (raw hex, CSS keyword, or
 * `var:preset|color|{slug}` reference) into a hex string when possible.
 * Returns `undefined` for empty values or unresolvable preset
 * references — the caller treats that as "no color set" and hides the
 * warning, matching the acceptance criteria.
 */
function resolveAttributeColor(
    value: string | undefined,
    palette: ReadonlyArray<PaletteColor>
): string | undefined {
    if (value === undefined || value === '') {
        return undefined;
    }

    const presetMatch = PRESET_COLOR_PATTERN.exec(value);

    if (presetMatch !== null) {
        return resolvePresetSlug(presetMatch[1], palette);
    }

    return value;
}

export interface EvaluateBlockContrastArgs {
    readonly attributes?: BlockEditAttributes;
    readonly palette: ReadonlyArray<PaletteColor>;
}

export interface ContrastEvaluation {
    readonly foreground: string;
    readonly background: string;
    readonly ratio: number;
    readonly passes: boolean;
}

/**
 * Pure helper that resolves the selected block's color attributes
 * against the merged editor palette and returns the WCAG ratio. Split
 * out of the component so the resolution + evaluation logic is unit
 * testable without spinning up the React + `@wordpress/data` stack.
 *
 * Returns `null` when either color cannot be resolved — that maps to
 * the "Hides when no text or no background is set" acceptance
 * criterion in the calling component.
 */
export function evaluateBlockContrast(
    args: EvaluateBlockContrastArgs
): ContrastEvaluation | null {
    const { attributes, palette } = args;

    if (attributes === undefined || attributes === null) {
        return null;
    }

    // Gutenberg writes preset slugs into `textColor`/`backgroundColor`
    // and raw hex into `style.color.{text,background}` — the slug
    // attribute wins when both are set, mirroring `attributesToStyle`
    // in `@wordpress/block-editor/src/hooks/color.js`.
    const foreground =
        resolvePresetSlug(attributes.textColor, palette) ??
        resolveAttributeColor(attributes.style?.color?.text, palette);
    const background =
        resolvePresetSlug(attributes.backgroundColor, palette) ??
        resolveAttributeColor(attributes.style?.color?.background, palette);

    if (foreground === undefined || background === undefined) {
        return null;
    }

    const ratio = getContrastRatio(foreground, background);

    if (ratio === null) {
        return null;
    }

    return {
        foreground,
        background,
        ratio,
        passes: ratio >= WCAG_AA_NORMAL_TEXT_RATIO,
    };
}

interface BlockEditorSettingsShape {
    readonly colors?: ReadonlyArray<PaletteColor>;
    readonly __experimentalFeatures?: {
        readonly color?: {
            readonly palette?: {
                readonly custom?: ReadonlyArray<PaletteColor>;
                readonly theme?: ReadonlyArray<PaletteColor>;
                readonly default?: ReadonlyArray<PaletteColor>;
            };
        };
    };
}

interface BlockEditorStore {
    getSettings?: () => BlockEditorSettingsShape | undefined;
}

/**
 * Merges every palette source the editor exposes into a single lookup
 * list. `__experimentalFeatures.color.palette.custom` wins over theme
 * and default — same priority Gutenberg uses internally — and the
 * top-level `settings.colors` is appended last as the legacy fallback.
 */
function collectPalette(
    settings: BlockEditorSettingsShape | undefined
): ReadonlyArray<PaletteColor> {
    if (settings === undefined || settings === null) {
        return [];
    }

    const featurePalette = settings.__experimentalFeatures?.color?.palette;

    return [
        ...(featurePalette?.custom ?? []),
        ...(featurePalette?.theme ?? []),
        ...(featurePalette?.default ?? []),
        ...(settings.colors ?? []),
    ];
}

interface ContrastWarningProps {
    readonly attributes?: BlockEditAttributes;
}

export function ContrastWarning(props: ContrastWarningProps): JSX.Element | null {
    const palette = useSelect((select: (key: string) => unknown) => {
        const store = select('core/block-editor') as BlockEditorStore | undefined;

        return collectPalette(store?.getSettings?.());
    }, []);

    const evaluation = evaluateBlockContrast({
        attributes: props.attributes,
        palette,
    });

    if (evaluation === null || evaluation.passes) {
        return null;
    }

    const message = sprintf(
        // translators: %s is the calculated contrast ratio (e.g. "3.21").
        __(
            'This color combination may be hard to read. Contrast ratio is %s:1; WCAG AA requires at least 4.5:1 for normal text.',
            TEXT_DOMAIN
        ),
        evaluation.ratio.toFixed(2)
    );

    // The fill is dropped into Gutenberg's color `ToolsPanel`, which lays
    // its children out in a two-column grid. Without the wrapper the
    // Notice would render at half-width — `grid-column: 1 / -1` on the
    // wrapper restores full width, mirroring the upstream
    // `.block-editor-contrast-checker` rule.
    return (
        <InspectorControls group="color">
            <div className="ap-visual-editor-contrast-warning">
                <Notice
                    status="warning"
                    isDismissible={false}
                    spokenMessage={null}
                >
                    {message}
                </Notice>
            </div>
        </InspectorControls>
    );
}

/**
 * `editor.BlockEdit` HOC. Wraps every block's edit component so the
 * contrast warning fill renders alongside the inner edit only when the
 * block type opts into color support — anything else short-circuits to
 * the original `BlockEdit` unchanged.
 */
export function withContrastWarning(
    BlockEdit: ComponentType<BlockEditProps>
): ComponentType<BlockEditProps> {
    return function ContrastWarningWrapper(props: BlockEditProps): JSX.Element {
        const supportsColor = hasBlockSupport(props.name, 'color', false);

        if (!supportsColor) {
            return createElement(BlockEdit, props);
        }

        return (
            <>
                <ContrastWarning attributes={props.attributes} />
                <BlockEdit {...props} />
            </>
        );
    };
}

export function registerContrastWarning(): void {
    const host = globalThis as unknown as GlobalSentinelHost;

    if (host[REGISTERED_KEY] === true) {
        return;
    }

    addFilter(FILTER_HOOK, FILTER_NAMESPACE, withContrastWarning);
    host[REGISTERED_KEY] = true;
}
