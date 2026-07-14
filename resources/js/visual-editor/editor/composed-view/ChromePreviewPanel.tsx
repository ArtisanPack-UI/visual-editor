/**
 * Inert preview panel that renders template chrome (header / footer) as
 * a static outline around the block canvas when the composed view is
 * on. Not editable, not selectable — just enough to give authors a
 * sense of what wraps their content on the frontend.
 *
 * Deliberately does NOT go through Gutenberg's block-editor. Handing
 * template chrome to `BlockEditorProvider` (whether as a swapped value
 * or a second provider) fights Gutenberg's stateful sync in ways that
 * surface as validation warnings + editor freezes — see the notes in
 * editor-app.tsx. Rendering the outline as plain divs sidesteps that
 * entirely; full inline chrome rendering is a follow-up.
 *
 * @since 1.1.0
 */

import { __ } from '@wordpress/i18n';
import type { BlockInstance } from '@wordpress/blocks';

import { TEXT_DOMAIN } from '../../vendor/i18n';

export interface ChromePreviewPanelProps {
    label: string;
    templateName: string;
    blocks: readonly BlockInstance[];
}

/**
 * Walk a chrome block tree and produce a short human-readable list of
 * the block names so the preview panel shows "what's here" without
 * pretending to render the real blocks.
 */
function collectBlockLabels(blocks: readonly BlockInstance[]): string[] {
    const out: string[] = [];

    for (const block of blocks) {
        out.push(block.name);

        if (block.innerBlocks.length > 0) {
            for (const nested of collectBlockLabels(block.innerBlocks)) {
                out.push(nested);
            }
        }
    }

    return out;
}

export function ChromePreviewPanel(
    props: ChromePreviewPanelProps
): JSX.Element {
    const { label, templateName, blocks } = props;
    const labels = collectBlockLabels(blocks);

    return (
        <aside
            className="ap-visual-editor__chrome-preview"
            data-testid={`ap-visual-editor-chrome-preview-${label.toLowerCase()}`}
            aria-label={__('Template chrome preview', TEXT_DOMAIN)}
        >
            <header className="ap-visual-editor__chrome-preview-header">
                <span className="ap-visual-editor__chrome-preview-label">
                    {label}
                </span>
                <span className="ap-visual-editor__chrome-preview-template">
                    {templateName}
                </span>
            </header>
            {labels.length === 0 ? (
                <p className="ap-visual-editor__chrome-preview-empty">
                    {__('(empty)', TEXT_DOMAIN)}
                </p>
            ) : (
                <ul className="ap-visual-editor__chrome-preview-list">
                    {labels.map((name, index) => (
                        <li
                            key={`${name}-${index}`}
                            className="ap-visual-editor__chrome-preview-item"
                        >
                            {name}
                        </li>
                    ))}
                </ul>
            )}
        </aside>
    );
}
