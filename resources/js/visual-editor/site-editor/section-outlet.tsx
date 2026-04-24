/**
 * Section-tab outlet placeholder.
 *
 * D2 (templates + parts), D3 (styles), D4 (navigation), and D5
 * (patterns) each replace this placeholder for their section. Until
 * those phases land, the canvas frame renders the empty state and the
 * navigator-outlet slot below the section list shows this short
 * "lands in {phase}" notice so the shell is informative rather than
 * blank.
 */

import { __, sprintf } from '@wordpress/i18n';

import { TEXT_DOMAIN } from '../vendor/i18n';

import { type SiteEditorSectionId } from './sections';

const SECTION_PHASE_NOTE: Record<SiteEditorSectionId, string> = {
    templates: 'D2',
    'template-parts': 'D2',
    patterns: 'D5',
    styles: 'D3',
    navigation: 'D4',
};

export interface SectionOutletProps {
    section: SiteEditorSectionId;
    sectionLabel: string;
}

export function SectionOutlet(props: SectionOutletProps): JSX.Element {
    const { section, sectionLabel } = props;
    const phase = SECTION_PHASE_NOTE[section];

    return (
        <p
            className="ap-site-editor__section-outlet"
            data-testid={`ap-site-editor-section-outlet-${section}`}
        >
            {sprintf(
                /* translators: 1: section label, 2: phase identifier (e.g. "D2"). */
                __('%1$s UI lands in %2$s.', TEXT_DOMAIN),
                sectionLabel,
                phase
            )}
        </p>
    );
}
