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
 */
export interface BackgroundControlContext {
    /** Live block attributes. Treat as read-only inside the filter. */
    attributes: Record<string, unknown>;
    /** Standard Gutenberg attribute setter for the selected block. */
    setAttributes: (attrs: Record<string, unknown>) => void;
    /** Selected block's clientId. */
    clientId: string;
    /** Selected block's registered name (e.g. `core/group`). */
    blockName: string;
    /**
     * Resolved `supports` object from the block type. Packages use
     * `blockSupports.background` (or a nested key like
     * `blockSupports.color?.background`) to gate their contribution.
     */
    blockSupports: Record<string, unknown>;
}

function isBackgroundControl(value: unknown): value is BackgroundControl {
    return (
        value !== null &&
        typeof value === 'object' &&
        typeof (value as BackgroundControl).id === 'string' &&
        typeof (value as BackgroundControl).label === 'string' &&
        typeof (value as BackgroundControl).render === 'function'
    );
}

/**
 * Apply the filter, drop malformed entries, sort by `priority` (stable),
 * and dedupe by `id` (last-wins). Kept pure so the HOC can call it
 * inside render without scheduling work.
 */
export function getFilteredBackgroundControls(
    context: BackgroundControlContext
): BackgroundControl[] {
    const raw = applyFilters(
        BACKGROUND_CONTROLS_FILTER,
        [] as BackgroundControl[],
        context
    );

    if (!Array.isArray(raw)) {
        return [];
    }

    const valid = raw.filter(isBackgroundControl);

    // Pair with the original index before comparing so we get a stable
    // sort on engines whose `Array.prototype.sort` isn't stable, and so
    // ties fall back to registration order.
    const sorted = valid
        .map((control, index) => ({ control, index }))
        .sort((a, b) => {
            const priorityA =
                a.control.priority ?? DEFAULT_BACKGROUND_CONTROL_PRIORITY;
            const priorityB =
                b.control.priority ?? DEFAULT_BACKGROUND_CONTROL_PRIORITY;

            if (priorityA !== priorityB) {
                return priorityA - priorityB;
            }

            return a.index - b.index;
        })
        .map(({ control }) => control);

    // Dedupe by id — two packages racing on the same identifier, or a
    // single package re-registering after HMR, would otherwise render
    // twice with duplicate React keys. Last-wins policy mirrors
    // `@wordpress/hooks` filter composition. `Map#set` on an existing
    // key updates in-place, so delete-then-reinsert to move the later
    // entry to its later position.
    const deduped = new Map<string, BackgroundControl>();

    for (const control of sorted) {
        if (deduped.has(control.id)) {
            deduped.delete(control.id);
        }
        deduped.set(control.id, control);
    }

    return Array.from(deduped.values());
}
