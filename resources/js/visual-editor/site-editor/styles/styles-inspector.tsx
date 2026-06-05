/**
 * Styles-section inspector.
 *
 * Renders the right-rail breadcrumb ("Styles ▸ Blocks ▸ Button"), the
 * active panel for the current navigator scope, and the dirty /
 * save-status chrome the brief's P1 calls out (Save always names its
 * scope, plus an error surface when the PUT comes back 422).
 *
 * Kept in its own file so the shell can drop the inspector independent
 * of the navigator + canvas panes and the panel switch stays testable
 * on its own.
 */

import { __ } from '@wordpress/i18n';
import type { ReactElement } from 'react';

import { TEXT_DOMAIN } from '../../vendor/i18n';
import type { ValidationErrors } from '../api-client';
import type { SaveStatus } from '../use-entity-editor';
import {
    BlockDetailPanel,
    BlocksIndexPanel,
} from './panels/blocks-panel';
import { ColorsPanel } from './panels/colors-panel';
import {
    ElementDetailPanel,
    ElementsIndexPanel,
} from './panels/elements-panel';
import { LayoutPanel } from './panels/layout-panel';
import { TypographyPanel } from './panels/typography-panel';
import { VariationsPanel } from './panels/variations-panel';
import type { StyleBlock } from './styles-navigator';
import {
    getElementLabel,
    getPanelLabel,
    type StylesNavigatorState,
} from './styles-navigator-tree';
import type { StyleVariation } from './style-book-canvas';
import type { UseGlobalStylesEditorResult } from './use-global-styles-editor';

import './styles-inspector.css';

export interface StylesInspectorProps {
    editor: UseGlobalStylesEditorResult;
    state: StylesNavigatorState;
    onNavigatorChange: (next: StylesNavigatorState) => void;

    /** Validation errors from the most recent save attempt. */
    validationErrors: ValidationErrors | null;

    blocks: readonly StyleBlock[];
    variations: readonly StyleVariation[];
    activeVariationSlug: string | null;
    onApplyVariation: (slug: string) => void;

    saveStatus: SaveStatus;
    saveErrorMessage: string | null;
}

function buildBreadcrumbSegments(
    state: StylesNavigatorState,
    blocks: readonly StyleBlock[]
): readonly string[] {
    const root = __('Styles', TEXT_DOMAIN);
    const panelLabel = getPanelLabel(state.panel);

    if (state.panel === 'blocks' && state.blockName !== null) {
        const block = blocks.find(
            (entry) => entry.name === state.blockName
        );
        const label = block?.title ?? state.blockName;

        return [root, panelLabel, label];
    }

    if (state.panel === 'elements' && state.elementName !== null) {
        return [root, panelLabel, getElementLabel(state.elementName)];
    }

    return [root, panelLabel];
}

function renderActivePanel(
    editor: UseGlobalStylesEditorResult,
    state: StylesNavigatorState,
    validationErrors: ValidationErrors | null,
    blocks: readonly StyleBlock[],
    variations: readonly StyleVariation[],
    activeVariationSlug: string | null,
    onNavigatorChange: (next: StylesNavigatorState) => void,
    onApplyVariation: (slug: string) => void
): ReactElement {
    if (state.panel === 'typography') {
        return (
            <TypographyPanel
                editor={editor}
                validationErrors={validationErrors}
            />
        );
    }

    if (state.panel === 'colors') {
        return (
            <ColorsPanel
                editor={editor}
                validationErrors={validationErrors}
            />
        );
    }

    if (state.panel === 'layout') {
        return (
            <LayoutPanel
                editor={editor}
                validationErrors={validationErrors}
            />
        );
    }

    if (state.panel === 'blocks') {
        if (state.blockName !== null) {
            return (
                <BlockDetailPanel
                    editor={editor}
                    validationErrors={validationErrors}
                    blocks={blocks}
                    selectedBlockName={state.blockName}
                    onSelectBlock={(blockName) =>
                        onNavigatorChange({
                            panel: 'blocks',
                            blockName,
                            elementName: null,
                        })
                    }
                />
            );
        }

        return (
            <BlocksIndexPanel
                editor={editor}
                validationErrors={validationErrors}
                blocks={blocks}
                selectedBlockName={null}
                onSelectBlock={(blockName) =>
                    onNavigatorChange({
                        panel: 'blocks',
                        blockName,
                        elementName: null,
                    })
                }
            />
        );
    }

    if (state.panel === 'elements') {
        if (state.elementName !== null) {
            return (
                <ElementDetailPanel
                    editor={editor}
                    validationErrors={validationErrors}
                    element={state.elementName}
                    onBack={() =>
                        onNavigatorChange({
                            panel: 'elements',
                            blockName: null,
                            elementName: null,
                        })
                    }
                />
            );
        }

        return (
            <ElementsIndexPanel
                editor={editor}
                onSelectElement={(element) =>
                    onNavigatorChange({
                        panel: 'elements',
                        blockName: null,
                        elementName: element,
                    })
                }
            />
        );
    }

    return (
        <VariationsPanel
            editor={editor}
            variations={variations}
            activeVariationSlug={activeVariationSlug}
            onApplyVariation={onApplyVariation}
        />
    );
}

