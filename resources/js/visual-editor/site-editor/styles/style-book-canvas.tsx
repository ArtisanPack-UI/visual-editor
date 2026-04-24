/**
 * Style Book canvas.
 *
 * Per design brief §3.7, the Styles section's canvas is a Style Book —
 * a gallery of block examples rendered against the current style state —
 * and it stays on-screen both when browsing the navigator and when
 * editing an inspector panel. That "live preview as you edit" loop is
 * the single-biggest demo moment per the issue.
 *
 * For V1 we ship a lightweight preview that reflects the key
 * customizable primitives (typography, colors, element styles, a
 * sampler of blocks) rather than WP's full preview-every-block panel
 * (issue notes: WP's "style book" panel is NOT in scope). The preview
 * reads directly from the merged draft so every user edit surfaces
 * immediately, which is enough to validate the inspector is doing the
 * right thing.
 *
 * The variation-picker strip sits above the preview per brief §3.7 —
 * horizontal cards, one per theme-provided variation, clicking one
 * overwrites the user record with the preset's values (the V1 scope;
 * authoring new variations is deferred to 1.1 per plan §8).
 */

import { __, sprintf } from '@wordpress/i18n';
import { type CSSProperties, useMemo } from 'react';

import { TEXT_DOMAIN } from '../../vendor/i18n';

import type { GlobalStylesDraft } from './use-global-styles-editor';

import './style-book-canvas.css';

export interface StyleVariation {
    slug: string;
    title: string;
    settings?: Record<string, unknown>;
    styles?: Record<string, unknown>;
}

export interface StyleBookCanvasProps {
    draft: GlobalStylesDraft | null;
    variations: readonly StyleVariation[];
    activeVariationSlug: string | null;
    onSelectVariation: (slug: string) => void;
    loadError: string | null;
}

interface Swatch {
    slug: string;
    name: string;
    color: string;
}

function readPalette(draft: GlobalStylesDraft | null): readonly Swatch[] {
    if (draft === null) {
        return [];
    }

    const palette = (draft.settings as Record<string, Record<string, unknown>>)
        ?.color?.palette;

    if (!Array.isArray(palette)) {
        return [];
    }

    const swatches: Swatch[] = [];

    for (const entry of palette) {
        if (entry === null || typeof entry !== 'object') {
            continue;
        }

        const row = entry as Record<string, unknown>;
        const slug = typeof row.slug === 'string' ? row.slug : null;
        const name = typeof row.name === 'string' ? row.name : null;
        const color = typeof row.color === 'string' ? row.color : null;

        if (slug === null || name === null || color === null) {
            continue;
        }

        swatches.push({ slug, name, color });
    }

    return swatches;
}

function readTypographyPreview(
    draft: GlobalStylesDraft | null
): { family: string | null; size: string | null } {
    if (draft === null) {
        return { family: null, size: null };
    }

    const typography = (
        draft.styles as Record<string, Record<string, unknown>>
    )?.typography;

    const family =
        typeof typography?.fontFamily === 'string'
            ? (typography.fontFamily as string)
            : null;
    const size =
        typeof typography?.fontSize === 'string'
            ? (typography.fontSize as string)
            : null;

    return { family, size };
}

function readLayoutPreview(
    draft: GlobalStylesDraft | null
): { contentSize: string | null; wideSize: string | null } {
    if (draft === null) {
        return { contentSize: null, wideSize: null };
    }

    const layout = (draft.settings as Record<string, Record<string, unknown>>)
        ?.layout;

    const contentSize =
        typeof layout?.contentSize === 'string'
            ? (layout.contentSize as string)
            : null;
    const wideSize =
        typeof layout?.wideSize === 'string'
            ? (layout.wideSize as string)
            : null;

    return { contentSize, wideSize };
}

function presetVarOrLiteral(value: string | null): string | undefined {
    if (value === null) {
        return undefined;
    }

    return value;
}

