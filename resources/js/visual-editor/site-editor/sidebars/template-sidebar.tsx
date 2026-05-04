/**
 * Template inspector sidebar (H6 minimal — #431).
 *
 * Reads through the `wp_template` entity registered in commit 5a's
 * `register-entities.ts`. Surfaces the document-level settings that
 * H7's UI rescope will expand into a full Gutenberg document panel:
 * title, slug, theme, source ('db' / 'theme'), and a note when the
 * record is a DB override of a theme file (the future "revert to
 * theme" action's gating signal).
 */

import { useEntityRecord } from '../../vendor/core-data-shim';
import { __ } from '@wordpress/i18n';

import { TEXT_DOMAIN } from '../../vendor/i18n';

import { SidebarFrame } from './sidebar-frame';

import type { TemplateSidebarRecord } from './types';

export interface TemplateSidebarProps {
    id: number | string;
}

export function TemplateSidebar({ id }: TemplateSidebarProps): JSX.Element {
    const { record, hasResolved, isResolving } = useEntityRecord<TemplateSidebarRecord>(
        'postType',
        'wp_template',
        id
    );

    return (
        <SidebarFrame
            label={__('Template', TEXT_DOMAIN)}
            record={record}
            hasResolved={hasResolved}
            isResolving={isResolving}
            testId="ap-template-sidebar"
        >
            {(template) => (
                <>
                    <Field label={__('Title', TEXT_DOMAIN)} value={template.title.rendered} />
                    <Field label={__('Slug', TEXT_DOMAIN)} value={template.slug} />
                    <Field label={__('Theme', TEXT_DOMAIN)} value={template.theme} />
                    <Field label={__('Source', TEXT_DOMAIN)} value={template.source} />
                    {template.has_theme_file && template.source === 'db' ? (
                        <p className="ap-site-editor-sidebar__note">
                            {__(
                                'This template overrides a theme file. A future "revert to theme" action will restore the file authority.',
                                TEXT_DOMAIN
                            )}
                        </p>
                    ) : null}
                </>
            )}
        </SidebarFrame>
    );
}

function Field({ label, value }: { label: string; value: string }): JSX.Element {
    return (
        <div className="ap-site-editor-sidebar__field">
            <span className="ap-site-editor-sidebar__field-label">{label}</span>
            <span className="ap-site-editor-sidebar__field-value">{value || '—'}</span>
        </div>
    );
}
