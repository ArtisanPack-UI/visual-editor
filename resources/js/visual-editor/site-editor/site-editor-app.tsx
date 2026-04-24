/**
 * Site-editor SPA root.
 *
 * D1 (#368) built the four shell regions (top bar / navigator / canvas /
 * inspector). D2 (#369) plugs in the Templates and Template Parts
 * sections: the navigator-outlet hosts the entity browser, the canvas
 * swaps its empty state for the block editor when an entity id appears
 * in the URL, and the inspector outlet renders the reused A1
 * `InspectorSidebar` with a kind-specific Document panel.
 *
 * The top-bar Save button is wired to whatever entity editor is mounted
 * via an `entityState` hand-off — the site editor tracks one entity at a
 * time, so the shell owns a single save slot rather than a per-section
 * context tree. D3–D5 will hook their own `entityState` into the same
 * slot when they ship.
 */

import { useCallback, useEffect, useMemo, useState } from 'react';
import { getBlockType, getBlockTypes, unregisterBlockType } from '@wordpress/blocks';
import { registerCoreBlocks } from '@wordpress/block-library';
import { __, sprintf } from '@wordpress/i18n';

import { TEXT_DOMAIN, bootI18n } from '../vendor/i18n';

import type { EntityKind, SiteEditorApiConfig } from './api-client';
import { CanvasFrame } from './canvas-frame';
import {
    useEntityEditorViews,
    type EntityEditorState,
} from './entity-editor';
import { InspectorOutlet } from './inspector-outlet';
import {
    NAVIGATOR_PANEL_ID,
    NavigatorSidebar,
    navigatorTabId,
} from './navigator-sidebar';
import { SectionOutlet } from './section-outlet';
import { getSection, type SiteEditorSectionId } from './sections';
import {
    TemplateCreateDialog,
    TemplatesBrowser,
} from './templates-section';
import {
    TemplatePartCreateDialog,
    TemplatePartsBrowser,
} from './template-parts-section';
import { usePersistedToggle } from './use-persisted-toggle';
import { useSiteEditorRouting } from './use-site-editor-routing';
import { TopBar } from '../editor/top-bar';

import './site-editor-app.css';

const NAVIGATOR_STORAGE_KEY = 'ap-site-editor:navigator-open';
const INSPECTOR_STORAGE_KEY = 'ap-site-editor:inspector-open';

const IDLE_ENTITY_STATE: EntityEditorState = {
    entityId: null,
    entityTitle: '',
    isDirty: false,
    saveStatus: 'idle',
    saveErrorMessage: null,
    lastSavedAt: null,
    save: null,
};

/**
 * Section ids whose content D2 ships. Others still render the D1
 * placeholder.
 */
const D2_SECTIONS: ReadonlySet<SiteEditorSectionId> = new Set([
    'templates',
    'template-parts',
]);

export interface SiteEditorAppProps {
    /** Pathname prefix the SPA owns (e.g. `/visual-editor/site`). */
    routeBase: string;
    /** URL to send the user back to when they leave the site editor. */
    postEditorUrl: string;
    /** Base URL for the site-editor REST surface (e.g. `/visual-editor/api`). */
    apiBase: string;
    /** Theme slug used when creating new templates / parts. */
    theme?: string;
}

let editorBooted = false;

/**
 * Core blocks the site-editor deliberately leaves unregistered for D2.
 * Per issue #369's out-of-scope list, these entity-scoped blocks wait
 * until E4 — their `Edit` components depend on core-data selectors
 * (`getCurrentTheme`, `getEditedPostType`, etc.) that the B1 shim does
 * not yet implement, so rendering them under D2 trips the block-crash
 * boundary and leaves users staring at red error cards inside seeded
 * templates.
 *
 * Mirrors the PHP `disabled_blocks` list in
 * `config/visual-editor.php` — the two lists want to agree, and E4 will
 * be the single commit that removes blocks from both sides.
 */
