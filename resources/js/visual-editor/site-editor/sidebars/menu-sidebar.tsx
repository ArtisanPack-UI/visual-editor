/**
 * Menu inspector sidebar (H6 minimal — #431).
 *
 * Reads through the `wp_navigation` entity registered at `/menus`.
 * Surfaces the menu's identifying fields + the auto-add-pages flag.
 * The location-assignment dropdown (which writes to cms-framework's
 * `menu_location_assignments` table) lands in H7.
 */

import { useEntityRecord } from '../../vendor/core-data-shim';
import { __ } from '@wordpress/i18n';

import { TEXT_DOMAIN } from '../../vendor/i18n';

import { SidebarFrame } from './sidebar-frame';

import type { MenuSidebarRecord } from './types';

export interface MenuSidebarProps {
    id: number | string;
}

export function MenuSidebar({ id }: MenuSidebarProps): JSX.Element {
    const { record, hasResolved, isResolving } = useEntityRecord<MenuSidebarRecord>(
        'postType',
        'wp_navigation',
        id
    );

    return (
        <SidebarFrame
            label={__('Menu', TEXT_DOMAIN)}
            record={record}
            hasResolved={hasResolved}
            isResolving={isResolving}
            testId="ap-menu-sidebar"
        >
            {(menu) => (
                <>
                    <Field label={__('Name', TEXT_DOMAIN)} value={menu.name} />
                    <Field label={__('Slug', TEXT_DOMAIN)} value={menu.slug} />
                    <Field label={__('Theme', TEXT_DOMAIN)} value={menu.theme} />
                    <Field
                        label={__('Auto-add new pages', TEXT_DOMAIN)}
                        value={
                            menu.auto_add_pages
                                ? __('Yes', TEXT_DOMAIN)
                                : __('No', TEXT_DOMAIN)
                        }
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
