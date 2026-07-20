/**
 * Tests for `resources/js/visual-editor/support/hook-aliases.ts` (#664).
 *
 * Confirms bidirectional passthrough between renamed hook pairs, no
 * infinite recursion when both names carry subscribers, and idempotent
 * registration.
 */

import { addFilter, applyFilters, removeAllFilters } from '@wordpress/hooks';
import { afterEach, beforeEach, describe, expect, it } from 'vitest';

import {
    __resetHookAliasesForTests,
    registerHookAliases,
} from '../hook-aliases';

const OLD_RESOURCES = 'ap.visual-editor.resources';
const NEW_RESOURCES = 'ap.visualEditor.resources';
const OLD_TEMPLATES = 'ap.visual-editor.templates';
const NEW_TEMPLATES = 'ap.visualEditor.templates';
const OLD_RENDERED = 'ap.visual-editor.rendered-block';
const NEW_RENDERED = 'ap.visualEditor.renderedBlock';

const ALL_PAIRS: ReadonlyArray<readonly [string, string]> = [
    [OLD_RESOURCES, NEW_RESOURCES],
    [OLD_TEMPLATES, NEW_TEMPLATES],
    [OLD_RENDERED, NEW_RENDERED],
];

function clearAll(): void {
    for (const [oldName, newName] of ALL_PAIRS) {
        removeAllFilters(oldName);
        removeAllFilters(newName);
    }
}

describe('registerHookAliases', () => {
    beforeEach(() => {
        __resetHookAliasesForTests();
        clearAll();
        registerHookAliases();
    });

    afterEach(() => {
        __resetHookAliasesForTests();
        clearAll();
    });

    it('routes old-name subscribers when new name is applied', () => {
        let seen: unknown = null;
        addFilter(
            OLD_RESOURCES,
            'test/old-subscriber',
            (value: unknown) => {
                seen = value;
                return value;
            },
        );

        const result = applyFilters(NEW_RESOURCES, { key: 'value' });

        expect(seen).toEqual({ key: 'value' });
        expect(result).toEqual({ key: 'value' });
    });

    it('routes new-name subscribers when old name is applied', () => {
        let seen: unknown = null;
        addFilter(
            NEW_TEMPLATES,
            'test/new-subscriber',
            (value: unknown) => {
                seen = value;
                return value;
            },
        );

        const result = applyFilters(OLD_TEMPLATES, ['a', 'b']);

        expect(seen).toEqual(['a', 'b']);
        expect(result).toEqual(['a', 'b']);
    });

    it('does not recurse infinitely when both names carry subscribers', () => {
        let oldCalls = 0;
        let newCalls = 0;

        addFilter(OLD_RENDERED, 'test/count-old', (value: unknown) => {
            oldCalls += 1;
            return value;
        });
        addFilter(NEW_RENDERED, 'test/count-new', (value: unknown) => {
            newCalls += 1;
            return value;
        });

        // Applying either name should fire each subscriber exactly once.
        applyFilters(NEW_RENDERED, 'html');
        expect(oldCalls).toBe(1);
        expect(newCalls).toBe(1);

        applyFilters(OLD_RENDERED, 'html');
        expect(oldCalls).toBe(2);
        expect(newCalls).toBe(2);
    });

    it('is idempotent — second registerHookAliases() does not double-fire subscribers', () => {
        let calls = 0;
        addFilter(OLD_RESOURCES, 'test/idem', (value: unknown) => {
            calls += 1;
            return value;
        });

        registerHookAliases();
        registerHookAliases();

        applyFilters(NEW_RESOURCES, 'x');

        expect(calls).toBe(1);
    });
});
