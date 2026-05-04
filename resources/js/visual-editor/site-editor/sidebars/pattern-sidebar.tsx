/**
 * Pattern inspector sidebar (H6 minimal — #431).
 *
 * Reads through the `wp_block` entity. Renders the document-level
 * pattern metadata: title, slug (carries the `user/` storage prefix
 * for user patterns per plan 14 §5.6), source, sync flag, and the
 * pattern's category / block-type chips. Editable fields land in
 * H7's UI rescope.
 */

import { useEntityRecord } from '../../vendor/core-data-shim';
import { __ } from '@wordpress/i18n';

import { TEXT_DOMAIN } from '../../vendor/i18n';

import { SidebarFrame } from './sidebar-frame';

import type { PatternSidebarRecord } from './types';

export interface PatternSidebarProps {
    id: number | string;
}

export function PatternSidebar({ id }: PatternSidebarProps): JSX.Element {
    const { record, hasResolved, isResolving } = useEntityRecord<PatternSidebarRecord>(
        'postType',
        'wp_block',
        id
    );

    return (
        <SidebarFrame
            label={__('Pattern', TEXT_DOMAIN)}
            record={record}
            hasResolved={hasResolved}
            isResolving={isResolving}
            testId="ap-pattern-sidebar"
        >
            {(pattern) => (
                <>
                    <Field label={__('Title', TEXT_DOMAIN)} value={pattern.title.rendered} />
                    <Field label={__('Slug', TEXT_DOMAIN)} value={pattern.slug} />
                    <Field label={__('Source', TEXT_DOMAIN)} value={pattern.source} />
                    <Field
                        label={__('Sync status', TEXT_DOMAIN)}
                        value={
                            pattern.synced
                                ? __('Synced', TEXT_DOMAIN)
                                : __('Unsynced', TEXT_DOMAIN)
                        }
                    />
                    <ChipList
                        label={__('Categories', TEXT_DOMAIN)}
                        chips={pattern.categories}
                        emptyHint={__('No categories assigned.', TEXT_DOMAIN)}
                    />
                    <ChipList
                        label={__('Block types', TEXT_DOMAIN)}
                        chips={pattern.block_types}
                        emptyHint={__('Available to all block types.', TEXT_DOMAIN)}
                    />
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

function ChipList({
    label,
    chips,
    emptyHint,
}: {
    label: string;
    chips: readonly string[];
    emptyHint: string;
}): JSX.Element {
    return (
        <div className="ap-site-editor-sidebar__field">
            <span className="ap-site-editor-sidebar__field-label">{label}</span>
            {chips.length > 0 ? (
                <ul className="ap-site-editor-sidebar__chip-list">
                    {chips.map((chip) => (
                        <li key={chip} className="ap-site-editor-sidebar__chip">
                            {chip}
                        </li>
                    ))}
                </ul>
            ) : (
                <span className="ap-site-editor-sidebar__field-value">
                    {emptyHint}
                </span>
            )}
        </div>
    );
}
