/**
 * `ap.visual-editor.background-controls` — filter that lets external
 * packages contribute controls to the shared background / appearance
 * area of any block that opts into a background support (#649).
 *
 * External packages register their controls via `@wordpress/hooks`:
 *
 *     addFilter(
 *         'ap.visual-editor.background-controls',
 *         'my-package/glass',
 *         (controls, { attributes, setAttributes, blockSupports }) => {
 *             if ( ! blockSupports.background ) {
 *                 return controls;
 *             }
 *             return [
 *                 ...controls,
 *                 {
 *                     id: 'my-package/glass',
 *                     label: 'Glass effect',
 *                     priority: 20,
 *                     render: () => <GlassPanel ... />,
 *                 },
 *             ];
 *         },
 *     );
 *
 * The editor calls `getFilteredBackgroundControls(context)` from the
 * `editor.BlockEdit` HOC in `with-background-controls.tsx` on every
 * render of a supporting block. Sorting, de-duplication, and the
 * "priority defaults to 10, lower first" contract live here so the HOC
 * stays about placement only.
 *
 * @package @artisanpack-ui/visual-editor
 * @since 1.4.0
 */

import { applyFilters } from '@wordpress/hooks';
import type { ReactNode } from 'react';

/** Filter name — exported so hosts can type against a constant. */
export const BACKGROUND_CONTROLS_FILTER = 'ap.visual-editor.background-controls';

/** Default priority for controls that don't set one. */
export const DEFAULT_BACKGROUND_CONTROL_PRIORITY = 10;

/**
 * Descriptor a package returns from the filter for each control it wants
 * to render in the block's background panel.
 */
export interface BackgroundControl {
    /**
     * Stable, namespaced identifier — used as the React `key` and to
     * dedupe two packages racing on the same slot (last-wins, mirroring
     * `@wordpress/hooks`).
     */
    id: string;
    /** Visible section heading. Should already be translated by the caller. */
    label: string;
    /**
     * Sort key. Lower renders earlier. Defaults to
     * {@link DEFAULT_BACKGROUND_CONTROL_PRIORITY} when omitted.
     */
    priority?: number;
    /** React render callback. Invoked once per render, no arguments. */
    render: () => ReactNode;
}

/**
 * Context passed to filter callbacks so a package can decide whether to
 * contribute a control (and what to render) based on the currently-
 * selected block.
 *
 * All fields except `setAttributes` are **read-only** — the HOC freezes
 * `attributes` and hands out a defensive clone of `blockSupports`, but
 * treat every field as immutable regardless. Mutating `attributes`
 * bypasses React reconciliation and undo history; mutating
 * `blockSupports` would (if it weren't cloned) corrupt the shared
 * `@wordpress/blocks` registry that gate-checks every other feature in
 * the editor.
 */
export interface BackgroundControlContext {
    /**
     * Frozen block attributes. Treat as read-only. To modify, call
     * `setAttributes({...})` — the standard Gutenberg pattern.
     */
    attributes: Readonly<Record<string, unknown>>;
    /** Standard Gutenberg attribute setter for the selected block. */
    setAttributes: (attrs: Record<string, unknown>) => void;
    /** Selected block's clientId. */
    clientId: string;
    /** Selected block's registered name (e.g. `artisanpack/group`). */
    blockName: string;
    /**
     * Deep-cloned `supports` object from the block type. Packages use
     * `blockSupports.background` (or a nested key like
     * `blockSupports.color?.background`) to gate their contribution.
     * Treat as read-only.
     */
    blockSupports: Readonly<Record<string, unknown>>;
}

function isBackgroundControl(value: unknown): value is BackgroundControl {
    if (
        value === null ||
        typeof value !== 'object' ||
        typeof (value as BackgroundControl).id !== 'string' ||
        (value as BackgroundControl).id === '' ||
        typeof (value as BackgroundControl).label !== 'string' ||
        typeof (value as BackgroundControl).render !== 'function'
    ) {
        return false;
    }

    // `priority` is optional, but if present it must be a finite number.
    // A `NaN` or string priority would make the sort comparator return
    // `NaN`, giving engine-dependent order across browsers.
    const priority = (value as BackgroundControl).priority;
    if (priority !== undefined && !Number.isFinite(priority)) {
        return false;
    }

    return true;
}

/**
 * Apply the filter, drop malformed entries, dedupe by `id` (last-wins),
 * then sort by `priority` (default `10`, lower first). Dedupe happens
 * BEFORE sort so "last-wins" tracks registration order, not sort order
 * — a later `addFilter` with a lower priority still overrides an
 * earlier registration at the same id.
 *
 * If a filter callback throws (a third-party bug), the exception is
 * caught, logged, and the function returns an empty list. Without the
 * guard, one bad callback would trip Gutenberg's `BlockCrashBoundary`
 * on every affected block for the rest of the session.
 *
 * Kept pure so the HOC can call it inside render without scheduling
 * work.
 */
export function getFilteredBackgroundControls(
    context: BackgroundControlContext
): BackgroundControl[] {
    let raw: unknown;

    try {
        raw = applyFilters(
            BACKGROUND_CONTROLS_FILTER,
            [] as BackgroundControl[],
            context
        );
    } catch (error) {
        // eslint-disable-next-line no-console
        console.error(
            '[artisanpack-ui/visual-editor] A ' +
                `"${BACKGROUND_CONTROLS_FILTER}" filter callback threw. ` +
                'Skipping background controls for this render.',
            error
        );
        return [];
    }

    if (!Array.isArray(raw)) {
        return [];
    }

    const valid = raw.filter(isBackgroundControl);

    // Dedupe by id — two packages racing on the same identifier, or a
    // single package re-registering after HMR, would otherwise render
    // twice with duplicate React keys. Last-wins mirrors how
    // `@wordpress/hooks` composes filters: a later `addFilter` at the
    // same namespace/priority overrides an earlier one. Doing the
    // dedupe here (pre-sort) means the surviving entry is always the
    // last one the filter chain contributed, regardless of its
    // priority relative to the earlier duplicate.
    const dedupedByRegistration = new Map<string, BackgroundControl>();

    for (const control of valid) {
        if (dedupedByRegistration.has(control.id)) {
            dedupedByRegistration.delete(control.id);
        }
        dedupedByRegistration.set(control.id, control);
    }

    // ES2019 mandates `Array.prototype.sort` be stable, so ties fall
    // back to insertion (= registration) order automatically.
    return Array.from(dedupedByRegistration.values()).sort((a, b) => {
        const priorityA = a.priority ?? DEFAULT_BACKGROUND_CONTROL_PRIORITY;
        const priorityB = b.priority ?? DEFAULT_BACKGROUND_CONTROL_PRIORITY;
        return priorityA - priorityB;
    });
}