const D2_DISABLED_BLOCKS: ReadonlyArray<string> = [
    'core/template-part',
    'core/post-content',
    'core/post-title',
    'core/post-excerpt',
    'core/post-date',
    'core/post-author',
    'core/post-featured-image',
    'core/site-logo',
    'core/site-title',
    'core/site-tagline',
    'core/navigation',
    'core/query',
    'core/query-loop',
    'core/latest-comments',
    'core/archives',
    'core/categories',
    'core/tag-cloud',
];

function ensureEditorBoot(): void {
    if (editorBooted) {
        return;
    }

    bootI18n();

    // Register core blocks before the first template loads so `parse()`
    // can turn the saved raw serialization back into BlockInstances
    // Gutenberg can render. Probe for `core/paragraph` specifically —
    // a length > 0 registry isn't enough, since a host app may have
    // registered an unrelated block before this boot runs and we'd
    // silently skip the core set. Paragraph is the canonical "is core
    // loaded?" sentinel.
    if (getBlockType('core/paragraph') === undefined) {
        registerCoreBlocks();
    }

    // Strip out the out-of-scope blocks. `unregisterBlockType` throws a
    // console warning when the target was never registered (e.g. a
    // narrower `registerCoreBlocks` build) — check the registry first so
    // host apps that ship a trimmed core don't see spurious noise.
    const registered = new Set(
        getBlockTypes().map((type: { name: string }) => type.name)
    );

    for (const name of D2_DISABLED_BLOCKS) {
        if (registered.has(name)) {
            unregisterBlockType(name);
        }
    }

    editorBooted = true;
}

function sectionKind(section: SiteEditorSectionId): EntityKind | null {
    if (section === 'templates') {
        return 'template';
    }

    if (section === 'template-parts') {
        return 'template-part';
    }

    return null;
}

