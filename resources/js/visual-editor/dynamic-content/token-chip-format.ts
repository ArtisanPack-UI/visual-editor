/**
 * Token chip decoration format.
 *
 * Registers a `richText.registerFormatType`-style format that scans
 * the block's saved text for `{{token}}` occurrences and paints them
 * with a chip decoration. The chip renders the resolved preview value
 * or `[Missing: token]` when the token is unresolved.
 *
 * This is presentation only — the raw `{{token}}` is what gets
 * persisted. Server-side resolution swaps in the real value at render
 * time (per cms-framework's DynamicContentResolver behavior).
 *
 * @since 1.4.0
 */

import { registerFormatType, applyFormat, removeFormat, type RichTextValue } from '@wordpress/rich-text';

import { resolveTokens } from './api';

const FORMAT_NAME = 'artisanpack/dynamic-content-chip';
const TOKEN_RE = /\{\{\s*([^{}]+?)\s*\}\}/g;

let registered = false;

/**
 * Return the resolved preview text for a token, using the batched
 * resolver. Missing tokens surface a `[Missing: token]` sentinel.
 *
 * @since 1.4.0
 */
export async function previewForToken(token: string): Promise<string> {
    try {
        const values = await resolveTokens([token]);
        const value = values[token];
        if (value === null || value === undefined || value === '') {
            return `[Missing: ${token}]`;
        }
        if (typeof value === 'string' || typeof value === 'number') return String(value);
        return `[${token}]`;
    } catch {
        return `[Missing: ${token}]`;
    }
}

/**
 * Register the format type.
 *
 * @since 1.4.0
 */
export function registerDynamicContentChipFormat(): void {
    if (registered) return;
    registered = true;

    registerFormatType(FORMAT_NAME, {
        title: 'Dynamic Content chip',
        tagName: 'span',
        className: 've-dc-chip',
        attributes: { 'data-token': 'data-token' },
        edit: () => null,
    } as unknown as Parameters<typeof registerFormatType>[1]);
}

/**
 * Applies chip decoration to every `{{token}}` occurrence in the given
 * RichText value. Called by consumer components (e.g. RichText render
 * pipelines) that want the visible chip preview.
 *
 * @since 1.4.0
 */
export function decorateTokens(value: RichTextValue): RichTextValue {
    if (!value || typeof value !== 'object' || !('text' in value)) return value;

    const text = (value as { text?: string }).text ?? '';
    let match: RegExpExecArray | null;

    let out = value;

    TOKEN_RE.lastIndex = 0;
    while ((match = TOKEN_RE.exec(text)) !== null) {
        const token = match[1].trim();
        const start = match.index;
        const end = start + match[0].length;
        out = applyFormat(
            out,
            {
                type: FORMAT_NAME,
                attributes: { 'data-token': token },
            } as unknown as Parameters<typeof applyFormat>[1],
            start,
            end
        );
    }

    return out;
}

/**
 * Utility to strip all chip decorations from a RichText value — used
 * before persisting to keep the saved HTML clean.
 *
 * @since 1.4.0
 */
export function stripTokenDecorations(value: RichTextValue): RichTextValue {
    if (!value || typeof value !== 'object') return value;
    return removeFormat(value, FORMAT_NAME, 0, (value as { text?: string }).text?.length ?? 0);
}
