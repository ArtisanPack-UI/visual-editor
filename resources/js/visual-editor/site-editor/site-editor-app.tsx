/**
 * Site-editor SPA root.
 *
 * D1 (#368) — assembles the four shell regions defined in the macro
 * design brief (`docs/design/site-editor-ux.md` §3.2):
 *
 *   ┌────────── Top bar ──────────┐
 *   │ Navigator │  Canvas │ Inspector │
 *   └─────────────────────────────────┘
 *
 * The top bar reuses the post-editor's {@link TopBar} so the chrome
 * stays visually consistent (per issue: "Reuse the post-editor's
 * top-bar implementation from M7 rather than forking it"). Site-editor-
 * specific extras — back-to-post-editor, mode indicator, save-with-
 * scope — ride in through the existing `extraActions` slot.
 *
 * D2–D5 plug into:
 *   - The navigator's `children` slot for per-section entity browsers.
 *   - The canvas-frame's `hasEntity` / `children` for live editing.
 *   - The inspector outlet for per-entity settings.
 *
 * Until those phases land, the shell is intentionally informative
 * (placeholders name the section + phase) rather than empty.
 */

import { useCallback, useEffect, useMemo } from 'react';
import { __, sprintf } from '@wordpress/i18n';

import { TEXT_DOMAIN, bootI18n } from '../vendor/i18n';

import { CanvasFrame } from './canvas-frame';
import { InspectorOutlet } from './inspector-outlet';
import { NavigatorSidebar } from './navigator-sidebar';
import { SectionOutlet } from './section-outlet';
import { getSection } from './sections';
import { usePersistedToggle } from './use-persisted-toggle';
import { useSiteEditorRouting } from './use-site-editor-routing';
import { TopBar } from '../editor/top-bar';

import './site-editor-app.css';

const NAVIGATOR_STORAGE_KEY = 'ap-site-editor:navigator-open';
const INSPECTOR_STORAGE_KEY = 'ap-site-editor:inspector-open';

export interface SiteEditorAppProps {
    /** Pathname prefix the SPA owns (e.g. `/visual-editor/site`). */
    routeBase: string;
    /** URL to send the user back to when they leave the site editor. */
    postEditorUrl: string;
}

let i18nBooted = false;

function ensureI18n(): void {
    if (i18nBooted) {
        return;
    }

    bootI18n();
    i18nBooted = true;
}

export function SiteEditorApp(props: SiteEditorAppProps): JSX.Element {
    ensureI18n();

    const { routeBase, postEditorUrl } = props;

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

    // Update the document title so deep-links and tab pickers identify
    // the active scope without forcing the user to read the URL.
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

    // The Cmd+S handler is intentionally a no-op for D1 so the
    // shortcut doesn't bubble up as a browser "save page" dialog. D2–D5
    // replace this with the real, scope-aware save dispatcher.
    const handleSavePlaceholder = useCallback((): void => undefined, []);

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
                {activeSection.modeLabel}
            </span>
            <button
                type="button"
                className="ap-site-editor__top-bar-save"
                disabled
                aria-label={activeSection.saveLabel}
                data-testid="ap-site-editor-save"
            >
                {activeSection.saveLabel}
            </button>
        </div>
    );

    return (
        <div
            className="ap-site-editor__shell"
            data-navigator-open={navigatorOpen}
            data-inspector-open={inspectorOpen}
            data-active-section={activeSection.id}
            data-testid="ap-site-editor-shell"
        >
            <TopBar
                saveStatus="idle"
                lastSavedAt={null}
                saveErrorMessage={null}
                canUndo={false}
                canRedo={false}
                onUndo={() => undefined}
                onRedo={() => undefined}
                isInserterOpen={navigatorOpen}
                isInspectorOpen={inspectorOpen}
                onToggleInserter={handleToggleNavigator}
                onToggleInspector={handleToggleInspector}
                previewUrl={null}
                onSave={handleSavePlaceholder}
                inserterToggleAriaLabel={inserterToggleAriaLabel}
                inspectorToggleAriaLabel={inspectorToggleAriaLabel}
                extraActions={extraActions}
            />
            <div
                className="ap-site-editor__body"
                id="ap-site-editor-section-outlet"
            >
                {navigatorOpen ? (
                    <div className="ap-site-editor__sidebar ap-site-editor__sidebar--navigator">
                        <NavigatorSidebar
                            activeSection={activeSection.id}
                            onSelectSection={handleSelectSection}
                        >
                            <SectionOutlet
                                section={activeSection.id}
                                sectionLabel={activeSection.label}
                            />
                        </NavigatorSidebar>
                    </div>
                ) : null}
                <CanvasFrame
                    sectionLabel={activeSection.modeLabel}
                    hasEntity={false}
                />
                {inspectorOpen ? (
                    <div className="ap-site-editor__sidebar ap-site-editor__sidebar--inspector">
                        <InspectorOutlet sectionLabel={activeSection.label} />
                    </div>
                ) : null}
            </div>
        </div>
    );
}
