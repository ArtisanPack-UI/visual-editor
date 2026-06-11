/**
 * Accordion body — editor-side component.
 *
 * Grandchild of `artisanpack/accordions`; nested under
 * `artisanpack/accordion`. The body is purely a content container in
 * the editor — the region wiring (`role`, `id`, `aria-labelledby`) is
 * stamped by the parent accordion renderer at render time using the
 * parent's `panelId` attribute. That keeps the panel id a single source
 * of truth on the accordion block.
 */

import type { ReactElement } from 'react';
import { useBlockProps, useInnerBlocksProps } from '@wordpress/block-editor';
import { __ } from '@wordpress/i18n';

import { TEXT_DOMAIN } from '../../vendor/i18n';

const TEMPLATE: [string, Record<string, unknown>][] = [['artisanpack/paragraph', {}]];

export default function AccordionBodyEdit(): ReactElement {
    const blockProps = useBlockProps({ className: 'ap-accordion__body' });
    const innerBlocksProps = useInnerBlocksProps(blockProps, {
        template: TEMPLATE,
        placeholder: __('Add panel content…', TEXT_DOMAIN),
    });

    return <div {...innerBlocksProps} />;
}
