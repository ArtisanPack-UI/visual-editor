/**
 * Tabs — editor-side component.
 *
 * Parent block in the tabs family (#497). The tab triggers are derived
 * directly from the inner-block tab-sections — each section owns its
 * own `label` + `tabId`, and the tabs block just stitches them into a
 * tablist. The editor shows the tab list with one trigger active at a
 * time so authors can switch between sections; "Add tab" appends a
 * new tab-section child.
 */

import { useMemo, useState, type ReactElement } from 'react';
import { InspectorControls, useBlockProps, useInnerBlocksProps } from '@wordpress/block-editor';
import { useDispatch, useSelect } from '@wordpress/data';
import { Button, PanelBody, SelectControl } from '@wordpress/components';
import { createBlock } from '@wordpress/blocks';
import { __ } from '@wordpress/i18n';

import { TEXT_DOMAIN } from '../../vendor/i18n';

type TabsAlign = 'horizontal' | 'vertical';
type TabsSpacing = 'start' | 'end' | 'center' | 'equal';

interface TabsAttributes {
    readonly tabsAlign: TabsAlign;
    readonly tabsSpacing: TabsSpacing;
}

interface TabsEditProps {
    readonly attributes: TabsAttributes;
    readonly setAttributes: (next: Partial<TabsAttributes>) => void;
    readonly clientId: string;
}

const ALLOWED_BLOCKS: string[] = ['artisanpack/tab-section'];

const TEMPLATE: [string, Record<string, unknown>][] = [
    ['artisanpack/tab-section', {}],
    ['artisanpack/tab-section', {}],
];

const ALIGN_OPTIONS: ReadonlyArray<{ value: TabsAlign; label: string }> = [
    { value: 'horizontal', label: 'Horizontal' },
    { value: 'vertical', label: 'Vertical' },
];

const SPACING_OPTIONS: ReadonlyArray<{ value: TabsSpacing; label: string }> = [
    { value: 'start', label: 'Start (Top / Left)' },
    { value: 'end', label: 'End (Bottom / Right)' },
    { value: 'center', label: 'Center' },
    { value: 'equal', label: 'Equal' },
];

interface SectionSummary {
    readonly clientId: string;
    readonly label: string;
    readonly tabId: string;
}

export default function TabsEdit({
    attributes,
    setAttributes,
    clientId,
}: TabsEditProps): ReactElement {
    const { tabsAlign, tabsSpacing } = attributes;
    const [activeClientId, setActiveClientId] = useState<string | null>(null);

    const sections = useSelect<SectionSummary[]>(
        (select) => {
            const store = select('core/block-editor') as unknown as {
                getBlocks: (id: string) => Array<{
                    clientId: string;
                    name: string;
                    attributes: Record<string, unknown>;
                }>;
            };
            return store
                .getBlocks(clientId)
                .filter((block) => block.name === 'artisanpack/tab-section')
                .map((block) => ({
                    clientId: block.clientId,
                    label:
                        typeof block.attributes.label === 'string'
                            ? block.attributes.label
                            : '',
                    tabId:
                        typeof block.attributes.tabId === 'string'
                            ? block.attributes.tabId
                            : '',
                }));
        },
        [clientId]
    );

    const { insertBlock, selectBlock } = useDispatch('core/block-editor') as unknown as {
        insertBlock: (block: unknown, index?: number, rootClientId?: string) => void;
        selectBlock: (clientId: string) => void;
    };

    const activeId = useMemo(() => {
        if (sections.length === 0) {
            return null;
        }
        if (activeClientId !== null && sections.some((s) => s.clientId === activeClientId)) {
            return activeClientId;
        }
        return sections[0].clientId;
    }, [activeClientId, sections]);

    const blockProps = useBlockProps({
        className: `ap-tabs align-tabs-${tabsAlign} space-tabs-${tabsSpacing}`,
    });

    const innerBlocksProps = useInnerBlocksProps(
        { className: 'ap-tabs__container', 'data-active-tab': activeId ?? '' },
        {
            allowedBlocks: ALLOWED_BLOCKS,
            template: TEMPLATE,
            renderAppender: false,
        }
    );

    const handleAddTab = (): void => {
        const block = createBlock('artisanpack/tab-section', {});
        insertBlock(block, sections.length, clientId);
    };

    return (
        <>
            <InspectorControls>
                <PanelBody title={__('Layout', TEXT_DOMAIN)} initialOpen>
                    <SelectControl
                        label={__('Tab list orientation', TEXT_DOMAIN)}
                        value={tabsAlign}
                        options={ALIGN_OPTIONS.map((option) => ({
                            label: __(option.label, TEXT_DOMAIN),
                            value: option.value,
                        }))}
                        onChange={(value) => {
                            if (value === 'horizontal' || value === 'vertical') {
                                setAttributes({ tabsAlign: value });
                            }
                        }}
                        __nextHasNoMarginBottom
                    />
                    <SelectControl
                        label={__('Tab list spacing', TEXT_DOMAIN)}
                        value={tabsSpacing}
                        options={SPACING_OPTIONS.map((option) => ({
                            label: __(option.label, TEXT_DOMAIN),
                            value: option.value,
                        }))}
                        onChange={(value) => {
                            if (
                                value === 'start' ||
                                value === 'end' ||
                                value === 'center' ||
                                value === 'equal'
                            ) {
                                setAttributes({ tabsSpacing: value });
                            }
                        }}
                        __nextHasNoMarginBottom
                    />
                </PanelBody>
            </InspectorControls>
            <div {...blockProps}>
                <div className="ap-tabs__list" role="tablist">
                    <ul>
                        {sections.map((section) => {
                            const isActive = section.clientId === activeId;
                            return (
                                <li key={section.clientId}>
                                    <button
                                        type="button"
                                        role="tab"
                                        aria-selected={isActive ? 'true' : 'false'}
                                        tabIndex={isActive ? 0 : -1}
                                        onClick={() => {
                                            setActiveClientId(section.clientId);
                                            selectBlock(section.clientId);
                                        }}
                                    >
                                        {section.label ||
                                            __('Untitled tab', TEXT_DOMAIN)}
                                    </button>
                                </li>
                            );
                        })}
                        <li className="ap-tabs__add-tab">
                            <Button
                                variant="secondary"
                                onClick={handleAddTab}
                                aria-label={__('Add tab', TEXT_DOMAIN)}
                            >
                                {__('+ Tab', TEXT_DOMAIN)}
                            </Button>
                        </li>
                    </ul>
                </div>
                <div {...innerBlocksProps} />
            </div>
        </>
    );
}
