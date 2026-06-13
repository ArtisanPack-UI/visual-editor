/**
 * Accordion panel — editor-side component.
 *
 * Child of `artisanpack/accordions`; ships its own pair of grandchildren
 * (`accordion-title` + `accordion-body`). The accordion is the single
 * source of truth for the panel id and toggle icon — the renderer
 * stamps those onto the title's `aria-controls` / icon class and the
 * body's `id` automatically, so authors only ever set the id once. A
 * stable id is auto-generated on first mount if the author hasn't set
 * one, so the wiring works out of the box.
 */

import { useEffect, type ReactElement } from 'react';
import { InspectorControls, useBlockProps, useInnerBlocksProps } from '@wordpress/block-editor';
import { PanelBody, SelectControl, TextControl } from '@wordpress/components';
import { __ } from '@wordpress/i18n';

import { TEXT_DOMAIN } from '../../vendor/i18n';

interface AccordionAttributes {
    readonly panelId: string;
    readonly panelIcon: 'plus-minus' | 'arrows';
}

interface AccordionEditProps {
    readonly attributes: AccordionAttributes;
    readonly setAttributes: (next: Partial<AccordionAttributes>) => void;
    readonly clientId: string;
}

const ALLOWED_BLOCKS: string[] = [
    'artisanpack/accordion-title',
    'artisanpack/accordion-body',
];

const TEMPLATE: [string, Record<string, unknown>][] = [
    ['artisanpack/accordion-title', {}],
    ['artisanpack/accordion-body', {}],
];

const ICON_OPTIONS = [
    { value: 'plus-minus' as const, label: 'Plus / Minus' },
    { value: 'arrows' as const, label: 'Arrows' },
];

function slugify(value: string): string {
    return value
        .toLowerCase()
        .replace(/[^a-z0-9-]+/g, '-')
        .replace(/^-+|-+$/g, '');
}

function makeAutoPanelId(clientId: string): string {
    // Derive from clientId so the same accordion always lands on the
    // same id across re-renders; trim to keep DOM ids short.
    const suffix = clientId.replace(/-/g, '').slice(0, 8);
    return `panel-${suffix}`;
}

export default function AccordionEdit({
    attributes,
    setAttributes,
    clientId,
}: AccordionEditProps): ReactElement {
    const { panelId, panelIcon } = attributes;

    useEffect(() => {
        if (panelId === '') {
            setAttributes({ panelId: makeAutoPanelId(clientId) });
        }
    }, [panelId, clientId, setAttributes]);

    const blockProps = useBlockProps({ className: 'ap-accordion' });
    const innerBlocksProps = useInnerBlocksProps(blockProps, {
        allowedBlocks: ALLOWED_BLOCKS,
        template: TEMPLATE,
    });

    return (
        <>
            <InspectorControls>
                <PanelBody title={__('Accordion settings', TEXT_DOMAIN)} initialOpen>
                    <TextControl
                        label={__('Panel id', TEXT_DOMAIN)}
                        help={__(
                            'Auto-generated on insert. Used for the panel id and the toggle aria-controls hookup.',
                            TEXT_DOMAIN
                        )}
                        value={panelId}
                        onChange={(value) => setAttributes({ panelId: slugify(value) })}
                        __nextHasNoMarginBottom
                    />
                    <SelectControl
                        label={__('Toggle icon', TEXT_DOMAIN)}
                        value={panelIcon}
                        options={ICON_OPTIONS.map((option) => ({
                            label: __(option.label, TEXT_DOMAIN),
                            value: option.value,
                        }))}
                        onChange={(value) => {
                            if (value === 'plus-minus' || value === 'arrows') {
                                setAttributes({ panelIcon: value });
                            }
                        }}
                        __nextHasNoMarginBottom
                    />
                </PanelBody>
            </InspectorControls>
            <div {...innerBlocksProps} />
        </>
    );
}
