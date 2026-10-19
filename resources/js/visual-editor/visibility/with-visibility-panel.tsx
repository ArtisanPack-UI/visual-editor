/**
 * `editor.BlockEdit` HOC — inject the VisibilityPanel into every
 * block that opts into `supports.artisanpackVisibility`
 * (#491 · #492 · #493).
 *
 * Follows the same pattern as `with-animations-panel.tsx` — the
 * attribute is registered by `register-attribute.ts` at
 * `blocks.registerBlockType` time so its presence on the block's
 * attribute schema is the opt-in signal.
 *
 * @package @artisanpack-ui/visual-editor
 * @since 1.4.0
 */

import { InspectorControls } from '@wordpress/block-editor';
import { getBlockType } from '@wordpress/blocks';
import { createHigherOrderComponent } from '@wordpress/compose';
import { addFilter } from '@wordpress/hooks';
import { __ } from '@wordpress/i18n';
import type { ComponentType } from 'react';

import { VisibilityPanel } from './VisibilityPanel';
import { searchUsers } from './user-search';
import type { VisibilityAttribute, VisibilityBreakpointOption } from './types';

const FILTER_HOOK      = 'editor.BlockEdit';
const FILTER_NAMESPACE = 'artisanpack-ui/visual-editor/visibility-panel';

const REGISTERED_KEY = Symbol.for(
    'artisanpack-ui.visual-editor.visibility-panel.registered',
);

interface GlobalSentinelHost {
    [REGISTERED_KEY]?: boolean;
    __artisanpackVisibilityRoles?: Array<{ slug: string; label: string }>;
    __artisanpackVisibilityBreakpoints?: VisibilityBreakpointOption[];
}

/**
 * Called by the editor bootstrap once the responsive `BreakpointRegistry`
 * has been hydrated from the server snapshot. Publishing the full list
 * of registered breakpoints (with their widths) into a global lets the
 * panel group the device keys, the Desktop shortcut and any legacy
 * ranges against the host's actual widths.
 *
 * @since 1.4.0
 * @since 1.15.2 Options carry `minWidthPx` / `maxWidthPx` (#974).
 */
export function setVisibilityBreakpoints(next: VisibilityBreakpointOption[]): void {
    const host = globalThis as unknown as GlobalSentinelHost;
    host.__artisanpackVisibilityBreakpoints = next.filter((bp) => typeof bp.key === 'string' && bp.key !== '');
}

/**
 * Publishes the current site's role list so the User Role subsection's
 * chip list renders real options.
 *
 * @since 1.4.0
 */
export function setVisibilityRoles(next: Array<{ slug: string; label: string }>): void {
    const host = globalThis as unknown as GlobalSentinelHost;
    host.__artisanpackVisibilityRoles = next.filter((r) => typeof r.slug === 'string' && r.slug !== '');
}

interface BlockEditProps {
    name: string;
    attributes: Record<string, unknown> & {
        artisanpackVisibility?: VisibilityAttribute | null;
    };
    setAttributes: (updates: Record<string, unknown>) => void;
    [key: string]: unknown;
}

/**
 * Whether the block opted into visibility. Checks the registered
 * attribute schema, not just the instance: blocks hydrated from an
 * entity (e.g. `core/navigation-link` items loaded from a
 * `wp_navigation` menu by the core-data shim) carry their saved
 * attributes verbatim, so the `default: null` key is absent even
 * though the block type supports it.
 */
function blockSupports(name: string, attributes: BlockEditProps['attributes']): boolean {
    if ('artisanpackVisibility' in attributes) {
        return true;
    }

    const schema = getBlockType(name)?.attributes;

    return schema !== undefined && schema !== null && 'artisanpackVisibility' in schema;
}

function readRoles(): Array<{ slug: string; label: string }> {
    const host = globalThis as unknown as GlobalSentinelHost;
    return host.__artisanpackVisibilityRoles ?? [];
}

function readBreakpoints(): VisibilityBreakpointOption[] {
    const host = globalThis as unknown as GlobalSentinelHost;
    // Fallback mirrors the package's default registry: the device keys
    // the panel offers as Mobile / Tablet (#974) plus the legacy
    // min-width keys, so saved content using them still shows checked.
    return host.__artisanpackVisibilityBreakpoints ?? [
        { key: 'tablet', label: __('Tablet', 'artisanpack-visual-editor'), maxWidthPx: 1023 },
        { key: 'mobile', label: __('Mobile', 'artisanpack-visual-editor'), maxWidthPx: 767 },
        { key: 'sm',  label: 'sm',  minWidthPx: 640 },
        { key: 'md',  label: 'md',  minWidthPx: 768 },
        { key: 'lg',  label: 'lg',  minWidthPx: 1024 },
        { key: 'xl',  label: 'xl',  minWidthPx: 1280 },
        { key: '2xl', label: '2xl', minWidthPx: 1536 },
    ];
}

export const withVisibilityPanel = createHigherOrderComponent(
    (BlockEdit: ComponentType<BlockEditProps>) => {
        function VisibilityBlockEdit(props: BlockEditProps): JSX.Element {
            const supports = blockSupports(props.name, props.attributes);

            if (!supports) {
                return <BlockEdit {...props} />;
            }

            const value = (props.attributes.artisanpackVisibility ?? null) as VisibilityAttribute | null;

            return (
                <>
                    <BlockEdit {...props} />
                    <InspectorControls>
                        <VisibilityPanel
                            value={value}
                            onChange={(next) => props.setAttributes({ artisanpackVisibility: next })}
                            breakpointOptions={readBreakpoints()}
                            roleOptions={readRoles()}
                            searchUsers={searchUsers}
                        />
                    </InspectorControls>
                </>
            );
        }

        VisibilityBlockEdit.displayName = 'VisibilityBlockEdit';

        return VisibilityBlockEdit;
    },
    'withVisibilityPanel',
);

export function registerVisibilityPanel(): void {
    const host = globalThis as unknown as GlobalSentinelHost;

    if (host[REGISTERED_KEY]) {
        return;
    }

    addFilter(FILTER_HOOK, FILTER_NAMESPACE, withVisibilityPanel);
    host[REGISTERED_KEY] = true;
}
