/**
 * BlockControls toolbar button opening the Token Inserter modal.
 *
 * Attached via `editor.BlockEdit` HOC filter — the wrapper appends a
 * BlockControls slot to every text-bearing block so the Token Inserter
 * lives one click away regardless of which block the author is in.
 *
 * @since 1.4.0
 */

import { BlockControls } from '@wordpress/block-editor';
import { ToolbarButton, ToolbarGroup } from '@wordpress/components';
import { createHigherOrderComponent } from '@wordpress/compose';
import { useState } from '@wordpress/element';
import { addFilter } from '@wordpress/hooks';
import { __ } from '@wordpress/i18n';

import TokenInserterModal from './token-inserter-modal';

// Curly-brace glyph. Plain React <svg> so we avoid @wordpress/icons
// and @wordpress/components' SVG/Path wrappers — both have version-
// to-version inconsistencies in how ToolbarButton renders them.
const TOKEN_ICON = (
    <svg
        xmlns="http://www.w3.org/2000/svg"
        viewBox="0 0 24 24"
        width="24"
        height="24"
        fill="currentColor"
        aria-hidden="true"
    >
        <path d="M8 5c-1.7 0-3 1.3-3 3v2.5c0 .8-.7 1.5-1.5 1.5H3v1h.5c.8 0 1.5.7 1.5 1.5V17c0 1.7 1.3 3 3 3h.5v-1H8c-1.1 0-2-.9-2-2v-2.5c0-.9-.5-1.7-1.2-2 .7-.4 1.2-1.2 1.2-2V8c0-1.1.9-2 2-2h.5V5H8zM16 5h-.5v1h.5c1.1 0 2 .9 2 2v2.5c0 .8.5 1.6 1.2 2-.7.4-1.2 1.2-1.2 2V17c0 1.1-.9 2-2 2h-.5v1h.5c1.7 0 3-1.3 3-3v-2.5c0-.8.7-1.5 1.5-1.5h.5v-1h-.5c-.8 0-1.5-.7-1.5-1.5V8c0-1.7-1.3-3-3-3z" />
    </svg>
);

const TARGET_BLOCKS = new Set<string>([
    'artisanpack/paragraph',
    'artisanpack/heading',
    'artisanpack/list-item',
    'artisanpack/quote',
    'artisanpack/pullquote',
    'artisanpack/verse',
    'core/paragraph',
    'core/heading',
]);

interface EditProps {
    name?: string;
    clientId?: string;
    attributes?: Record<string, unknown>;
    setAttributes?: (patch: Record<string, unknown>) => void;
}

interface BlockEditProps extends EditProps {
    [key: string]: unknown;
}

const withDynamicContentToolbar = createHigherOrderComponent(
    (BlockEdit: React.ComponentType<BlockEditProps>) => {
        return function DynamicContentToolbarWrapper(props: BlockEditProps) {
            const [open, setOpen] = useState(false);
            const name = typeof props.name === 'string' ? props.name : '';

            if (!TARGET_BLOCKS.has(name)) {
                return <BlockEdit {...props} />;
            }

            const insertToken = (token: string) => {
                const attrs = (props.attributes ?? {}) as Record<string, unknown>;
                const contentKey = 'content' in attrs ? 'content' : null;
                if (!contentKey || typeof props.setAttributes !== 'function') return;
                const current = attrs[contentKey];
                const rendered = typeof current === 'string' ? current : '';
                props.setAttributes({ [contentKey]: `${rendered}${token}` });
            };

            // BlockControls is auto-scoped to the currently-selected block by
            // Gutenberg's block list. No `useSelect` gate needed here —
            // adding one caused the button to intermittently disappear
            // during selection transitions.
            return (
                <>
                    <BlockControls>
                        <ToolbarGroup>
                            <ToolbarButton
                                icon={TOKEN_ICON}
                                label={__('Insert Dynamic Content token', 'artisanpack-visual-editor')}
                                onClick={() => setOpen(true)}
                                showTooltip
                            />
                        </ToolbarGroup>
                    </BlockControls>
                    <BlockEdit {...props} />
                    <TokenInserterModal
                        isOpen={open}
                        onClose={() => setOpen(false)}
                        onInsert={insertToken}
                    />
                </>
            );
        };
    },
    'withDynamicContentToolbar'
);

let registered = false;

export function registerDynamicContentToolbarButton(): void {
    if (registered) return;
    registered = true;

    addFilter(
        'editor.BlockEdit',
        'artisanpack-ui/visual-editor/dynamic-content-toolbar',
        withDynamicContentToolbar
    );
}
