/**
 * `editor.BlockEdit` HOC — mount the
 * `ap.visual-editor.background-controls` filter on every block that
 * opts into a background support (#649).
 *
 * Centralizes the target-block decision inside the editor so external
 * packages contributing a background/appearance control (Liquid Glass,
 * noise, gradient mesh, etc.) don't have to enumerate blocks and wrap
 * `editor.BlockEdit` themselves. They just add a filter callback
 * returning a `BackgroundControl` descriptor; this HOC decides the
 * gating and the placement.
 *
 * Placement: a full-width `PanelBody` per control inside the default
 * `InspectorControls` group. We considered `group="color"` (which sits
 * next to the built-in Background color picker) but that slot renders
 * inside a `ToolsPanel` that either drops non-`ToolsPanelItem` children
 * or squeezes them into a narrow column — the same trap `liquid-glass`
 * hit before it moved to a plain `PanelBody`. The default slot gives
 * every control room to render normally and keeps the API surface
 * simple (packages return a single `render` — no ToolsPanel wiring
 * required).
 *
 * @package @artisanpack-ui/visual-editor
 * @since 1.4.0
 */

import { InspectorControls } from '@wordpress/block-editor';
import { PanelBody } from '@wordpress/components';
import { createHigherOrderComponent } from '@wordpress/compose';
import { getBlockType } from '@wordpress/blocks';
import { addFilter } from '@wordpress/hooks';
import type { ComponentType } from 'react';

import {
    getFilteredBackgroundControls,
    type BackgroundControlContext,
} from './background-controls';

const FILTER_HOOK = 'editor.BlockEdit';
const FILTER_NAMESPACE =
    'artisanpack-ui/visual-editor/background-controls';

const REGISTERED_KEY = Symbol.for(
    'artisanpack-ui.visual-editor.background-controls.registered'
);

interface GlobalSentinelHost {
    [REGISTERED_KEY]?: boolean;
}

interface BlockEditProps {
    name: string;
    clientId?: string;
    attributes: Record<string, unknown>;
    setAttributes: (updates: Record<string, unknown>) => void;
    [key: string]: unknown;
}

/**
 * Resolve the block type's `supports` object as a defensive deep
 * clone. Returns an empty object when the block type is unknown so
 * callers can uniformly probe keys without null-guarding first.
 *
 * The clone matters — the raw `supports` object is a live reference to
 * the `@wordpress/blocks` registry, and this value is handed to every
 * third-party `ap.visual-editor.background-controls` filter callback
 * as `context.blockSupports`. A callback that mutates the received
 * object would (without the clone) silently tamper with the shared
 * registry entry every other block-support consumer reads from.
 */
function resolveBlockSupports(name: string): Record<string, unknown> {
    const blockType = getBlockType(name);

    if (!blockType) {
        return {};
    }

    const supports = (blockType as { supports?: unknown }).supports;

    if (supports === null || typeof supports !== 'object') {
        return {};
    }

    // `structuredClone` is available in every browser that ships a
    // modern Gutenberg (Safari 15.4+, Chrome/Edge 98+, Firefox 94+),
    // matches Node ≥17 for tests, and is the correct primitive here:
    // block `supports` objects are plain data (no functions, no
    // symbols) so structuredClone reproduces them losslessly.
    return structuredClone(supports) as Record<string, unknown>;
}

/**
 * A block opts into background controls when it declares either
 * `supports.background` (image / gradient background support) or
 * `supports.color.background` (color background — the default when
 * `supports.color` is enabled without an explicit `background: false`
 * override).
 *
 * The check is deliberately permissive: package authors gate their own
 * contribution inside the filter callback using the resolved
 * `blockSupports` object, so it's fine for the HOC to run for a
 * slightly broader set of blocks than any single package cares about.
 */
export function blockSupportsBackground(
    supports: Record<string, unknown>
): boolean {
    if (supports.background) {
        return true;
    }

    const color = supports.color;

    if (color === true) {
        return true;
    }

    if (color !== null && typeof color === 'object') {
        const colorBackground = (color as Record<string, unknown>).background;
        // `undefined` means "inherit the default", which for the color
        // support is `true`. Only an explicit `false` disables it.
        return colorBackground !== false;
    }

    return false;
}

export const withBackgroundControls = createHigherOrderComponent(
    (BlockEdit: ComponentType<BlockEditProps>) => {
        function BackgroundControlsBlockEdit(
            props: BlockEditProps
        ): JSX.Element {
            const blockSupports = resolveBlockSupports(props.name);

            if (!blockSupportsBackground(blockSupports)) {
                return <BlockEdit {...props} />;
            }

            // `Object.freeze` on a shallow copy is enough to signal
            // read-only at the top level and to defend against the most
            // common footgun (a filter callback assigning to
            // `context.attributes.foo = ...`). Deep-freezing would
            // require walking the tree on every render — too expensive
            // for a hot path; the type is `Readonly<...>` and the
            // context docstring calls it out.
            const context: BackgroundControlContext = {
                attributes: Object.freeze({ ...props.attributes }),
                setAttributes: props.setAttributes,
                clientId: props.clientId ?? '',
                blockName: props.name,
                blockSupports: Object.freeze(blockSupports),
            };

            const controls = getFilteredBackgroundControls(context);

            // Always return the Fragment shape so the child `BlockEdit`
            // keeps a stable parent slot type as `controls` transitions
            // from empty to non-empty (e.g. HMR, lazy-loaded package
            // bundles, filter callbacks reading external stores). A
            // top-level switch between `<BlockEdit/>` and a Fragment
            // would remount BlockEdit and lose RichText cursor, drag,
            // and child hook state.
            return (
                <>
                    <BlockEdit {...props} />
                    {controls.length > 0 && (
                        <InspectorControls>
                            {controls.map((control) => (
                                <PanelBody
                                    key={control.id}
                                    title={control.label}
                                    initialOpen={false}
                                >
                                    {control.render()}
                                </PanelBody>
                            ))}
                        </InspectorControls>
                    )}
                </>
            );
        }

        BackgroundControlsBlockEdit.displayName =
            'BackgroundControlsBlockEdit';

        return BackgroundControlsBlockEdit;
    },
    'withBackgroundControls'
);

/**
 * Register the `editor.BlockEdit` HOC at most once per page. Idempotent
 * — safe to call from both the post-editor and site-editor bootstrap
 * paths.
 */
export function registerBackgroundControls(): void {
    const host = globalThis as unknown as GlobalSentinelHost;

    if (host[REGISTERED_KEY]) {
        return;
    }

    addFilter(FILTER_HOOK, FILTER_NAMESPACE, withBackgroundControls);
    host[REGISTERED_KEY] = true;
}
