/**
 * Host-extensible document sidebar surface.
 *
 * Exposes two ways for a host app to add a panel to the Document tab of the
 * inspector sidebar:
 *
 *   1. `<PluginDocumentSettingPanel>` — a React slot-fill mirroring the
 *      WordPress `@wordpress/edit-post` component of the same name. Hosts
 *      render it anywhere inside the editor tree (typically from an
 *      `extraActions`-style slot in their own wrapper) and it appears in
 *      the sidebar.
 *
 *   2. `ap.visual-editor.document-panels` — a `@wordpress/hooks` filter
 *      taking the current panel list and returning an augmented one. This
 *      mirrors the `artisanpack-ui/hooks` idioms Laravel devs expect and
 *      is more convenient for panels registered during bootstrap rather
 *      than as children of the editor tree.
 *
 * Both surfaces funnel into a single `<DocumentPanelSlot />` that the
 * inspector renders at the end of the built-in panels. Ordering within
 * the slot follows React render order; filter-registered panels carry an
 * optional `order` integer (lower = earlier) so plugins loaded in any
 * order can still agree on positioning.
 */

import { PanelBody } from '@wordpress/components';
import { createSlotFill } from '@wordpress/components';
import { applyFilters } from '@wordpress/hooks';
import type { ReactNode } from 'react';

const SLOT_NAME = 'ApVisualEditorDocumentPanel';

const { Slot, Fill } = createSlotFill(SLOT_NAME);

/**
 * Filter name hosts use with `@wordpress/hooks` to register panels.
 * Exported so host code can type-check the filter name against this
 * constant instead of hand-typing the string.
 */
export const DOCUMENT_PANELS_FILTER = 'ap.visualEditor.documentPanels';

export interface DocumentPanelSpec {
    /** Stable identifier; used as the React `key` and `data-panel-name`. */
    id: string;
    /** Visible panel title. Should already be translated by the caller. */
    title: string;
    /** Start expanded. Defaults to `false` (mirrors WP's `PanelBody` default). */
    initialOpen?: boolean;
    /**
     * Sort key within the filter-registered list. Lower values render
     * earlier. Panels without an `order` fall back to registration order.
     * Slot-fill panels ignore this field entirely — their order is the
     * order React encounters the `<Fill>` elements.
     */
    order?: number;
    /** React render callback. Invoked once per render, no arguments. */
    render: () => ReactNode;
}

export interface PluginDocumentSettingPanelProps {
    /** Stable identifier emitted as `data-panel-name` on the panel root. */
    name: string;
    /** Visible panel title. Should already be translated by the caller. */
    title: string;
    /** Start expanded. Defaults to `false`. */
    initialOpen?: boolean;
    /** Extra class to merge onto the `PanelBody` root. */
    className?: string;
    children?: ReactNode;
}

/**
 * Slot-fill companion to the `ap.visual-editor.document-panels` filter.
 * Render it anywhere inside the editor tree to contribute a panel to the
 * Document tab.
 */
export function PluginDocumentSettingPanel(
    props: PluginDocumentSettingPanelProps
): JSX.Element {
    const { name, title, initialOpen = false, className, children } = props;

    return (
        <Fill>
            <PanelBody
                title={title}
                initialOpen={initialOpen}
                className={className}
            >
                <div data-panel-name={name}>{children}</div>
            </PanelBody>
        </Fill>
    );
}

/** Slot that renders every registered `<PluginDocumentSettingPanel>` fill. */
export function DocumentPanelSlot(): JSX.Element {
    return <Slot />;
}

/**
 * Runs the `ap.visual-editor.document-panels` filter against the empty
 * list and returns the registered panels, sorted by `order`. Kept pure so
 * the inspector can call it inside render without scheduling work.
 */
export function getFilteredDocumentPanels(): DocumentPanelSpec[] {
    const panels = applyFilters(
        DOCUMENT_PANELS_FILTER,
        [] as DocumentPanelSpec[]
    );

    if (!Array.isArray(panels)) {
        return [];
    }

    const valid = panels.filter(
        (panel): panel is DocumentPanelSpec =>
            panel !== null &&
            typeof panel === 'object' &&
            typeof (panel as DocumentPanelSpec).id === 'string' &&
            typeof (panel as DocumentPanelSpec).title === 'string' &&
            typeof (panel as DocumentPanelSpec).render === 'function'
    );

    // Stable sort by order, then by registration index. Array.prototype.sort
    // is not guaranteed stable across engines older than ES2019, so pair
    // with the original index before comparing.
    const sorted = valid
        .map((panel, index) => ({ panel, index }))
        .sort((a, b) => {
            const orderA = a.panel.order ?? 100;
            const orderB = b.panel.order ?? 100;

            if (orderA !== orderB) {
                return orderA - orderB;
            }

            return a.index - b.index;
        })
        .map(({ panel }) => panel);

    // Deduplicate by id — two plugins racing on the same identifier, or
    // a single plugin re-registering after HMR, would otherwise produce
    // duplicate React keys and render the panel twice. Last-wins policy
    // mirrors how `@wordpress/hooks` itself composes filters: a later
    // `addFilter` overrides an earlier one at the same priority.
    //
    // `Map#set` on an existing key updates the value but keeps the
    // original insertion position. We want the *later* panel to appear
    // at its later position (so ordering reflects the most recent
    // registration), so delete-then-reinsert when the key already
    // exists.
    const deduped = new Map<string, DocumentPanelSpec>();

    for (const panel of sorted) {
        if (deduped.has(panel.id)) {
            deduped.delete(panel.id);
        }
        deduped.set(panel.id, panel);
    }

    return Array.from(deduped.values());
}
