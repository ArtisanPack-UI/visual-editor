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
import { ToolbarButton, ToolbarGroup, SVG, Path } from '@wordpress/components';
import { createHigherOrderComponent } from '@wordpress/compose';
import { useState } from '@wordpress/element';
import { addFilter } from '@wordpress/hooks';
import { __ } from '@wordpress/i18n';

import TokenInserterModal from './token-inserter-modal';

// Curly-brace token glyph — inline SVG so we don't depend on
// @wordpress/icons and its default dashicon isn't inconsistently
// resolved across block-editor versions.
const TOKEN_ICON = (
    <SVG xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24">
        <Path d="M8 4c-2.2 0-3 1-3 3v3c0 1-.5 2-2 2v1c1.5 0 2 1 2 2v3c0 2 .8 3 3 3v-1c-1.4 0-2-.4-2-2v-3c0-1.2-.5-2-1.5-2.5C5.5 12 6 11.2 6 10V7c0-1.6.6-2 2-2V4zM16 4c2.2 0 3 1 3 3v3c0 1 .5 2 2 2v1c-1.5 0-2 1-2 2v3c0 2-.8 3-3 3v-1c1.4 0 2-.4 2-2v-3c0-1.2.5-2 1.5-2.5C18.5 12 18 11.2 18 10V7c0-1.6-.6-2-2-2V4z" />
    </SVG>
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