export function StyleBookCanvas(props: StyleBookCanvasProps): JSX.Element {
    const { draft, variations, activeVariationSlug, onSelectVariation, loadError } =
        props;
    const palette = useMemo(() => readPalette(draft), [draft]);
    const typography = useMemo(() => readTypographyPreview(draft), [draft]);
    const layout = useMemo(() => readLayoutPreview(draft), [draft]);

    const bodyStyle: CSSProperties = useMemo(() => {
        return {
            fontFamily: presetVarOrLiteral(typography.family),
            fontSize: presetVarOrLiteral(typography.size),
        };
    }, [typography.family, typography.size]);

    return (
        <div
            className="ap-site-editor__style-book"
            data-testid="ap-site-editor-style-book"
        >
            <div
                className="ap-site-editor__style-book-variations"
                role="radiogroup"
                aria-label={__('Style variations', TEXT_DOMAIN)}
                data-testid="ap-site-editor-style-book-variations"
            >
                {variations.length === 0 ? (
                    <p
                        className="ap-site-editor__style-book-note"
                        data-testid="ap-site-editor-style-book-no-variations"
                    >
                        {__(
                            'The active theme does not provide style variations.',
                            TEXT_DOMAIN
                        )}
                    </p>
                ) : null}
                {variations.map((variation, index) => {
                    const isActive =
                        activeVariationSlug === variation.slug;
                    // Roving-tabindex per the WAI-ARIA APG `radiogroup`
                    // pattern: only the selected (or the first, when
                    // nothing is selected yet) radio is tab-reachable;
                    // the rest step through with arrow keys.
                    const isTabStop =
                        isActive ||
                        (activeVariationSlug === null && index === 0);

                    return (
                        <button
                            key={variation.slug}
                            type="button"
                            role="radio"
                            aria-checked={isActive}
                            tabIndex={isTabStop ? 0 : -1}
                            className="ap-site-editor__style-book-variation"
                            data-active={isActive}
                            data-testid={`ap-site-editor-style-book-variation-${variation.slug}`}
                            onClick={() => onSelectVariation(variation.slug)}
                        >
                            <span className="ap-site-editor__style-book-variation-title">
                                {variation.title}
                            </span>
                        </button>
                    );
                })}
            </div>

            {loadError !== null ? (
                <p
                    role="alert"
                    className="ap-site-editor__style-book-error"
                    data-testid="ap-site-editor-style-book-error"
                >
                    {loadError}
                </p>
            ) : null}

            <div
                className="ap-site-editor__style-book-body"
                style={bodyStyle}
                data-testid="ap-site-editor-style-book-body"
            >
                <section
                    className="ap-site-editor__style-book-section"
                    aria-label={__('Typography sample', TEXT_DOMAIN)}
                >
                    <h2 className="ap-site-editor__style-book-h">
                        {__('Headline sample', TEXT_DOMAIN)}
                    </h2>
                    <p>
                        {__(
                            'The quick brown fox jumps over the lazy dog.',
                            TEXT_DOMAIN
                        )}
                    </p>
                </section>
                <section
                    className="ap-site-editor__style-book-section"
                    aria-label={__('Color palette', TEXT_DOMAIN)}
                >
                    <h2 className="ap-site-editor__style-book-h">
                        {__('Palette', TEXT_DOMAIN)}
                    </h2>
                    <ul
                        className="ap-site-editor__style-book-swatches"
                        data-testid="ap-site-editor-style-book-swatches"
                    >
                        {palette.map((swatch, index) => (
                            <li
                                key={`${swatch.slug}-${index}`}
                                className="ap-site-editor__style-book-swatch"
                                data-testid={`ap-site-editor-style-book-swatch-${swatch.slug}`}
                            >
                                <span
                                    className="ap-site-editor__style-book-swatch-chip"
                                    style={{ background: swatch.color }}
                                    aria-hidden="true"
                                />
                                <span className="ap-site-editor__style-book-swatch-name">
                                    {swatch.name}
                                </span>
                                <code className="ap-site-editor__style-book-swatch-value">
                                    {swatch.color}
                                </code>
                            </li>
                        ))}
                    </ul>
                </section>
                <section
                    className="ap-site-editor__style-book-section"
                    aria-label={__('Layout preview', TEXT_DOMAIN)}
                >
                    <h2 className="ap-site-editor__style-book-h">
                        {__('Layout', TEXT_DOMAIN)}
                    </h2>
                    <p data-testid="ap-site-editor-style-book-content-width">
                        {sprintf(
                            /* translators: %s: content-size value (e.g. "720px"). */
                            __('Content width: %s', TEXT_DOMAIN),
                            layout.contentSize ?? __('default', TEXT_DOMAIN)
                        )}
                    </p>
                    <p data-testid="ap-site-editor-style-book-wide-width">
                        {sprintf(
                            /* translators: %s: wide-size value. */
                            __('Wide width: %s', TEXT_DOMAIN),
                            layout.wideSize ?? __('default', TEXT_DOMAIN)
                        )}
                    </p>
                </section>
            </div>
        </div>
    );
}
