/**
 * Fallback routing for the composed view (#624).
 *
 * Turns an `AppliedTemplateState` into the template the canvas should
 * actually compose against, plus the copy that explains the choice. Three
 * states route to the built-in default template:
 *
 *   - `missing` / `empty`       — the content has no template selected.
 *   - `missing` / `unknown-slug` — the selected slug resolves to nothing.
 *   - `error`                    — the request failed outright.
 *
 * The first two are ordinary author states and read as a warning; the
 * third is a fault and reads as an error, but all three land on the same
 * canvas so the author is never left staring at a toggle that appears to
 * do nothing.
 *
 * Kept as a pure function (rather than folded into `editor-app.tsx`) so
 * the routing is testable without mounting the whole editor.
 *
 * @since 1.6.0
 */

import { __, sprintf } from '@wordpress/i18n';
import type { BlockInstance } from '@wordpress/blocks';

import { TEXT_DOMAIN } from '../../vendor/i18n';

import { createDefaultTemplateBlocks, defaultTemplateName } from './default-template';
import type { AppliedTemplateState } from './use-applied-template';

export type ComposedFallbackReason = 'empty' | 'unknown-slug' | 'error';

export interface ComposedTemplateSelection {
    /** Blocks to split + hydrate into the canvas chrome. */
    blocks: readonly BlockInstance[];
    /** Template name, for the notice and the #623 ribbon. */
    name: string;
    /**
     * Template slug, or `null` on the fallback. `null` is the signal that
     * there is no template to deep-link to, so the ribbon's
     * **Edit template ↗** CTA has to be hidden.
     */
    slug: string | null;
    /** `null` when a real template resolved. */
    fallbackReason: ComposedFallbackReason | null;
}

export interface ComposedFallbackNotice {
    tone: 'warning' | 'error';
    message: string;
}

/**
 * Pick the template the composed canvas renders against.
 *
 * Returns `null` while the hook is `idle` or `loading` — there is nothing
 * to compose yet, and falling back mid-flight would flash the default
 * template before the real one arrives.
 */
export function selectComposedTemplate(
    state: AppliedTemplateState
): ComposedTemplateSelection | null {
    if (state.status === 'idle' || state.status === 'loading') {
        return null;
    }

    if (state.status === 'ok') {
        return {
            blocks: state.template.blocks,
            name: state.template.name,
            slug: state.template.slug,
            fallbackReason: null,
        };
    }

    return {
        blocks: createDefaultTemplateBlocks(),
        name: defaultTemplateName(),
        slug: null,
        fallbackReason:
            state.status === 'missing' ? state.missing.reason : 'error',
    };
}

/**
 * Copy explaining a fallback. Returns `null` when no fallback happened,
 * so the caller can use a non-null result as "there is something to say".
 */
export function composedFallbackNotice(
    state: AppliedTemplateState
): ComposedFallbackNotice | null {
    if (state.status === 'error') {
        return {
            tone: 'error',
            message: __(
                'The template could not be loaded — previewing on the default template.',
                TEXT_DOMAIN
            ),
        };
    }

    if (state.status !== 'missing') {
        return null;
    }

    if (state.missing.reason === 'unknown-slug') {
        const slug = state.missing.slug ?? '';

        // An `unknown-slug` miss with no slug in the payload is
        // contradictory, but a hand-rolled or proxied response can produce
        // it. Naming an empty template in the copy reads as a bug, so fall
        // through to the no-template wording.
        if (slug !== '') {
            return {
                tone: 'warning',
                message: sprintf(
                    /* translators: %s: requested template slug. */
                    __(
                        'The template “%s” is unavailable — previewing on the default template.',
                        TEXT_DOMAIN
                    ),
                    slug
                ),
            };
        }
    }

    return {
        tone: 'warning',
        message: __(
            'No template is set for this content — previewing on the default template.',
            TEXT_DOMAIN
        ),
    };
}
