/**
 * Font Library live preview (#635).
 *
 * Renders an editable sample-text field set in the target family so a user can
 * see a font before (and after) installing it. Any stylesheet needed to make
 * the preview render — `@font-face` rules built from an installed font's
 * self-hosted files, or a provider-supplied preview stylesheet URL — is
 * injected into `<head>` for the modal session only and torn down when the
 * component unmounts or the font changes, so browsing a catalog never leaves
 * dangling font loads behind.
 *
 * @since 1.7.0
 */

import { useEffect, useId, type CSSProperties } from 'react';

import { __ } from '@wordpress/i18n';

import { TEXT_DOMAIN } from '../vendor/i18n';
import type { FontFace } from './api-client';

export interface FontPreviewProps {
    /** The font-family name to render the sample in. */
    readonly family: string;
    /**
     * Installed faces whose self-hosted URLs are turned into `@font-face`
     * rules. Faces without a resolvable URL are skipped.
     */
    readonly faces?: readonly FontFace[];
    /**
     * A provider-supplied preview stylesheet URL (catalog fonts that aren't
     * installed yet). Loaded via a session-scoped `<link>`.
     */
    readonly previewUrl?: string;
    /** The current sample text. */
    readonly sampleText: string;
    /** Fired as the user edits the sample text. Omit for a static preview. */
    readonly onSampleTextChange?: (text: string) => void;
    /** Preview font size in pixels. */
    readonly fontSize?: number;
}

const FORMAT_BY_EXTENSION: Record<string, string> = {
    woff2: 'woff2',
    woff: 'woff',
    ttf: 'truetype',
    otf: 'opentype',
};

/**
 * Stable empty-faces default so the `@font-face` effect's dependency array does
 * not see a fresh array identity on every render (which would re-run it each
 * time for catalog rows and the header preview that pass no faces).
 *
 * @since 1.7.0
 */
const EMPTY_FACES: readonly FontFace[] = [];

/**
 * Escape a value for use inside a double-quoted CSS string. Backslash must be
 * escaped first (so it can't consume the escape we add for a quote), then the
 * quote, then any newline character — CR, LF, and form feed (U+000C), all of
 * which CSS preprocessing normalizes to a newline that terminates the string
 * and emits a bad-string token. Without this a crafted family name (custom
 * uploads are user-controlled) could break out of the injected `@font-face`
 * block and inject arbitrary CSS.
 *
 * @since 1.7.0
 */
function cssString(value: string): string {
    return value.replace(/\\/g, '\\\\').replace(/"/g, '\\"').replace(/[\r\n\f]+/g, ' ');
}

/**
 * Build a `font-family` value for the given family name, escaped and quoted
 * with a `sans-serif` fallback.
 *
 * @since 1.7.0
 */
function cssFontFamily(family: string): string {
    return `"${cssString(family)}", sans-serif`;
}

/**
 * Build a single `@font-face` rule for an installed face, or `null` when the
 * face has no resolvable URL. Every interpolated value is escaped or clamped.
 *
 * @since 1.7.0
 */
function faceRule(family: string, face: FontFace): string | null {
    if (!face.url) {
        return null;
    }

    const format = face.format ? FORMAT_BY_EXTENSION[face.format.toLowerCase()] : null;
    // Constrain the numeric/enumerated fields to safe tokens rather than
    // interpolating whatever the JSON carried.
    const weight = Number.isFinite(face.weight) ? Math.trunc(face.weight) : 400;
    const style = face.style === 'italic' ? 'italic' : 'normal';
    const src = format
        ? `url("${cssString(face.url)}") format("${format}")`
        : `url("${cssString(face.url)}")`;

    return [
        '@font-face {',
        `font-family: ${cssFontFamily(family)};`,
        `font-weight: ${weight};`,
        `font-style: ${style};`,
        'font-display: swap;',
        `src: ${src};`,
        '}',
    ].join(' ');
}

export default function FontPreview({
    family,
    faces = EMPTY_FACES,
    previewUrl,
    sampleText,
    onSampleTextChange,
    fontSize = 28,
}: FontPreviewProps) {
    const fieldId = useId();

    // Inject `@font-face` rules for the installed faces, scoped to this
    // preview's lifetime. Rebuilds whenever the family or its faces change.
    useEffect(() => {
        const rules = faces
            .map((face) => faceRule(family, face))
            .filter((rule): rule is string => rule !== null);

        if (rules.length === 0) {
            return;
        }

        const style = document.createElement('style');
        style.setAttribute('data-font-preview', family);
        style.textContent = rules.join('\n');
        document.head.appendChild(style);

        return () => {
            style.remove();
        };
    }, [family, faces]);

    // Load a provider preview stylesheet, scoped to this preview's lifetime.
    useEffect(() => {
        if (!previewUrl) {
            return;
        }

        const link = document.createElement('link');
        link.rel = 'stylesheet';
        link.href = previewUrl;
        link.setAttribute('data-font-preview-provider', family);
        document.head.appendChild(link);

        return () => {
            link.remove();
        };
    }, [family, previewUrl]);

    const previewStyle: CSSProperties = {
        fontFamily: cssFontFamily(family),
        fontSize,
        lineHeight: 1.3,
        width: '100%',
        minHeight: fontSize * 2,
        padding: '10px 12px',
        border: '1px solid #ddd',
        borderRadius: 4,
        background: '#fff',
        color: '#1e1e1e',
        resize: 'vertical',
        boxSizing: 'border-box',
    };

    if (!onSampleTextChange) {
        return (
            <div style={previewStyle} aria-label={__('Font preview', TEXT_DOMAIN)}>
                {sampleText}
            </div>
        );
    }

    return (
        <label>
            <span className="screen-reader-text" style={{ position: 'absolute', left: -9999 }}>
                {__('Preview sample text', TEXT_DOMAIN)}
            </span>
            <textarea
                id={fieldId}
                style={previewStyle}
                value={sampleText}
                rows={2}
                spellCheck={false}
                onChange={(event) => onSampleTextChange(event.target.value)}
            />
        </label>
    );
}
