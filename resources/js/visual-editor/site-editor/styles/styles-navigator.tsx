/**
 * Styles-section navigator panel.
 *
 * Renders the left-rail tree of style scopes: Typography / Colors /
 * Layout / Blocks / Elements / Variations. Picking a node updates the
 * inspector breadcrumb + panel scope through the shared
 * `StylesNavigatorState`; the canvas continues to show the same Style
 * Book so the user sees changes live.
 *
 * Kept intentionally lightweight (no Gutenberg-components dep) so the
 * styles test suite can mount it inside jsdom without pulling in the
 * full block-editor runtime.
 */

import { __ } from '@wordpress/i18n';
import { useMemo } from 'react';

import { TEXT_DOMAIN } from '../../vendor/i18n';
import {
    ELEMENT_ORDER,
    PANEL_ORDER,
    getElementLabel,
    getPanelLabel,
    type ElementScope,
    type StylesNavigatorPanelId,
    type StylesNavigatorState,
} from './styles-navigator-tree';

import './styles-navigator.css';

export interface StyleBlock {
    name: string;
    title?: string;
}

export interface StylesNavigatorProps {
    state: StylesNavigatorState;
    onSelect: (next: StylesNavigatorState) => void;
    /** Registered blocks from `/visual-editor/api/blocks` (B1 / shim). */
    blocks: readonly StyleBlock[];
    /** Error hint when the blocks fetch failed; shows under the Blocks panel. */
    blocksError?: string | null;
    isLoadingBlocks?: boolean;
}

function topLevelActive(
    state: StylesNavigatorState,
    panel: StylesNavigatorPanelId
): boolean {
    if (state.panel !== panel) {
        return false;
    }

    // The root node is "selected" when no child is drilled into.
    if (panel === 'blocks') {
        return state.blockName === null;
    }

    if (panel === 'elements') {
        return state.elementName === null;
    }

    return true;
}

export function StylesNavigator(
    props: StylesNavigatorProps
): JSX.Element {
    const { state, onSelect, blocks, blocksError, isLoadingBlocks } = props;

    const blockChildren = useMemo(() => {
        const copy = blocks.slice();

        copy.sort((a, b) => {
            const aLabel = (a.title ?? a.name).toLowerCase();
            const bLabel = (b.title ?? b.name).toLowerCase();

            return aLabel.localeCompare(bLabel);
        });

        return copy;
    }, [blocks]);

    return (
        <nav
            className="ap-site-editor__styles-navigator"
            aria-label={__('Styles scopes', TEXT_DOMAIN)}
            data-testid="ap-site-editor-styles-navigator"
        >
            <ul
                role="list"
                className="ap-site-editor__styles-navigator-list"
            >
                {PANEL_ORDER.map((panel) => {
                    const isOpen = state.panel === panel;
                    const isSelected = topLevelActive(state, panel);
                    const label = getPanelLabel(panel);

                    return (
                        <li
                            key={panel}
                            role="listitem"
                            className="ap-site-editor__styles-navigator-item"
                            data-panel={panel}
                            data-active={isSelected}
                        >
                            <button
                                type="button"
                                className="ap-site-editor__styles-navigator-link"
                                data-testid={`ap-site-editor-styles-nav-${panel}`}
                                aria-current={isSelected ? 'page' : undefined}
                                onClick={() =>
                                    onSelect({
                                        panel,
                                        blockName: null,
                                        elementName: null,
                                    })
                                }
                            >
                                {label}
                            </button>

                            {panel === 'blocks' && isOpen ? (
                                <ul
                                    role="list"
                                    className="ap-site-editor__styles-navigator-children"
                                    data-testid="ap-site-editor-styles-nav-blocks-children"
                                >
                                    {blockChildren.length === 0 &&
                                    isLoadingBlocks ? (
                                        <li className="ap-site-editor__styles-navigator-note">
                                            {__('Loading blocks…', TEXT_DOMAIN)}
                                        </li>
                                    ) : null}
                                    {blockChildren.length === 0 &&
                                    !isLoadingBlocks &&
                                    blocksError !== null &&
                                    blocksError !== undefined ? (
                                        <li
                                            role="alert"
                                            className="ap-site-editor__styles-navigator-error"
                                            data-testid="ap-site-editor-styles-nav-blocks-error"
                                        >
                                            {blocksError}
                                        </li>
                                    ) : null}
                                    {blockChildren.length === 0 &&
                                    !isLoadingBlocks &&
                                    (blocksError === null ||
                                        blocksError === undefined) ? (
                                        <li className="ap-site-editor__styles-navigator-note">
                                            {__(
                                                'No blocks registered.',
                                                TEXT_DOMAIN
                                            )}
                                        </li>
                                    ) : null}
                                    {blockChildren.map((block) => {
                                        const isBlockSelected =
                                            state.blockName === block.name;
                                        const displayLabel =
                                            block.title ?? block.name;

                                        return (
                                            <li
                                                key={block.name}
                                                role="listitem"
                                                className="ap-site-editor__styles-navigator-child"
                                            >
                                                <button
                                                    type="button"
                                                    className="ap-site-editor__styles-navigator-child-link"
                                                    data-active={isBlockSelected}
                                                    data-testid={`ap-site-editor-styles-nav-block-${block.name}`}
                                                    aria-current={
                                                        isBlockSelected
                                                            ? 'page'
                                                            : undefined
                                                    }
                                                    onClick={() =>
                                                        onSelect({
                                                            panel: 'blocks',
                                                            blockName: block.name,
                                                            elementName: null,
                                                        })
                                                    }
                                                >
                                                    {displayLabel}
                                                </button>
                                            </li>
                                        );
                                    })}
                                </ul>
                            ) : null}

                            {panel === 'elements' && isOpen ? (
                                <ul
                                    role="list"
                                    className="ap-site-editor__styles-navigator-children"
                                    data-testid="ap-site-editor-styles-nav-elements-children"
                                >
                                    {ELEMENT_ORDER.map(
                                        (element: ElementScope) => {
                                            const isElementSelected =
                                                state.elementName === element;

                                            return (
                                                <li
                                                    key={element}
                                                    role="listitem"
                                                    className="ap-site-editor__styles-navigator-child"
                                                >
                                                    <button
                                                        type="button"
                                                        className="ap-site-editor__styles-navigator-child-link"
                                                        data-active={isElementSelected}
                                                        data-testid={`ap-site-editor-styles-nav-element-${element}`}
                                                        aria-current={
                                                            isElementSelected
                                                                ? 'page'
                                                                : undefined
                                                        }
                                                        onClick={() =>
                                                            onSelect({
                                                                panel: 'elements',
                                                                blockName: null,
                                                                elementName: element,
                                                            })
                                                        }
                                                    >
                                                        {getElementLabel(element)}
                                                    </button>
                                                </li>
                                            );
                                        }
                                    )}
                                </ul>
                            ) : null}
                        </li>
                    );
                })}
            </ul>
        </nav>
    );
}
