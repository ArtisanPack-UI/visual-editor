/**
 * Composed-view locked-canvas ribbon (#623).
 *
 * A single sticky bar at the top of the composed canvas naming the
 * template the author's content is being previewed inside, plus one
 * **Edit template ↗** link into the site editor.
 *
 * ## Why one ribbon rather than per-part chrome
 *
 * The composed canvas renders the template's header and footer as real,
 * inert blocks (`ChromeBlocks`). Badging each template part with its own
 * "edit this part" affordance would put four or five competing controls
 * on a surface whose whole point is to read like the finished page. One
 * ribbon at the top says the same thing once: this is a preview, here is
 * what it is, here is where you go to change it. Per-part chrome is
 * explicitly out of scope for v1.
 *
 * ## Why it lives inside the iframe
 *
 * Rendering it in the editor chrome (beside the composed notice) would
 * pin it above the canvas frame, where it reads as another editor
 * toolbar. Mounted inside `BlockCanvas` it scrolls with the composed
 * content and sticks to the top of the *preview*, which is what makes it
 * read as a property of the page rather than of the editor. The cost is
 * that `composed-view-ribbon.css` cannot be a side-effect import here —
 * it is pulled in `?inline` by `canvas-styles.ts` and handed to
 * `BlockCanvas`, like every other in-iframe sheet.
 *
 * The CTA opens in a new tab on purpose: the post editor may hold
 * unsaved content, and navigating away from it to edit template chrome
 * would be a data-loss trap.
 *
 * @since 1.6.0
 */

import type { ReactElement } from 'react';
import { __, sprintf } from '@wordpress/i18n';

import { TEXT_DOMAIN } from '../../vendor/i18n';
import { buildTemplateDeepLink } from '../../site-editor/deep-link';

export interface ComposedViewRibbonProps {
    /** Resolved template name — the fallback template's name when composing against it. */
    templateName: string;
    /**
     * Resolved template slug, or `null` when composing against the
     * built-in default template (#624). `null` hides the CTA: the
     * fallback template is a client-side constant with no site-editor
     * record behind it, so there is nothing to link to.
     */
    templateSlug: string | null;
    /**
     * Route base the site editor is mounted at. Defaults to the
     * package's own mount path; hosts that mount it elsewhere pass
     * theirs.
     */
    siteEditorRouteBase?: string;
}

export function ComposedViewRibbon(
    props: ComposedViewRibbonProps
): ReactElement {
    const { templateName, templateSlug, siteEditorRouteBase } = props;

    return (
        <div
            className="ap-visual-editor__composed-ribbon"
            role="region"
            aria-label={__('Composed-view controls', TEXT_DOMAIN)}
            data-testid="ap-composed-view-ribbon"
        >
            <span className="ap-visual-editor__composed-ribbon-label">
                {sprintf(
                    /* translators: %s: template name. */
                    __('Editing content inside %s', TEXT_DOMAIN),
                    templateName
                )}
            </span>
            {templateSlug !== null ? (
                <a
                    className="ap-visual-editor__composed-ribbon-cta"
                    href={buildTemplateDeepLink(
                        templateSlug,
                        siteEditorRouteBase
                    )}
                    target="_blank"
                    rel="noopener noreferrer"
                    data-testid="ap-composed-view-ribbon-cta"
                >
                    {__('Edit template ↗', TEXT_DOMAIN)}
                </a>
            ) : null}
        </div>
    );
}
