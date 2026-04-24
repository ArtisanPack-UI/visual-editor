/**
 * Shape + labels for the Styles-section navigator tree.
 *
 * Per design brief §3.7, the navigator mirrors theme.json's structure:
 * Typography / Colors / Layout / Blocks / Elements / Variations. Issue
 * #370 pulls Elements up to a sibling of Blocks (rather than nested
 * under Typography / Colors as the brief describes) so the breadcrumb
 * pattern "Styles ▸ Elements ▸ Link" mirrors "Styles ▸ Blocks ▸ Button" —
 * same depth, same mental model, one panel per node.
 *
 * Blocks' children are populated dynamically from the block-type
 * registry (GET /visual-editor/api/blocks) so the per-block tree
 * reflects only the blocks registered through the shim (per the issue's
 * out-of-scope note: don't list every Gutenberg block if it's disabled).
 */

import { __ } from '@wordpress/i18n';

import { TEXT_DOMAIN } from '../../vendor/i18n';

export type StylesNavigatorPanelId =
    | 'typography'
    | 'colors'
    | 'layout'
    | 'blocks'
    | 'elements'
    | 'variations';

export type StylesNavigatorNode =
    | { kind: 'panel'; id: StylesNavigatorPanelId; label: string }
    | { kind: 'block'; id: string; label: string };

export interface StylesNavigatorState {
    panel: StylesNavigatorPanelId;
    /** When `panel === 'blocks'`, the specific block the user drilled into. */
    blockName: string | null;
    /** When `panel === 'elements'`, the specific element scope. */
    elementName: ElementScope | null;
}

export type ElementScope =
    | 'link'
    | 'button'
    | 'heading'
    | 'h1'
    | 'h2'
    | 'h3'
    | 'h4'
    | 'h5'
    | 'h6'
    | 'caption';

export const DEFAULT_NAVIGATOR_STATE: Readonly<StylesNavigatorState> =
    Object.freeze({
        panel: 'typography',
        blockName: null,
        elementName: null,
    });

export function getPanelLabel(panel: StylesNavigatorPanelId): string {
    switch (panel) {
        case 'typography':
            return __('Typography', TEXT_DOMAIN);
        case 'colors':
            return __('Colors', TEXT_DOMAIN);
        case 'layout':
            return __('Layout', TEXT_DOMAIN);
        case 'blocks':
            return __('Blocks', TEXT_DOMAIN);
        case 'elements':
            return __('Elements', TEXT_DOMAIN);
        case 'variations':
            return __('Variations', TEXT_DOMAIN);
    }
}

export function getElementLabel(element: ElementScope): string {
    switch (element) {
        case 'link':
            return __('Link', TEXT_DOMAIN);
        case 'button':
            return __('Button', TEXT_DOMAIN);
        case 'heading':
            return __('Headings', TEXT_DOMAIN);
        case 'h1':
            return __('Heading 1', TEXT_DOMAIN);
        case 'h2':
            return __('Heading 2', TEXT_DOMAIN);
        case 'h3':
            return __('Heading 3', TEXT_DOMAIN);
        case 'h4':
            return __('Heading 4', TEXT_DOMAIN);
        case 'h5':
            return __('Heading 5', TEXT_DOMAIN);
        case 'h6':
            return __('Heading 6', TEXT_DOMAIN);
        case 'caption':
            return __('Caption', TEXT_DOMAIN);
    }
}

export const ELEMENT_ORDER: readonly ElementScope[] = Object.freeze([
    'link',
    'button',
    'heading',
    'h1',
    'h2',
    'h3',
    'h4',
    'h5',
    'h6',
    'caption',
]);

export const PANEL_ORDER: readonly StylesNavigatorPanelId[] = Object.freeze([
    'typography',
    'colors',
    'layout',
    'blocks',
    'elements',
    'variations',
]);
