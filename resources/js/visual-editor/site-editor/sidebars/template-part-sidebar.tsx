/**
 * Template-part inspector sidebar (H6 minimal — #431).
 *
 * Adds the closed-list `area` field to {@see TemplateSidebar}'s
 * surface; otherwise mirrors its read-only document panel.
 */

import { useEntityRecord } from '../../vendor/core-data-shim';
import { __ } from '@wordpress/i18n';

import { TEXT_DOMAIN } from '../../vendor/i18n';

import { SidebarFrame } from './sidebar-frame';

import type { TemplatePartSidebarRecord } from './types';

export interface TemplatePartSidebarProps {
    id: number | string;
}

export function TemplatePartSidebar({
    id,
}: TemplatePartSidebarProps): JSX.Element {
    const { record, hasResolved, isResolving } = useEntityRecord<TemplatePartSidebarRecord>(
        'postType',
        'wp_template_part',
        id
    );

    return (
        <SidebarFrame
            label={__('Template Part', TEXT_DOMAIN)}
            record={record}
            hasResolved={hasResolved}
            isResolving={isResolving}
            testId="ap-template-part-sidebar"
        >
            {(part) => (
                <>
                    <Field label={__('Title', TEXT_DOMAIN)} value={part.title.rendered} />
                    <Field label={__('Slug', TEXT_DOMAIN)} value={part.slug} />
                    <Field label={__('Area', TEXT_DOMAIN)} value={part.area} />
                    <Field label={__('Theme', TEXT_DOMAIN)} value={part.theme} />
                    <Field label={__('Source', TEXT_DOMAIN)} value={part.source} />
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
