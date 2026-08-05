/**
 * `ChromeBlocks` renders the applied template's header/footer as *inert*
 * previews (#655). "Inert" here has a specific meaning, and this file
 * exists to pin it.
 *
 * The abandoned first cut of #621 fed a composed tree to the content
 * `BlockEditorProvider` and made the chrome read-only by stamping `lock`
 * attributes onto it. That design froze the editor (`value` → `onChange`
 * → recompose → `value`, forever) and was replaced by
 * `__experimentalUseBlockPreview`, which mounts its *own* block-editor
 * provider, wraps the subtree in `useDisabled()` (genuinely
 * non-selectable, non-editable), and sets `isPreviewMode`.
 *
 * Nothing about that is enforced by types, so a future change could
 * quietly reintroduce the lock-based shape and pass every other test in
 * the suite. These assertions fail if it does:
 *
 *   - the preview hook must be the render path (a lock-based rewrite
 *     would go through `BlockEditorProvider` + `BlockList` instead, both
 *     stubbed here as tripwires);
 *   - the blocks handed to it must be untouched — no injected `lock`
 *     attribute, no `templateLock`;
 *   - whatever inert markers the hook returns (`inert`, `aria-disabled`,
 *     `contentEditable: false`) must reach the DOM rather than be
 *     dropped on the floor by the spread.
 *
 * `@wordpress/block-editor` is stubbed, matching `editor-canvas.test.tsx`
 * and `site-editor/__tests__/canvas-frame.test.tsx` — the real module
 * pulls `@wordpress/blocks`, which vitest cannot import (JSON import
 * attribute). That scopes this file to *our* contract with the hook.
 */

import { render, screen } from '@testing-library/react';
import { beforeEach, describe, expect, it, vi } from 'vitest';
import type { BlockInstance } from '@wordpress/blocks';

const useBlockPreview = vi.fn(
    (options: { props?: Record<string, unknown> }) => ({
        ...(options.props ?? {}),
        // Stand-ins for what `useDisabled()` and `isPreviewMode` put on
        // the node in the real hook.
        inert: '',
        'aria-disabled': 'true',
        contentEditable: false,
    })
);
const blockEditorProviderTripwire = vi.fn();
const blockListTripwire = vi.fn();

vi.mock('@wordpress/block-editor', () => ({
    __experimentalUseBlockPreview: (options: unknown) =>
        (useBlockPreview as unknown as (o: unknown) => unknown)(options),
    BlockEditorProvider: (props: { children?: unknown }): JSX.Element => {
        blockEditorProviderTripwire(props);

        return <div data-testid="ap-stub-block-editor-provider" />;
    },
    BlockList: (): JSX.Element => {
        blockListTripwire();

        return <div data-testid="ap-stub-block-list" />;
    },
}));

import { ROOT_CANVAS_LAYOUT } from '../../../editor-settings';
import { ChromeBlocks } from '../ChromeBlocks';

function block(
    name: string,
    attributes: Record<string, unknown> = {}
): BlockInstance {
    return {
        clientId: `cid-${name}`,
        name,
        isValid: true,
        attributes,
        innerBlocks: [],
    } as unknown as BlockInstance;
}

const HEADER_LABEL = 'Template header (read-only)';

beforeEach(() => {
    useBlockPreview.mockClear();
    blockEditorProviderTripwire.mockClear();
    blockListTripwire.mockClear();
});

describe('ChromeBlocks inertness (#655)', () => {
    it('renders the chrome through the block-preview hook', () => {
        const blocks = [block('artisanpack/template-part')];

        render(
            <ChromeBlocks
                blocks={blocks}
                label={HEADER_LABEL}
                region="header"
            />
        );

        expect(useBlockPreview).toHaveBeenCalledTimes(1);
        expect(useBlockPreview.mock.calls[0]?.[0]).toMatchObject({
            blocks,
            layout: ROOT_CANVAS_LAYOUT,
        });
    });

    it('never mounts an editable block list for the chrome', () => {
        // The tripwire: a `lock`-attribute reimplementation renders the
        // chrome through a real provider + block list, both of which are
        // editable surfaces no matter what `lock` says.
        render(
            <ChromeBlocks
                blocks={[block('artisanpack/site-title')]}
                label={HEADER_LABEL}
                region="header"
            />
        );

        expect(blockEditorProviderTripwire).not.toHaveBeenCalled();
        expect(blockListTripwire).not.toHaveBeenCalled();
        expect(
            screen.queryByTestId('ap-stub-block-list')
        ).not.toBeInTheDocument();
    });

    it('hands the blocks over untouched — no lock attributes injected', () => {
        const original = block('artisanpack/site-title', { level: 1 });

        render(
            <ChromeBlocks
                blocks={[original]}
                label={HEADER_LABEL}
                region="header"
            />
        );

        const passed = (
            useBlockPreview.mock.calls[0]?.[0] as {
                blocks: readonly BlockInstance[];
            }
        ).blocks;

        // Same reference: hydration owns the tree, this component only
        // previews it.
        expect(passed[0]).toBe(original);
        expect(passed[0]?.attributes).toEqual({ level: 1 });
        expect(passed[0]?.attributes).not.toHaveProperty('lock');
        expect(
            useBlockPreview.mock.calls[0]?.[0]
        ).not.toHaveProperty('templateLock');
    });

    it('spreads the hook props so the inert markers reach the DOM', () => {
        render(
            <ChromeBlocks
                blocks={[block('artisanpack/site-title')]}
                label={HEADER_LABEL}
                region="header"
            />
        );

        const chrome = screen.getByLabelText(HEADER_LABEL);

        expect(chrome).toHaveAttribute('inert');
        expect(chrome).toHaveAttribute('aria-disabled', 'true');
        // React renders `contentEditable: false` as the literal attribute
        // `contenteditable="false"`; either way the chrome must never be
        // an editable host.
        expect(chrome.getAttribute('contenteditable')).not.toBe('true');
        expect(chrome.querySelector('[contenteditable="true"]')).toBeNull();
    });

    it('labels the region for assistive tech with a role that permits a label', () => {
        render(
            <ChromeBlocks
                blocks={[block('artisanpack/site-title')]}
                label={HEADER_LABEL}
                region="footer"
            />
        );

        const chrome = screen.getByLabelText(HEADER_LABEL);

        // `role="presentation"` with an `aria-label` is prohibited ARIA —
        // the label is dropped, so the region announced as nothing at all.
        expect(chrome).toHaveAttribute('role', 'group');
        expect(chrome).toHaveAttribute('data-chrome-region', 'footer');
        expect(chrome).toHaveClass('ap-visual-editor__chrome');
    });

    it('renders nothing — and calls no hook — for an empty region', () => {
        const { container } = render(
            <ChromeBlocks blocks={[]} label={HEADER_LABEL} region="footer" />
        );

        expect(container).toBeEmptyDOMElement();
        expect(useBlockPreview).not.toHaveBeenCalled();
    });
});
