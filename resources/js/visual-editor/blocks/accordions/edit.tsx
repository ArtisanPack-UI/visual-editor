/**
 * Accordions — editor-side component.
 *
 * Parent block in the accordion family (#497). Renders a single block
 * `div` that hosts one or more `artisanpack/accordion` children via
 * `useInnerBlocksProps`. Exposes a single inspector toggle: when
 * `faqSchema` is on, the Blade renderer walks the child panels and
 * emits FAQPage JSON-LD alongside the accordion (#757). Styling and
 * layout are surfaced through the standard block supports declared
 * in `block.json`.
 */

import type { ReactElement } from 'react';
import {
    __experimentalGetGapCSSValue as getGapCSSValue,
    InspectorControls,
    useBlockProps,
    useInnerBlocksProps,
} from '@wordpress/block-editor';
import { PanelBody, ToggleControl } from '@wordpress/components';
import { __ } from '@wordpress/i18n';

import { TEXT_DOMAIN } from '../../vendor/i18n';

const ALLOWED_BLOCKS: string[] = ['artisanpack/accordion'];

const TEMPLATE: [string, Record<string, unknown>][] = [['artisanpack/accordion', {}]];

interface AccordionsAttributes {
    readonly faqSchema: boolean;
    readonly style?: {
        readonly spacing?: {
            readonly blockGap?: string | { readonly top?: string; readonly left?: string };
        };
    };
}

interface AccordionsEditProps {
    readonly attributes: AccordionsAttributes;
    readonly setAttributes: (next: Partial<AccordionsAttributes>) => void;
}

export default function AccordionsEdit({
    attributes,
    setAttributes,
}: AccordionsEditProps): ReactElement {
    const { faqSchema } = attributes;

    // #933 — the block's own Block spacing is the gap between panels, as
    // the renderers print it. Reading `--wp--style--block-gap` in CSS
    // would inherit an ancestor's gap instead.
    const blockGap = attributes.style?.spacing?.blockGap;
    const ownGap = (getGapCSSValue as (value?: string) => string | null)(
        typeof blockGap === 'string' ? blockGap : blockGap?.top
    );

    const blockProps = useBlockProps({
        className: 'ap-accordions',
        style: ownGap ? { rowGap: ownGap } : undefined,
    });
    const innerBlocksProps = useInnerBlocksProps(blockProps, {
        allowedBlocks: ALLOWED_BLOCKS,
        template: TEMPLATE,
    });

    return (
        <>
            <InspectorControls>
                <PanelBody title={__('Accordions settings', TEXT_DOMAIN)} initialOpen>
                    <ToggleControl
                        label={__('Emit FAQPage schema', TEXT_DOMAIN)}
                        help={__(
                            'Adds FAQPage JSON-LD built from each panel’s title (Question) and body (Answer). Leave off if the page already emits FAQ schema elsewhere — avoid double-emission.',
                            TEXT_DOMAIN
                        )}
                        checked={faqSchema}
                        onChange={(next) => setAttributes({ faqSchema: next })}
                        __nextHasNoMarginBottom
                    />
                </PanelBody>
            </InspectorControls>
            <div {...innerBlocksProps} />
        </>
    );
}
