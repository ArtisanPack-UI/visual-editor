import { act, render, screen, waitFor } from '@testing-library/react';
import userEvent from '@testing-library/user-event';
import type { ReactNode } from 'react';
import { afterEach, beforeEach, describe, expect, it, vi } from 'vitest';

// Stub `BlockCanvas`, `BlockEditorProvider`, `BlockInspector`, and the
// `SlotFillProvider` / `Popover` pair so the hook's canvas/inspector
// renders run under jsdom without reaching into the real Gutenberg
// data store. The entity-editor's shell-side logic is what's under test
// here; the actual block-rendering integration is covered by the
// canvas-frame suite.
vi.mock('@wordpress/block-editor', () => ({
    BlockEditorProvider: ({
        children,
        onChange,
    }: {
        children?: ReactNode;
        onChange?: (blocks: unknown[]) => void;
    }): JSX.Element => (
        <div data-testid="ap-stub-block-editor-provider">
            <button
                type="button"
                data-testid="ap-stub-block-editor-fire-change"
                onClick={() => onChange?.([{ name: 'core/paragraph' }])}
            >
                fire-change
            </button>
            {children}
        </div>
    ),
    BlockInspector: (): JSX.Element => (
        <div data-testid="ap-stub-block-inspector" />
    ),
    BlockList: (): JSX.Element => <div data-testid="ap-stub-block-list" />,
    BlockTools: ({ children }: { children?: ReactNode }): JSX.Element => (
        <div>{children}</div>
    ),
    ObserveTyping: ({ children }: { children?: ReactNode }): JSX.Element => (
        <div>{children}</div>
    ),
    WritingFlow: ({ children }: { children?: ReactNode }): JSX.Element => (
        <div>{children}</div>
    ),
}));

vi.mock('@wordpress/blocks', () => ({
    parse: (raw: string): unknown[] =>
        raw === '' ? [] : [{ name: 'core/paragraph', clientId: 'stub' }],
    serialize: (): string => '',
}));

vi.mock('@wordpress/format-library', () => ({}));

vi.mock('@wordpress/components', () => {
    const SlotFillProvider = ({ children }: { children?: ReactNode }): JSX.Element => (
        <div>{children}</div>
    );

    function PopoverSlot(): null {
        return null;
    }

    const Popover = Object.assign(() => null, { Slot: PopoverSlot });

    return { SlotFillProvider, Popover };
});

vi.mock('@wordpress/data', () => ({
    useSelect: () => false,
}));

const FETCH_MOCK = vi.fn();
const UPDATE_MOCK = vi.fn();

vi.mock('../api-client', async () => {
    const actual = await vi.importActual<typeof import('../api-client')>(
        '../api-client'
    );

    return {
        ...actual,
        fetchEntity: (...args: unknown[]) => FETCH_MOCK(...args),
        updateEntity: (...args: unknown[]) => UPDATE_MOCK(...args),
    };
});

import { useEntityEditorViews } from '../entity-editor';
import type { EntityEditorState } from '../entity-editor';

const API_CONFIG = { apiBase: '/visual-editor/api' };

beforeEach(() => {
    FETCH_MOCK.mockReset();
    UPDATE_MOCK.mockReset();
});

afterEach(() => {
    vi.unstubAllGlobals();
});

function Harness(props: {
    entityId: string | null;
    onState: (state: EntityEditorState) => void;
}): JSX.Element {
    const { entityId, onState } = props;

    const views = useEntityEditorViews({
        apiConfig: API_CONFIG,
        kind: 'template',
        entityId,
        onStateChange: onState,
    });

    return (
        <div>
            {views.canvas}
            {views.inspector}
        </div>
    );
}

describe('useEntityEditorViews', () => {
    it('renders the inactive empty state when no entityId is set', () => {
        const onState = vi.fn();

        render(<Harness entityId={null} onState={onState} />);

        expect(
            screen.getByTestId('ap-site-editor-entity-canvas-inactive')
        ).toBeInTheDocument();
    });

    it('loads the entity, announces it, and renders the canvas', async () => {
        FETCH_MOCK.mockResolvedValue({
            id: 7,
            slug: 'single',
            title: { rendered: 'Single post' },
            description: '',
            content: { raw: '', blocks: [{ name: 'core/paragraph' }] },
            status: 'publish',
            theme: 'default',
            type: 'wp_template',
            source: 'custom',
            origin: null,
        });

        const onState = vi.fn();

        render(<Harness entityId="7" onState={onState} />);

        await waitFor(() =>
            expect(
                screen.getByTestId('ap-site-editor-entity-canvas')
            ).toBeInTheDocument()
        );

        expect(
            screen.getByTestId('ap-site-editor-entity-canvas-announce')
        ).toHaveTextContent('Editing Single post');

        expect(
            screen.getByTestId('ap-site-editor-entity-canvas-chain')
        ).toBeInTheDocument();
    });

    it('flips the dirty flag through onStateChange when the canvas edits blocks', async () => {
        FETCH_MOCK.mockResolvedValue({
            id: 1,
            slug: 'index',
            title: { rendered: 'Index' },
            description: '',
            content: { raw: '', blocks: [] },
            status: 'publish',
            theme: 'default',
            type: 'wp_template',
            source: 'custom',
            origin: null,
        });

        const states: EntityEditorState[] = [];
        const onState = (state: EntityEditorState): void => {
            states.push(state);
        };

        render(<Harness entityId="1" onState={onState} />);

        await waitFor(() =>
            expect(
                screen.getByTestId('ap-site-editor-entity-canvas')
            ).toBeInTheDocument()
        );

        const user = userEvent.setup();
        await user.click(
            screen.getByTestId('ap-stub-block-editor-fire-change')
        );

        await waitFor(() => expect(states.some((s) => s.isDirty)).toBe(true));

        expect(
            screen.getByTestId('ap-site-editor-entity-canvas-dirty')
        ).toBeInTheDocument();
    });

    it('save() dispatches PUT and clears dirty on success', async () => {
        FETCH_MOCK.mockResolvedValue({
            id: 1,
            slug: 'index',
            title: { rendered: 'Index' },
            description: '',
            content: { raw: '', blocks: [] },
            status: 'publish',
            theme: 'default',
            type: 'wp_template',
            source: 'custom',
            origin: null,
        });

        UPDATE_MOCK.mockImplementation(async (_c, _k, _id, payload) => ({
            id: 1,
            slug: 'index',
            title: { rendered: 'Index' },
            description: '',
            content: payload.content,
            status: 'publish',
            theme: 'default',
            type: 'wp_template',
            source: 'custom',
            origin: null,
        }));

        const latestRef: { value: EntityEditorState | null } = { value: null };
        const onState = (state: EntityEditorState): void => {
            latestRef.value = state;
        };

        render(<Harness entityId="1" onState={onState} />);

        await waitFor(() =>
            expect(
                screen.getByTestId('ap-site-editor-entity-canvas')
            ).toBeInTheDocument()
        );

        // Mutate and save.
        const user = userEvent.setup();
        await user.click(
            screen.getByTestId('ap-stub-block-editor-fire-change')
        );

        await waitFor(() => expect(latestRef.value?.isDirty).toBe(true));

        expect(latestRef.value?.save).not.toBeNull();

        await act(async () => {
            await latestRef.value?.save?.();
        });

        expect(UPDATE_MOCK).toHaveBeenCalledTimes(1);
        await waitFor(() => expect(latestRef.value?.saveStatus).toBe('saved'));
        expect(latestRef.value?.isDirty).toBe(false);
    });
});