export function SiteEditorApp(props: SiteEditorAppProps): JSX.Element {
    ensureEditorBoot();

    const { routeBase, postEditorUrl, apiBase, theme = 'default' } = props;

    const apiConfig = useMemo<SiteEditorApiConfig>(
        () => ({ apiBase }),
        [apiBase]
    );

    const routing = useSiteEditorRouting({ routeBase });
    const [navigatorOpen, setNavigatorOpen] = usePersistedToggle(
        NAVIGATOR_STORAGE_KEY,
        true
    );
    const [inspectorOpen, setInspectorOpen] = usePersistedToggle(
        INSPECTOR_STORAGE_KEY,
        true
    );
    const activeSection = useMemo(
        () => getSection(routing.section),
        [routing.section]
    );

    const activeEntityId = routing.entityId;
    const isD2Section = D2_SECTIONS.has(activeSection.id);
    const activeKind = sectionKind(activeSection.id);

    // Effective kind passed to the editor hook — always a real value so
    // the hook is called unconditionally. Non-D2 sections get
    // `entityId: null`, which short-circuits the hook into idle state.
    const editorKind: EntityKind = activeKind ?? 'template';
    const editorEntityId = isD2Section ? activeEntityId : null;

    const [entityState, setEntityState] =
        useState<EntityEditorState>(IDLE_ENTITY_STATE);

    const handleEntityStateChange = useCallback(
        (state: EntityEditorState): void => {
            setEntityState(state);
        },
        []
    );

    const editorViews = useEntityEditorViews({
        apiConfig,
        kind: editorKind,
        entityId: editorEntityId,
        onStateChange: handleEntityStateChange,
    });

    const [dialogKind, setDialogKind] = useState<EntityKind | null>(null);

    // Browser-list refresh signal. Bumped after a successful create so the
    // navigator list re-fetches and the new row appears without a full
    // page reload. Each section tracks its own counter so touching one
    // section never invalidates the other.
    const [templatesRefreshKey, setTemplatesRefreshKey] = useState(0);
    const [partsRefreshKey, setPartsRefreshKey] = useState(0);

    useEffect(() => {
        if (typeof document === 'undefined') {
            return;
        }

        document.title = sprintf(
            /* translators: %s: active site-editor section label. */
            __('Site Editor · %s', TEXT_DOMAIN),
            activeSection.label
        );
    }, [activeSection.label]);

    // Reset cached entity state when leaving a D2 section so a
    // subsequent re-entry doesn't surface stale save-status chrome.
    // Switches between D2 sections (templates ↔ template-parts) are
    // handled by the entity-editor hook itself: when its `entityId`
    // resets to `null`, it dispatches an idle `onStateChange` that
    // clears our cached state through the normal path.
    useEffect(() => {
        if (!isD2Section) {
            setEntityState(IDLE_ENTITY_STATE);
        }
    }, [isD2Section]);

    const handleSelectSection = useCallback(
        (sectionId: typeof activeSection.id): void => {
            routing.navigate(sectionId, null);
        },
        [routing]
    );

    const handleToggleNavigator = useCallback((): void => {
        setNavigatorOpen((open) => !open);
    }, [setNavigatorOpen]);

    const handleToggleInspector = useCallback((): void => {
        setInspectorOpen((open) => !open);
    }, [setInspectorOpen]);

    const handleOpenEntity = useCallback(
        (entityId: string): void => {
            routing.navigate(activeSection.id, entityId);
        },
        [activeSection.id, routing]
    );

    const handleRequestCreate = useCallback((): void => {
        if (activeKind === null) {
            return;
        }

        setDialogKind(activeKind);
    }, [activeKind]);

    const handleCloseDialog = useCallback((): void => {
        setDialogKind(null);
    }, []);

    const handleDialogCreated = useCallback(
        (entityId: number | string): void => {
            setDialogKind(null);

            // Bump the section's refresh counter so the navigator list
            // re-fetches and the new record appears alongside its siblings.
            // Only the active section's counter moves — the sibling list
            // stays cached until the user explicitly visits it.
            if (activeSection.id === 'templates') {
                setTemplatesRefreshKey((v) => v + 1);
            } else if (activeSection.id === 'template-parts') {
                setPartsRefreshKey((v) => v + 1);
            }

            routing.navigate(activeSection.id, String(entityId));
        },
        [activeSection.id, routing]
    );

    const handleSave = useCallback((): void => {
        if (entityState.save === null) {
            return;
        }

        void entityState.save();
    }, [entityState.save]);

    const inserterToggleAriaLabel = useMemo(
        () => ({
            open: __('Open navigator', TEXT_DOMAIN),
            close: __('Close navigator', TEXT_DOMAIN),
        }),
        []
    );

    const inspectorToggleAriaLabel = useMemo(
        () => ({
            open: __('Open inspector', TEXT_DOMAIN),
            close: __('Close inspector', TEXT_DOMAIN),
        }),
        []
    );

    const saveLabel = useMemo(() => {
        if (entityState.entityTitle !== '' && entityState.entityId !== null) {
            return sprintf(
                /* translators: 1: entity title, 2: section save label (e.g. "Save template"). */
                __('%1$s — %2$s', TEXT_DOMAIN),
                entityState.entityTitle,
                activeSection.saveLabel
            );
        }

        return activeSection.saveLabel;
    }, [activeSection.saveLabel, entityState.entityId, entityState.entityTitle]);

    const extraActions = (
        <div
            className="ap-site-editor__top-bar-extras"
            data-testid="ap-site-editor-top-bar-extras"
        >
            <a
                className="ap-site-editor__top-bar-back"
                href={postEditorUrl}
                data-testid="ap-site-editor-back-to-post-editor"
            >
                {__('← Post editor', TEXT_DOMAIN)}
            </a>
            <span
                className="ap-site-editor__mode-indicator"
                role="status"
                aria-live="polite"
                data-testid="ap-site-editor-mode-indicator"
                data-section={activeSection.id}
            >
                {entityState.entityId !== null
                    ? sprintf(
                          /* translators: %s: entity title being edited. */
                          __('Editing: %s', TEXT_DOMAIN),
                          entityState.entityTitle || activeSection.label
                      )
                    : activeSection.modeLabel}
            </span>
            {entityState.isDirty ? (
                <span
                    className="ap-site-editor__dirty-indicator"
                    role="status"
                    data-testid="ap-site-editor-top-bar-dirty"
                >
                    {__('Unsaved', TEXT_DOMAIN)}
                </span>
            ) : null}
            <button
                type="button"
                className="ap-site-editor__top-bar-save"
                disabled={entityState.save === null || entityState.saveStatus === 'saving'}
                aria-label={saveLabel}
                data-testid="ap-site-editor-save"
                onClick={handleSave}
            >
                {entityState.saveStatus === 'saving'
                    ? __('Saving…', TEXT_DOMAIN)
                    : activeSection.saveLabel}
            </button>
        </div>
    );

    let navigatorChildren: JSX.Element;

    if (activeSection.id === 'templates') {
        navigatorChildren = (
            <TemplatesBrowser
                apiConfig={apiConfig}
                activeEntityId={activeEntityId}
                onOpen={handleOpenEntity}
                onRequestCreate={handleRequestCreate}
                refreshKey={templatesRefreshKey}
            />
        );
    } else if (activeSection.id === 'template-parts') {
        navigatorChildren = (
            <TemplatePartsBrowser
                apiConfig={apiConfig}
                activeEntityId={activeEntityId}
                onOpen={handleOpenEntity}
                onRequestCreate={handleRequestCreate}
                refreshKey={partsRefreshKey}
            />
        );
    } else {
        navigatorChildren = (
            <SectionOutlet
                section={activeSection.id}
                sectionLabel={activeSection.label}
            />
        );
    }

    const showEntityEditor = isD2Section && activeEntityId !== null;

    return (
        <div
            className="ap-site-editor__shell"
            data-navigator-open={navigatorOpen}
            data-inspector-open={inspectorOpen}
            data-active-section={activeSection.id}
            data-has-entity={showEntityEditor}
            data-testid="ap-site-editor-shell"
        >
            <TopBar
                saveStatus={entityState.saveStatus}
                lastSavedAt={
                    entityState.lastSavedAt !== null
                        ? entityState.lastSavedAt.toISOString()
                        : null
                }
                saveErrorMessage={entityState.saveErrorMessage}
                canUndo={false}
                canRedo={false}
                onUndo={() => undefined}
                onRedo={() => undefined}
                isInserterOpen={navigatorOpen}
                isInspectorOpen={inspectorOpen}
                onToggleInserter={handleToggleNavigator}
                onToggleInspector={handleToggleInspector}
                previewUrl={null}
                onSave={handleSave}
                inserterToggleAriaLabel={inserterToggleAriaLabel}
                inspectorToggleAriaLabel={inspectorToggleAriaLabel}
                extraActions={extraActions}
            />
            <div
                className="ap-site-editor__body"
                id={NAVIGATOR_PANEL_ID}
                role="tabpanel"
                aria-labelledby={navigatorTabId(activeSection.id)}
                tabIndex={0}
            >
                {navigatorOpen ? (
                    <div className="ap-site-editor__sidebar ap-site-editor__sidebar--navigator">
                        <NavigatorSidebar
                            activeSection={activeSection.id}
                            onSelectSection={handleSelectSection}
                        >
                            {navigatorChildren}
                        </NavigatorSidebar>
                    </div>
                ) : null}
                {showEntityEditor ? (
                    <div
                        className="ap-site-editor__canvas"
                        data-has-entity="true"
                        data-testid="ap-site-editor-canvas"
                    >
                        {editorViews.canvas}
                    </div>
                ) : (
                    <CanvasFrame
                        sectionLabel={activeSection.modeLabel}
                        hasEntity={false}
                    />
                )}
                {inspectorOpen ? (
                    <div className="ap-site-editor__sidebar ap-site-editor__sidebar--inspector">
                        {showEntityEditor ? (
                            editorViews.inspector
                        ) : (
                            <InspectorOutlet sectionLabel={activeSection.label} />
                        )}
                    </div>
                ) : null}
            </div>
            {dialogKind === 'template' ? (
                <TemplateCreateDialog
                    apiConfig={apiConfig}
                    defaultTheme={theme}
                    onClose={handleCloseDialog}
                    onCreated={(entity) => handleDialogCreated(entity.id)}
                />
            ) : null}
            {dialogKind === 'template-part' ? (
                <TemplatePartCreateDialog
                    apiConfig={apiConfig}
                    defaultTheme={theme}
                    onClose={handleCloseDialog}
                    onCreated={(entity) => handleDialogCreated(entity.id)}
                />
            ) : null}
        </div>
    );
}
