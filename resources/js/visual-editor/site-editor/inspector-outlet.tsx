/**
 * Site-editor inspector outlet.
 *
 * The right-rail mount point D2–D5 will plug their per-section inspector
 * panels into. For D1 (#368) this only renders a placeholder identifying
 * which section is active so the shell visibly fills the inspector
 * region. The macro design brief (`docs/design/site-editor-ux.md` §3.9)
 * dictates that the inspector is always present (collapsible, but never
 * removed) so users have a single, predictable home for entity-level
 * settings — that policy is enforced here by always rendering the rail.
 */

import { __, sprintf } from '@wordpress/i18n';

import { TEXT_DOMAIN } from '../vendor/i18n';

import './inspector-outlet.css';

export interface InspectorOutletProps {
    sectionLabel: string;
}

export function InspectorOutlet(props: InspectorOutletProps): JSX.Element {
    const { sectionLabel } = props;

    return (
        <aside
            className="ap-site-editor__inspector"
            aria-label={__('Section inspector', TEXT_DOMAIN)}
            data-testid="ap-site-editor-inspector"
        >
            <div className="ap-site-editor__inspector-header">
                <h2 className="ap-site-editor__inspector-title">
                    {sectionLabel}
                </h2>
            </div>
            <div className="ap-site-editor__inspector-body">
                <p className="ap-site-editor__inspector-placeholder">
                    {sprintf(
                        /* translators: %s: site-editor section label (e.g. "Templates"). */
                        __(
                            'Inspector controls for %s land in a follow-up phase.',
                            TEXT_DOMAIN
                        ),
                        sectionLabel
                    )}
                </p>
            </div>
        </aside>
    );
}
