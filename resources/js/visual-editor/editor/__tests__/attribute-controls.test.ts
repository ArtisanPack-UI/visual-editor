/**
 * Tests for the attribute-schema → inspector-control inference used to give
 * server-rendered third-party blocks a zero-build editing experience (#766).
 *
 * `@wordpress/components` is stubbed so importing the module under test does
 * not pull the whole component library into the unit run — only the pure
 * inference functions are exercised here.
 */

import { describe, it, expect, vi } from 'vitest';

vi.mock('@wordpress/components', () => ({
    RangeControl: () => null,
    SelectControl: () => null,
    TextareaControl: () => null,
    TextControl: () => null,
    ToggleControl: () => null,
}));

import {
    humanizeAttributeName,
    resolveAttributeControl,
    resolveAttributeControls,
    type AttributeSchema,
} from '../attribute-controls';

describe('humanizeAttributeName', () => {
    it('splits camelCase, snake_case, and kebab-case into Title Case', () => {
        expect(humanizeAttributeName('numberOfTags')).toBe('Number Of Tags');
        expect(humanizeAttributeName('show_counts')).toBe('Show Counts');
        expect(humanizeAttributeName('largest-font-size')).toBe('Largest Font Size');
    });

    it('falls back to the raw name when it has no word characters', () => {
        expect(humanizeAttributeName('___')).toBe('___');
    });
});

describe('resolveAttributeControl inference', () => {
    it('maps boolean → toggle', () => {
        expect(resolveAttributeControl('showCounts', { type: 'boolean' })).toEqual({
            kind: 'toggle',
            name: 'showCounts',
            label: 'Show Counts',
            help: undefined,
        });
    });

    it('maps string → text', () => {
        expect(resolveAttributeControl('heading', { type: 'string' })?.kind).toBe('text');
    });

    it('maps a plain number → number', () => {
        expect(resolveAttributeControl('count', { type: 'number' })?.kind).toBe('number');
        expect(resolveAttributeControl('count', { type: 'integer' })?.kind).toBe('number');
    });

    it('maps a bounded number (apControl min+max) → range', () => {
        const control = resolveAttributeControl('count', {
            type: 'number',
            apControl: { min: 1, max: 100, step: 5 },
        });

        expect(control).toEqual({
            kind: 'range',
            name: 'count',
            label: 'Count',
            help: undefined,
            min: 1,
            max: 100,
            step: 5,
        });
    });

    it('maps an enum → select with humanized option labels', () => {
        const control = resolveAttributeControl('align', {
            type: 'string',
            enum: ['left', 'center', 'right'],
        });

        expect(control?.kind).toBe('select');
        expect(control && 'options' in control ? control.options : []).toEqual([
            { label: 'Left', value: 'left' },
            { label: 'Center', value: 'center' },
            { label: 'Right', value: 'right' },
        ]);
    });
});

describe('resolveAttributeControl overrides + exclusions', () => {
    it('honours an explicit apControl.control over the inferred type', () => {
        const control = resolveAttributeControl('body', {
            type: 'string',
            apControl: { control: 'textarea', label: 'Body copy', help: 'Shown below the title.' },
        });

        expect(control).toEqual({
            kind: 'textarea',
            name: 'body',
            label: 'Body copy',
            help: 'Shown below the title.',
        });
    });

    it('uses apControl.options for a select when provided', () => {
        const control = resolveAttributeControl('size', {
            type: 'string',
            apControl: {
                control: 'select',
                options: [
                    { label: 'Small', value: 'sm' },
                    { label: 'Large', value: 'lg' },
                ],
            },
        });

        expect(control?.kind).toBe('select');
        expect(control && 'options' in control ? control.options : []).toEqual([
            { label: 'Small', value: 'sm' },
            { label: 'Large', value: 'lg' },
        ]);
    });

    it('falls back to number when apControl.control=range lacks bounds', () => {
        expect(
            resolveAttributeControl('count', { type: 'number', apControl: { control: 'range' } })?.kind
        ).toBe('number');
    });

    it('hides an attribute with apControl:false', () => {
        expect(resolveAttributeControl('internal', { type: 'string', apControl: false })).toBeNull();
    });

    it('skips content-sourced attributes', () => {
        expect(resolveAttributeControl('content', { type: 'string', source: 'html' })).toBeNull();
    });

    it('returns null for a type with no safe generic control', () => {
        expect(resolveAttributeControl('items', { type: 'array' })).toBeNull();
        expect(resolveAttributeControl('meta', { type: 'object' })).toBeNull();
    });

    it('returns null for an empty attribute name', () => {
        expect(resolveAttributeControl('', { type: 'string' })).toBeNull();
    });
});

describe('resolveAttributeControls', () => {
    it('preserves schema key order and drops non-control attributes', () => {
        const attributes: Record<string, AttributeSchema> = {
            title: { type: 'string' },
            content: { type: 'string', source: 'html' },
            count: { type: 'number' },
            hidden: { type: 'string', apControl: false },
            tags: { type: 'array' },
        };

        expect(resolveAttributeControls(attributes).map((control) => control.name)).toEqual([
            'title',
            'count',
        ]);
    });

    it('returns an empty list for missing or non-object schemas', () => {
        expect(resolveAttributeControls(undefined)).toEqual([]);
        // @ts-expect-error - exercising the runtime guard
        expect(resolveAttributeControls(null)).toEqual([]);
    });
});
