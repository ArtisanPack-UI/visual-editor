/**
 * Accordion + Tabs family save contract (#747).
 *
 * These are dynamic (server-rendered) blocks, but each hosts inner
 * blocks inside a wrapper `<div>`, so `save()` must reproduce that
 * wrapper or Gutenberg's validator rejects the persisted markup as
 * "unexpected or invalid content". The renderers still walk the inner
 * tree and stamp their own toggle / tablist wiring at render time, so
 * `save()` deliberately omits editor-only chrome (the accordion-title
 * icon span, the tabs tablist and its runtime `data-active-tab`).
 */

import { describe, expect, it, vi } from 'vitest';

vi.mock('@wordpress/block-editor', () => ({
    InnerBlocks: Object.assign(() => null, { Content: () => null }),
    useBlockProps: Object.assign(() => ({}), {
        save: (props: { className?: string }) => ({ ...props }),
    }),
    useInnerBlocksProps: Object.assign(() => ({}), {
        save: (blockProps: Record<string, unknown>) => ({ ...blockProps }),
    }),
}));

import accordionSave from '../accordion/save';
import accordionsSave from '../accordions/save';
import accordionBodySave from '../accordion-body/save';
import accordionTitleSave from '../accordion-title/save';
import tabsSave from '../tabs/save';
import tabSectionSave from '../tab-section/save';

interface SaveElement {
    readonly type: unknown;
    readonly props: {
        readonly className?: string;
        readonly children?: unknown;
    };
}

describe('accordion + tabs family save contract', () => {
    it.each([
        ['accordion', accordionSave, 'ap-accordion'],
        ['accordions', accordionsSave, 'ap-accordions'],
        ['accordion-body', accordionBodySave, 'ap-accordion__body'],
        ['tab-section', tabSectionSave, 'ap-tab-section'],
    ] as const)(
        '%s save reproduces a single wrapper div carrying the block className',
        (_slug, save, className) => {
            const element = (save as () => SaveElement)();
            expect(element.type).toBe('div');
            expect(element.props.className).toBe(className);
        }
    );

    it('accordion-title nests the title-content host inside ap-accordion__title, no icon chrome', () => {
        const element = accordionTitleSave() as unknown as SaveElement;
        expect(element.type).toBe('div');
        expect(element.props.className).toBe('ap-accordion__title');
        // The only child is the inner-blocks host; the preview icon span
        // is editor-only chrome and must not be serialized.
        const child = element.props.children as SaveElement;
        expect(child.type).toBe('div');
        expect(child.props.className).toBe('ap-accordion__title-content');
    });

    it('tabs nests the ap-tabs__container host inside the ap-tabs wrapper, no tablist chrome', () => {
        const element = tabsSave({
            attributes: { tabsAlign: 'horizontal', tabsSpacing: 'start' },
        }) as unknown as SaveElement;
        expect(element.type).toBe('div');
        expect(element.props.className).toBe(
            'ap-tabs align-tabs-horizontal space-tabs-start'
        );
        const child = element.props.children as SaveElement;
        expect(child.type).toBe('div');
        expect(child.props.className).toBe('ap-tabs__container');
    });
});
