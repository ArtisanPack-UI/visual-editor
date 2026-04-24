/**
 * Site-editor section registry.
 *
 * Single source of truth for the five sections D2–D5 will plug into. Each
 * entry binds a route slug to its label, save-scope label, and inline SVG
 * icon used by the navigator. Order in the array also defines the order
 * shown in the navigator.
 *
 * Per the macro design brief (`docs/design/site-editor-ux.md` §4.2), every
 * Save action *names its scope*. The `saveLabel` here is consumed by the
 * top bar; D2–D5 only need to wire the section-specific save handler.
 */

import { __ } from '@wordpress/i18n';
import type { ReactElement } from 'react';

import { TEXT_DOMAIN } from '../vendor/i18n';

export type SiteEditorSectionId =
    | 'templates'
    | 'template-parts'
    | 'patterns'
    | 'styles'
    | 'navigation';

export interface SiteEditorSection {
    id: SiteEditorSectionId;
    /**
     * Route slug used in the URL and the navigator anchors. Matches the
     * `id` for the five V1 sections; kept separate so a future section
     * could ship under an alias slug without breaking the registry key.
     */
    slug: SiteEditorSectionId;
    /** Human-readable section title (e.g. "Templates"). */
    label: string;
    /** Mode-indicator wording (e.g. "Editing: Templates"). */
    modeLabel: string;
    /** Save button label per design brief §4.3 (e.g. "Save template"). */
    saveLabel: string;
    /**
     * Inline SVG path. Kept inline to avoid a Blade-icons dependency in
     * the package bundle (see project memory: package blades / JS must
     * not depend on `<x-artisanpack-icon>`).
     */
    icon: ReactElement;
}

function svg(path: string): ReactElement {
    return (
        <svg
            aria-hidden="true"
            focusable="false"
            viewBox="0 0 24 24"
            width="18"
            height="18"
        >
            <path fill="currentColor" d={path} />
        </svg>
    );
}

/**
 * Returns the registry. A function (not a top-level constant) so the
 * `__()` calls run after `bootI18n()` in the site-editor entry has
 * initialized the text domain.
 */
export function getSiteEditorSections(): ReadonlyArray<SiteEditorSection> {
    return [
        {
            id: 'templates',
            slug: 'templates',
            label: __('Templates', TEXT_DOMAIN),
            modeLabel: __('Editing: Templates', TEXT_DOMAIN),
            saveLabel: __('Save template', TEXT_DOMAIN),
            icon: svg('M4 4h16v4H4V4zm0 6h7v10H4V10zm9 0h7v10h-7V10z'),
        },
        {
            id: 'template-parts',
            slug: 'template-parts',
            label: __('Template Parts', TEXT_DOMAIN),
            modeLabel: __('Editing: Template Parts', TEXT_DOMAIN),
            saveLabel: __('Save template part', TEXT_DOMAIN),
            icon: svg(
                'M3 4h18v3H3V4zm0 6.5h18v3H3v-3zM3 17h18v3H3v-3z'
            ),
        },
        {
            id: 'patterns',
            slug: 'patterns',
            label: __('Patterns', TEXT_DOMAIN),
            modeLabel: __('Editing: Patterns', TEXT_DOMAIN),
            saveLabel: __('Save pattern', TEXT_DOMAIN),
            icon: svg(
                'M3 3h8v8H3V3zm10 0h8v8h-8V3zM3 13h8v8H3v-8zm10 0h8v8h-8v-8z'
            ),
        },
        {
            id: 'styles',
            slug: 'styles',
            label: __('Styles', TEXT_DOMAIN),
            modeLabel: __('Editing: Global styles', TEXT_DOMAIN),
            saveLabel: __('Save global styles', TEXT_DOMAIN),
            icon: svg(
                'M12 3a9 9 0 0 0 0 18c.83 0 1.5-.67 1.5-1.5 0-.39-.15-.74-.39-1.01-.23-.27-.36-.61-.36-.99 0-.83.67-1.5 1.5-1.5H16a5 5 0 0 0 5-5c0-4.42-4.03-8-9-8zm-5.5 9a1.5 1.5 0 1 1 0-3 1.5 1.5 0 0 1 0 3zm3-4a1.5 1.5 0 1 1 0-3 1.5 1.5 0 0 1 0 3zm5 0a1.5 1.5 0 1 1 0-3 1.5 1.5 0 0 1 0 3zm3 4a1.5 1.5 0 1 1 0-3 1.5 1.5 0 0 1 0 3z'
            ),
        },
        {
            id: 'navigation',
            slug: 'navigation',
            label: __('Navigation', TEXT_DOMAIN),
            modeLabel: __('Editing: Navigation', TEXT_DOMAIN),
            saveLabel: __('Save menu', TEXT_DOMAIN),
            icon: svg(
                'M3 6h18v2H3V6zm0 5h18v2H3v-2zm0 5h18v2H3v-2z'
            ),
        },
    ];
}

/**
 * Lookup helper that returns `null` for unknown slugs so callers can
 * fall back to the default section instead of throwing.
 */
export function findSectionBySlug(
    slug: string | null | undefined
): SiteEditorSection | null {
    if (slug === null || slug === undefined || slug === '') {
        return null;
    }

    const sections = getSiteEditorSections();

    return sections.find((section) => section.slug === slug) ?? null;
}

/**
 * Resolve a known {@link SiteEditorSectionId} to its registry entry.
 * The input is type-narrowed to one of the five known ids so the lookup
 * cannot fail; throwing on a miss makes a future registry change loud
 * instead of silently falling back to a wrong section.
 */
export function getSection(id: SiteEditorSectionId): SiteEditorSection {
    const match = getSiteEditorSections().find((section) => section.id === id);

    if (match === undefined) {
        throw new Error(`Unknown site-editor section id: ${id}`);
    }

    return match;
}

/** Default landing section when the URL is just `/visual-editor/site`. */
export const DEFAULT_SECTION_ID: SiteEditorSectionId = 'templates';
