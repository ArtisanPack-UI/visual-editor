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
import { createHigherOrderComponent } from '@wordpress/compose';
import { addFilter } from '@wordpress/hooks';
import type { ComponentType } from 'react';

import { VisibilityPanel } from './VisibilityPanel';
import { searchUsers } from './user-search';
import type { VisibilityAttribute } from './types';

const FILTER_HOOK      = 'editor.BlockEdit';
const FILTER_NAMESPACE = 'artisanpack-ui/visual-editor/visibility-panel';

const REGISTERED_KEY = Symbol.for(
    'artisanpack-ui.visual-editor.visibility-panel.registered',
);

interface GlobalSentinelHost {
    [REGISTERED_KEY]?: boolean;
    __artisanpackVisibilityRoles?: Array<{ slug: string; label: string }>;
    __artisanpackVisibilityBreakpoints?: Array<{ key: string; label: string }>;
}

interface BlockEditProps {
    name: string;
    attributes: Record<string, unknown> & {
        artisanpackVisibility?: VisibilityAttribute | null;
    };
    setAttributes: (updates: Record<string, unknown>) => void;
    [key: string]: unknown;
}

function blockSupports(_name: string, attributes: BlockEditProps['attributes']): boolean {
    return 'artisanpackVisibility' in attributes;
}

function readRoles(): Array<{ slug: string; label: string }> {
    const host = globalThis as unknown as GlobalSentinelHost;
    return host.__artisanpackVisibilityRoles ?? [];
}

function readBreakpoints(): Array<{ key: string; label: string }> {
    const host = globalThis as unknown as GlobalSentinelHost;
    return host.__artisanpackVisibilityBreakpoints ?? [
        { key: 'sm', label: 'Mobile' },
        { key: 'md', label: 'Tablet' },
        { key: 'lg', label: 'Desktop' },
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
