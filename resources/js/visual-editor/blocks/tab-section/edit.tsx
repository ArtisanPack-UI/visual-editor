/**
 * Tab section — editor-side component.
 *
 * Child of `artisanpack/tabs`. Each section owns its own `label` (the
 * text rendered on the tab trigger) and `tabId` (the slug). Both
 * auto-fill on first mount when blank — `label` from the position,
 * `tabId` from `label` — so authors can drop tabs in without doing any
 * id bookkeeping. The parent tabs renderer reads these attributes from
 * inner blocks to build the tab list.
 */

import { useEffect, type ReactElement } from 'react';
import { InspectorControls, useBlockProps, useInnerBlocksProps } from '@wordpress/block-editor';
import { useSelect } from '@wordpress/data';
import { PanelBody, TextControl } from '@wordpress/components';
import { __ } from '@wordpress/i18n';

import { TEXT_DOMAIN } from '../../vendor/i18n';

interface TabSectionAttributes {
    readonly label: string;
    readonly tabId: string;
}

interface TabSectionEditProps {
    readonly attributes: TabSectionAttributes;
    readonly setAttributes: (next: Partial<TabSectionAttributes>) => void;
    readonly clientId: string;
}

const TEMPLATE: [string, Record<string, unknown>][] = [['artisanpack/group', {}]];

function slugify(value: string): string {
    return value
        .toLowerCase()
        .replace(/[^a-z0-9-]+/g, '-')
        .replace(/^-+|-+$/g, '');
}

function makeAutoTabId(clientId: string): string {
    const suffix = clientId.replace(/-/g, '').slice(0, 8);
    return `tab-${suffix}`;
}

export default function TabSectionEdit({
    attributes,
    setAttributes,
    clientId,
}: TabSectionEditProps): ReactElement {
    const { label, tabId } = attributes;

    // Derive the section's position among its tabs siblings so the
    // auto-generated default label reads "Tab 1", "Tab 2", … instead
    // of every section starting life as plain "Tab".
    const position = useSelect(
        (select) => {
            const store = select('core/block-editor') as unknown as {
                getBlockRootClientId: (id: string) => string | null;
                getBlockOrder: (id: string) => string[];
            };
            const parentId = store.getBlockRootClientId(clientId);
            if (parentId === null || parentId === '') {
                return 1;
            }
            const order = store.getBlockOrder(parentId);
            const index = order.indexOf(clientId);
            return index === -1 ? 1 : index + 1;
        },
        [clientId]
    );

    // Intentional: an empty label or tabId is always refilled with
    // the positional default. Authors who clear the field will see
    // the default snap back in — they should overwrite, not blank
    // out, since a missing label / id breaks the rendered tab list.
    // The check is strict equality with `''` (not "is auto-generated")
    // because we can't distinguish a default the author typed in
    // ("Tab 2") from one we filled in for them; both should be
    // treated as user-owned once persisted.
    useEffect(() => {
        const patch: Partial<TabSectionAttributes> = {};
        if (label === '') {
            patch.label = `${__('Tab', TEXT_DOMAIN)} ${position}`;
        }
        if (tabId === '') {
            patch.tabId = makeAutoTabId(clientId);
        }
        if (Object.keys(patch).length > 0) {
            setAttributes(patch);
        }
    }, [label, tabId, position, clientId, setAttributes]);

    const blockProps = useBlockProps({ className: 'ap-tab-section' });
    const innerBlocksProps = useInnerBlocksProps(blockProps, {
        template: TEMPLATE,
    });

    return (
        <>
            <InspectorControls>
                <PanelBody title={__('Tab section', TEXT_DOMAIN)} initialOpen>
                    <TextControl
                        label={__('Tab label', TEXT_DOMAIN)}
                        help={__(
                            'Shown on the tab trigger above this panel.',
                            TEXT_DOMAIN
                        )}
                        value={label}
                        onChange={(value) => setAttributes({ label: value })}
                        __nextHasNoMarginBottom
                    />
                    <TextControl
                        label={__('Tab id', TEXT_DOMAIN)}
                        help={__(
                            'Auto-generated on insert; override if you need a stable URL anchor.',
                            TEXT_DOMAIN
                        )}
                        value={tabId}
                        onChange={(value) => setAttributes({ tabId: slugify(value) })}
                        __nextHasNoMarginBottom
                    />
                </PanelBody>
            </InspectorControls>
            <div {...innerBlocksProps} />
        </>
    );
}
