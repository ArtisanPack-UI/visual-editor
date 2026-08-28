/**
 * Tabs — save component.
 *
 * Dynamic block: the renderers walk the persisted inner-block tree
 * (the tab-sections) and build the tablist at render time. So `save()`
 * persists the `ap-tabs` wrapper (carrying the align/spacing modifier
 * classes) and the `ap-tabs__container` inner-blocks host — it
 * deliberately omits the editor-only tablist UI and the runtime
 * `data-active-tab` value. The wrappers must still round-trip, or
 * Gutenberg flags the saved markup as "unexpected or invalid content"
 * (#747).
 */

/* eslint-disable @typescript-eslint/no-explicit-any */
import type { ReactElement } from 'react';
import { useBlockProps, useInnerBlocksProps } from '@wordpress/block-editor';

type TabsAlign = 'horizontal' | 'vertical';
type TabsSpacing = 'start' | 'end' | 'center' | 'equal';

interface TabsSaveAttributes {
    readonly tabsAlign: TabsAlign;
    readonly tabsSpacing: TabsSpacing;
}

export default function TabsSave({
    attributes,
}: {
    attributes: TabsSaveAttributes;
}): ReactElement {
    const { tabsAlign, tabsSpacing } = attributes;
    const blockProps = (useBlockProps.save as any)({
        className: `ap-tabs align-tabs-${tabsAlign} space-tabs-${tabsSpacing}`,
    });
    const innerBlocksProps = (useInnerBlocksProps.save as any)({
        className: 'ap-tabs__container',
    });
    return (
        <div {...blockProps}>
            <div {...innerBlocksProps} />
        </div>
    );
}
