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
import { useSelect } from '@wordpress/data';
import { useState } from '@wordpress/element';
import { addFilter } from '@wordpress/hooks';
import { __ } from '@wordpress/i18n';

import TokenInserterModal from './token-inserter-modal';

// Curly-brace token glyph. Plain React <svg> so we don't depend on
// @wordpress/components' SVG/Path wrappers or @wordpress/icons — both
// have subtle version-to-version rendering differences in
// ToolbarButton across block-editor releases.
const TOKEN_ICON = (
    <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" width="24" height="24" fill="currentColor" aria-hidden="true">
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
    // Kept as an index signature so we can pass through unmodified.
    [key: string]: unknown;
}

const withDynamicContentToolbar = createHigherOrderComponent(
    (BlockEdit: React.ComponentType<BlockEditProps>) => {
        return function DynamicContentToolbarWrapper(props: BlockEditProps) {
            const [open, setOpen] = useState(false);
            const name = typeof props.name === 'string' ? props.name : '';

            // Gate on selection — see notes on the button/image binding
            // panels. Prevents duplicate BlockControls fills when the
            // block's edit is remounted inside a container's InnerBlocks
            // render tree.
            const isCurrentlySelected = useSelect(
                (select: (store: string) => { getSelectedBlockClientId?: () => string | null }) => {
                    const store = select('core/block-editor');
                    return typeof store?.getSelectedBlockClientId === 'function'
                        && store.getSelectedBlockClientId() === props.clientId;
                },
                [ props.clientId ]
            );

            if (!TARGET_BLOCKS.has(name)) {
                return <BlockEdit {...props} />;
            }

            const insertToken = (token: string) => {
                const attrs = (props.attributes ?? {}) as Record<string, unknown>;
                const contentKey = 'content' in attrs ? 'content' : null;
                if (!contentKey || typeof props.setAttributes !== 'function') return;
                const current = attrs[contentKey];
                const rendered = typeof current === 'string' ? current : '';
                // Simple text append; a richer implementation would splice
                // at the caret via `useAnchor` but that requires deeper
                // rich-text integration than we ship in v1.
                props.setAttributes({ [contentKey]: `${rendered}${token}` });
            };

            return (
                <>
                    {isCurrentlySelected && (
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
                    )}
                    <BlockEdit {...props} />
                    {isCurrentlySelected && (
                        <TokenInserterModal
                            isOpen={open}
                            onClose={() => setOpen(false)}
                            onInsert={insertToken}
                        />
                    )}
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
