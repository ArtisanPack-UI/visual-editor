import { describe, expect, it } from 'vitest';
import { render, screen } from '@testing-library/react';
import { select } from '@wordpress/data';

import {
    EntityProvider,
    store,
    useEntityBlockEditor,
    useEntityId,
    useEntityProp,
    useEntityRecord,
    useEntityRecords,
    useResourcePermissions,
} from '../core-data-shim';

describe('core-data-shim store', () => {
    it('registers under the "core" key', () => {
        expect(store).toBeDefined();
    });

    it('returns null from getEntityRecord-family selectors', () => {
        const coreSelect = select('core') as Record<
            string,
            (...args: unknown[]) => unknown
        >;

        expect(coreSelect.getEntityRecord('postType', 'post', 1)).toBeNull();
        expect(coreSelect.getCurrentUser()).toBeNull();
        expect(coreSelect.getMedia(42)).toBeNull();
    });

    it('returns [] from list selectors', () => {
        const coreSelect = select('core') as Record<
            string,
            (...args: unknown[]) => unknown
        >;

        expect(coreSelect.getEntityRecords('postType', 'post')).toEqual([]);
        expect(coreSelect.getUsers()).toEqual([]);
    });

    it('reports resolution as finished and not in-flight', () => {
        const coreSelect = select('core') as Record<
            string,
            (...args: unknown[]) => unknown
        >;

        expect(
            coreSelect.hasFinishedResolution('getEntityRecord', [
                'postType',
                'post',
                1,
            ])
        ).toBe(true);
        expect(
            coreSelect.isResolving('getEntityRecord', ['postType', 'post', 1])
        ).toBe(false);
    });
});

describe('core-data-shim hooks', () => {
    function renderHook<T>(hook: () => T): T {
        let captured!: T;
        function Probe() {
            captured = hook();
            return null;
        }
        render(<Probe />);
        return captured;
    }

    it('useEntityRecord returns an empty, resolved record', () => {
        const result = renderHook(() => useEntityRecord());
        expect(result).toMatchObject({
            record: null,
            editedRecord: null,
            hasEdits: false,
            hasResolved: true,
            isResolving: false,
        });
        expect(typeof result.edit).toBe('function');
        expect(typeof result.save).toBe('function');
    });

    it('useEntityRecords returns an empty, resolved list', () => {
        const result = renderHook(() => useEntityRecords());
        expect(result.records).toEqual([]);
        expect(result.hasResolved).toBe(true);
        expect(result.isResolving).toBe(false);
        expect(result.totalItems).toBe(0);
        expect(result.totalPages).toBe(0);
    });

    it('useEntityProp returns [undefined, setter, undefined]', () => {
        const [value, setter, rawValue] = renderHook(() => useEntityProp());
        expect(value).toBeUndefined();
        expect(rawValue).toBeUndefined();
        expect(typeof setter).toBe('function');
    });

    it('useEntityBlockEditor returns an empty block list and stable setters', () => {
        const [blocks, onInput, onChange] = renderHook(() =>
            useEntityBlockEditor()
        );
        expect(blocks).toEqual([]);
        expect(typeof onInput).toBe('function');
        expect(typeof onChange).toBe('function');
    });

    it('useResourcePermissions denies everything and reports resolved', () => {
        const perms = renderHook(() => useResourcePermissions());
        expect(perms).toEqual({
            canCreate: false,
            canUpdate: false,
            canDelete: false,
            isResolving: false,
        });
    });

    it('useEntityId reads from EntityProvider context', () => {
        function Probe() {
            const id = useEntityId();
            return <span data-testid="probe">{String(id ?? 'none')}</span>;
        }

        render(
            <EntityProvider kind="postType" name="page" id={7}>
                <Probe />
            </EntityProvider>
        );

        expect(screen.getByTestId('probe').textContent).toBe('7');
    });

    it('useEntityId returns undefined outside EntityProvider', () => {
        function Probe() {
            const id = useEntityId();
            return <span data-testid="probe">{String(id ?? 'none')}</span>;
        }

        render(<Probe />);
        expect(screen.getByTestId('probe').textContent).toBe('none');
    });
});
