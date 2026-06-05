/**
 * Site-editor navigator sidebar.
 *
 * Renders the section list (Templates / Template Parts / Patterns /
 * Styles / Navigation) as the left-rail navigator. Per the macro design
 * brief (`docs/design/site-editor-ux.md` §3.2, principle P5: navigator
 * browses, canvas edits, inspector configures), this region only owns
 * navigation between sections — D2–D5 plug per-section entity browsers
 * inside each section's collapsible block underneath.
 *
 * The sidebar exposes a collapse affordance whose state is persisted
 * (see {@link useSiteEditorChromeState}). The state lift up to the parent
 * shell so the top-bar toggle and the sidebar's own chevron stay in sync.
 */

import type { KeyboardEvent as ReactKeyboardEvent, ReactNode } from 'react';
import { useCallback, useRef } from 'react';
import { __ } from '@wordpress/i18n';

import { TEXT_DOMAIN } from '../vendor/i18n';

import {
    getSiteEditorSections,
    type SiteEditorSectionId,
} from './sections';

import './navigator-sidebar.css';

/** Stable id per tab — also consumed by the tabpanel's `aria-labelledby`. */
export function navigatorTabId(section: SiteEditorSectionId): string {
    return `ap-site-editor-tab-${section}`;
}

/** Id of the tabpanel the navigator's tabs control. */
export const NAVIGATOR_PANEL_ID = 'ap-site-editor-section-outlet';

export interface NavigatorSidebarProps {
    activeSection: SiteEditorSectionId;
    onSelectSection: (section: SiteEditorSectionId) => void;
    /**
     * Optional content rendered underneath the section list. D2–D5 use
     * this slot to inject the per-section entity browser (template list,
     * pattern list, etc.); D1 leaves it empty.
     */
    children?: ReactNode;
}

export function NavigatorSidebar(props: NavigatorSidebarProps): JSX.Element {
    const { activeSection, onSelectSection, children } = props;
    const sections = getSiteEditorSections();
    const tablistRef = useRef<HTMLUListElement | null>(null);

    // Roving-tabindex keyboard navigation per the WAI-ARIA APG `tabs`
    // pattern (vertical orientation). Up / Down move focus, Home / End
    // jump to the ends; selecting a tab also fires onSelectSection so
    // arrow-key navigation immediately swaps the active section
    // (automatic activation, the recommended default for tab content
    // that is cheap to render — our shells are).
    const handleKeyDown = useCallback(
        (event: ReactKeyboardEvent<HTMLUListElement>): void => {
            if (tablistRef.current === null) {
                return;
            }

            const tabs = Array.from(
                tablistRef.current.querySelectorAll<HTMLButtonElement>(
                    '[role="tab"]'
                )
            );

            if (tabs.length === 0) {
                return;
            }

            const currentIndex = tabs.indexOf(
                document.activeElement as HTMLButtonElement
            );

            let nextIndex: number | null = null;

            if (event.key === 'ArrowDown') {
                nextIndex =
                    currentIndex === -1
                        ? 0
                        : (currentIndex + 1) % tabs.length;
            } else if (event.key === 'ArrowUp') {
                nextIndex =
                    currentIndex === -1
                        ? tabs.length - 1
                        : (currentIndex - 1 + tabs.length) % tabs.length;
            } else if (event.key === 'Home') {
                nextIndex = 0;
            } else if (event.key === 'End') {
                nextIndex = tabs.length - 1;
            }

            if (nextIndex === null) {
                return;
            }

            event.preventDefault();
            const target = tabs[nextIndex];

            if (target === undefined) {
                return;
            }

            target.focus();

            const sectionId = target.dataset.section as
                | SiteEditorSectionId
                | undefined;

            if (sectionId !== undefined) {
                onSelectSection(sectionId);
            }
        },
        [onSelectSection]
    );

    return (
        <nav
            className="ap-site-editor__navigator"
            aria-label={__('Site editor sections', TEXT_DOMAIN)}
            data-testid="ap-site-editor-navigator"
        >
            <ul
                ref={tablistRef}
                className="ap-site-editor__navigator-list"
                role="tablist"
                aria-orientation="vertical"
                onKeyDown={handleKeyDown}
            >
                {sections.map((section) => {
                    const isActive = section.id === activeSection;

                    return (
                        <li
                            key={section.id}
                            className="ap-site-editor__navigator-item"
                            role="presentation"
                        >
                            <button
                                type="button"
                                role="tab"
                                id={navigatorTabId(section.id)}
                                aria-selected={isActive}
                                aria-controls={NAVIGATOR_PANEL_ID}
                                tabIndex={isActive ? 0 : -1}
                                className="ap-site-editor__navigator-link"
                                data-active={isActive}
                                data-section={section.id}
                                data-testid={`ap-site-editor-navigator-${section.id}`}
                                onClick={() => onSelectSection(section.id)}
                            >
                                <span
                                    className="ap-site-editor__navigator-icon"
                                    aria-hidden="true"
                                >
                                    {section.icon}
                                </span>
                                <span className="ap-site-editor__navigator-label">
                                    {section.label}
                                </span>
                            </button>
                        </li>
                    );
                })}
            </ul>
            {children !== undefined ? (
                <div
                    className="ap-site-editor__navigator-outlet"
                    data-testid="ap-site-editor-navigator-outlet"
                >
                    {children}
                </div>
            ) : null}
        </nav>
    );
}
