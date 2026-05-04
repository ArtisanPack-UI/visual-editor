/**
 * Global-styles inspector sidebar (H6 minimal — #431).
 *
 * Reads through the `globalStyles` root entity. Surfaces the active
 * theme + variation selection + a count of declared variations so the
 * editor can confirm the H6 controller's data is reaching the shim.
 * The variation picker UI lands in H7's rescope.
 */

import { useEntityRecord } from '../../vendor/core-data-shim';
import { __, sprintf } from '@wordpress/i18n';

import { TEXT_DOMAIN } from '../../vendor/i18n';

import { SidebarFrame } from './sidebar-frame';

import type { GlobalStylesSidebarRecord } from './types';

export interface GlobalStylesSidebarProps {
    /** Sentinel `__base__` when no DB override exists; numeric id otherwise. */
    id: number | string;
}

export function GlobalStylesSidebar({
    id,
}: GlobalStylesSidebarProps): JSX.Element {
    const { record, hasResolved, isResolving } = useEntityRecord<GlobalStylesSidebarRecord>(
        'root',
        'globalStyles',
        id
    );

    return (
        <SidebarFrame
            label={__('Global Styles', TEXT_DOMAIN)}
            record={record}
            hasResolved={hasResolved}
            isResolving={isResolving}
            testId="ap-global-styles-sidebar"
        >
            {(globalStyles) => (
                <>
                    <Field label={__('Theme', TEXT_DOMAIN)} value={globalStyles.theme} />
                    <Field
                        label={__('Variations', TEXT_DOMAIN)}
                        value={sprintf(
                            /* translators: %d: number of style variations declared by the active theme. */
                            __('%d declared', TEXT_DOMAIN),
                            globalStyles.variations.length
                        )}
                    />
                    <Field
                        label={__('Status', TEXT_DOMAIN)}
                        value={
                            id === '__base__'
                                ? __(
                                      'Theme defaults are authoritative — no DB override yet.',
                                      TEXT_DOMAIN
                                  )
                                : __('User-customized.', TEXT_DOMAIN)
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
