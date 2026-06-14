/**
 * Accordion title — editor-side component.
 *
 * Grandchild of `artisanpack/accordions`; nested under
 * `artisanpack/accordion`. The title is purely a content container in
 * the editor — the toggle wiring (`role`, `aria-controls`, `aria-expanded`)
 * is stamped by the parent accordion renderer at render time using the
 * parent's `panelId` / `panelIcon` attributes. The editor still reads
 * the parent's `panelIcon` so the inserter / canvas preview can show
 * the right toggle icon next to each title.
 */

import type { ReactElement } from 'react';
import { useBlockProps, useInnerBlocksProps } from '@wordpress/block-editor';
import { useSelect } from '@wordpress/data';
import { __ } from '@wordpress/i18n';

import { TEXT_DOMAIN } from '../../vendor/i18n';

interface AccordionTitleEditProps {
    readonly clientId: string;
}

type AccordionPanelIcon = 'plus-minus' | 'arrows';

const TEMPLATE: [string, Record<string, unknown>][] = [
    ['artisanpack/heading', { level: 3 }],
];

function normalizeIcon(value: unknown): AccordionPanelIcon {
    return value === 'arrows' ? 'arrows' : 'plus-minus';
}

export default function AccordionTitleEdit({
    clientId,
}: AccordionTitleEditProps): ReactElement {
    const panelIcon = useSelect<AccordionPanelIcon>(
        (select) => {
            const store = select('core/block-editor') as unknown as {
                getBlockRootClientId: (id: string) => string | null;
                getBlock: (id: string) => {
                    attributes?: Record<string, unknown>;
                } | null;
            };
            const parentId = store.getBlockRootClientId(clientId);
            if (parentId === null || parentId === '') {
                return 'plus-minus';
            }
            const parent = store.getBlock(parentId);
            return normalizeIcon(parent?.attributes?.panelIcon);
        },
        [clientId]
    );

    const blockProps = useBlockProps({ className: 'ap-accordion__title-content' });
    const innerBlocksProps = useInnerBlocksProps(blockProps, {
        template: TEMPLATE,
        placeholder: __('Add a heading…', TEXT_DOMAIN),
    });

    return (
        <div className="ap-accordion__title">
            <div {...innerBlocksProps} />
            <span
                className={`ap-accordion__icon ap-accordion__icon--${panelIcon}`}
                aria-hidden="true"
            />
        </div>
    );
}
