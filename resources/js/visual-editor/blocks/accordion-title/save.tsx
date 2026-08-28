/**
 * Accordion title — save component.
 *
 * Dynamic block: the parent accordion renderer walks the persisted inner
 * tree and stamps the full toggle wiring (role, aria-controls, id, icon)
 * from the parent's `panelId` / `panelIcon` at render time. So `save()`
 * persists only the title wrapper + inner content — it deliberately omits
 * the editor-only preview icon span (which depends on the parent's
 * `panelIcon`, unavailable to `save`). The wrapper must still round-trip,
 * or Gutenberg flags the saved markup as invalid (#747).
 */

/* eslint-disable @typescript-eslint/no-explicit-any */
import type { ReactElement } from 'react';
import { useBlockProps, useInnerBlocksProps } from '@wordpress/block-editor';

export default function AccordionTitleSave(): ReactElement {
    const blockProps = (useBlockProps.save as any)({
        className: 'ap-accordion__title-content',
    });
    const innerBlocksProps = (useInnerBlocksProps.save as any)(blockProps);
    return (
        <div className="ap-accordion__title">
            <div {...innerBlocksProps} />
        </div>
    );
}
