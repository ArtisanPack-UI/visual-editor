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

import type { ReactNode } from 'react';
import { __ } from '@wordpress/i18n';

import { TEXT_DOMAIN } from '../vendor/i18n';

import {
    getSiteEditorSections,
    type SiteEditorSectionId,
} from './sections';

import './navigator-sidebar.css';

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

    return (
        <nav
            className="ap-site-editor__navigator"
            aria-label={__('Site editor sections', TEXT_DOMAIN)}
            data-testid="ap-site-editor-navigator"
        >
            <ul
                className="ap-site-editor__navigator-list"
                role="tablist"
                aria-orientation="vertical"
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
                                aria-selected={isActive}
                                aria-controls="ap-site-editor-section-outlet"
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
