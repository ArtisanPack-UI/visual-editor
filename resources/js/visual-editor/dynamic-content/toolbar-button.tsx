/**
 * BlockControls toolbar button opening the Token Inserter modal.
 *
 * Attached via `editor.BlockEdit` HOC filter — the wrapper appends a
 * BlockControls slot to every text-bearing block so the Token Inserter
 * lives one click away regardless of which block the author is in.
 *
 * @since 1.4.0
 */

import { BlockControls, RichTextShortcut } from '@wordpress/block-editor';
import { ToolbarButton, ToolbarGroup } from '@wordpress/components';
import { createHigherOrderComponent } from '@wordpress/compose';
import { useState } from '@wordpress/element';
import { addFilter } from '@wordpress/hooks';
import { __ } from '@wordpress/i18n';
import { insert, create } from '@wordpress/rich-text';

import TokenInserterModal from './token-inserter-modal';

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
                    <BlockControls group="other">
                        <ToolbarGroup>
                            <ToolbarButton
                                icon="editor-code"
                                label={__('Insert Dynamic Content token', 'artisanpack-visual-editor')}
                                onClick={() => setOpen(true)}
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