export function StylesInspector(
    props: StylesInspectorProps
): JSX.Element {
    const {
        editor,
        state,
        onNavigatorChange,
        validationErrors,
        blocks,
        variations,
        activeVariationSlug,
        onApplyVariation,
        saveStatus,
        saveErrorMessage,
    } = props;

    const crumbs = buildBreadcrumbSegments(state, blocks);

    return (
        <aside
            className="ap-site-editor__inspector ap-site-editor__styles-inspector"
            aria-label={__('Global styles inspector', TEXT_DOMAIN)}
            data-testid="ap-site-editor-styles-inspector"
        >
            <div className="ap-site-editor__inspector-header">
                <nav
                    aria-label={__('Styles scope', TEXT_DOMAIN)}
                    data-testid="ap-site-editor-styles-breadcrumb"
                    className="ap-site-editor__styles-breadcrumb"
                >
                    <ol className="ap-site-editor__styles-breadcrumb-list">
                        {crumbs.map((crumb, index) => (
                            <li
                                key={`${crumb}-${index}`}
                                className="ap-site-editor__styles-breadcrumb-item"
                                aria-current={
                                    index === crumbs.length - 1
                                        ? 'page'
                                        : undefined
                                }
                            >
                                {crumb}
                                {index < crumbs.length - 1 ? (
                                    <span
                                        aria-hidden="true"
                                        className="ap-site-editor__styles-breadcrumb-sep"
                                    >
                                        {' ▸ '}
                                    </span>
                                ) : null}
                            </li>
                        ))}
                    </ol>
                </nav>
                {editor.isDirty ? (
                    <span
                        className="ap-site-editor__dirty-indicator"
                        role="status"
                        data-testid="ap-site-editor-styles-dirty"
                    >
                        {__('Unsaved changes', TEXT_DOMAIN)}
                    </span>
                ) : null}
                {saveStatus === 'saved' && !editor.isDirty ? (
                    <span
                        className="ap-site-editor__save-indicator"
                        role="status"
                        data-testid="ap-site-editor-styles-saved"
                    >
                        {__('Saved', TEXT_DOMAIN)}
                    </span>
                ) : null}
                {saveStatus === 'error' && saveErrorMessage !== null ? (
                    <p
                        role="alert"
                        className="ap-site-editor__styles-inspector-error"
                        data-testid="ap-site-editor-styles-save-error"
                    >
                        {saveErrorMessage}
                    </p>
                ) : null}
            </div>
            <div
                className="ap-site-editor__inspector-body"
                data-testid="ap-site-editor-styles-inspector-body"
            >
                {editor.loadStatus === 'error' ? (
                    <p
                        role="alert"
                        className="ap-site-editor__styles-inspector-error"
                        data-testid="ap-site-editor-styles-load-error"
                    >
                        {editor.loadErrorMessage ??
                            __(
                                'Failed to load global styles.',
                                TEXT_DOMAIN
                            )}
                    </p>
                ) : null}
                {editor.loadStatus === 'loading' ? (
                    <p
                        className="ap-site-editor__inspector-placeholder"
                        data-testid="ap-site-editor-styles-loading"
                    >
                        {__('Loading global styles…', TEXT_DOMAIN)}
                    </p>
                ) : null}
                {editor.loadStatus === 'ready'
                    ? renderActivePanel(
                          editor,
                          state,
                          validationErrors,
                          blocks,
                          variations,
                          activeVariationSlug,
                          onNavigatorChange,
                          onApplyVariation
                      )
                    : null}
            </div>
        </aside>
    );
}
