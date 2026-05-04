/**
 * Shared frame for the H6 minimal inspector sidebars (#431).
 *
 * Renders the loading / not-found / loaded states common to every
 * entity sidebar. Concrete sidebars pass a render prop that produces
 * the document-settings panel from the resolved record. Polish (real
 * editable inputs, design system styling, document-tabs integration)
 * lands in H7's UI rescope.
 */

import { __ } from '@wordpress/i18n';

import { TEXT_DOMAIN } from '../../vendor/i18n';

import './sidebar-frame.css';

export interface SidebarFrameProps<TRecord> {
    label: string;
    record: TRecord | null;
    hasResolved: boolean;
    isResolving: boolean;
    testId: string;
    children: (record: TRecord) => JSX.Element;
}

export function SidebarFrame<TRecord>(
    props: SidebarFrameProps<TRecord>
): JSX.Element {
    const { label, record, hasResolved, isResolving, testId, children } = props;

    if (isResolving || !hasResolved) {
        return (
            <section
                className="ap-site-editor-sidebar"
                data-testid={`${testId}-loading`}
            >
                <h2 className="ap-site-editor-sidebar__heading">{label}</h2>
                <p className="ap-site-editor-sidebar__placeholder">
                    {__('Loading…', TEXT_DOMAIN)}
                </p>
            </section>
        );
    }

    if (record === null) {
        return (
            <section
                className="ap-site-editor-sidebar"
                data-testid={`${testId}-empty`}
            >
                <h2 className="ap-site-editor-sidebar__heading">{label}</h2>
                <p className="ap-site-editor-sidebar__placeholder">
                    {__(
                        'No record found. The id may not exist or cms-framework may not be installed.',
                        TEXT_DOMAIN
                    )}
                </p>
            </section>
        );
    }

    return (
        <section className="ap-site-editor-sidebar" data-testid={testId}>
            <h2 className="ap-site-editor-sidebar__heading">{label}</h2>
            {children(record)}
        </section>
    );
}
