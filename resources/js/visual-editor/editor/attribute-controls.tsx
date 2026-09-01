/**
 * Attribute-schema → inspector-control generation for server-rendered
 * third-party blocks (#766).
 *
 * A block registered from PHP (via `VisualEditor::registerServerBlock()`)
 * ships no client `edit` component, only a block.json-shaped `attributes`
 * schema. To give it an editing experience with zero client build, the
 * editor infers an inspector control per attribute from that schema. The
 * inference is deliberately conservative — the four primitive JSON types the
 * bundled `@wordpress/components` covers cleanly — with an explicit
 * `apControl` escape hatch on any attribute for hosts that want to pin the
 * control, its label, help text, range bounds, or select options.
 *
 * The mapping (`resolveAttributeControl`) is a pure function so it can be
 * unit-tested without React; `AttributeControl` renders one resolved
 * descriptor.
 */

import type { ReactElement } from 'react';
import {
    RangeControl,
    SelectControl,
    TextareaControl,
    TextControl,
    ToggleControl,
} from '@wordpress/components';

/**
 * A single attribute's block.json schema entry, plus the optional
 * `apControl` hint this package reads.
 */
export interface AttributeSchema {
    readonly type?: unknown;
    readonly enum?: unknown;
    readonly default?: unknown;
    /**
     * Present on content-sourced attributes (`html`, `text`, `attribute`,
     * …). Such attributes are edited in the canvas, never the inspector, so
     * a sourced attribute is skipped.
     */
    readonly source?: unknown;
    /**
     * Host override. `false` hides the attribute from the inspector; an
     * object pins the control and its presentation.
     */
    readonly apControl?: ApControlHint | false;
    readonly [key: string]: unknown;
}

/** Explicit per-attribute control override read from block.json. */
export interface ApControlHint {
    readonly control?: 'text' | 'textarea' | 'number' | 'range' | 'toggle' | 'select';
    readonly label?: string;
    readonly help?: string;
    readonly min?: number;
    readonly max?: number;
    readonly step?: number;
    readonly options?: ReadonlyArray<{ readonly label: string; readonly value: string }>;
}

/** The resolved, render-ready description of one attribute's control. */
export type AttributeControlDescriptor =
    | { kind: 'text'; name: string; label: string; help?: string }
    | { kind: 'textarea'; name: string; label: string; help?: string }
    | { kind: 'toggle'; name: string; label: string; help?: string }
    | {
          kind: 'number';
          name: string;
          label: string;
          help?: string;
          min?: number;
          max?: number;
          step?: number;
      }
    | {
          kind: 'range';
          name: string;
          label: string;
          help?: string;
          min: number;
          max: number;
          step?: number;
      }
    | {
          kind: 'select';
          name: string;
          label: string;
          help?: string;
          options: ReadonlyArray<{ label: string; value: string }>;
      };

/**
 * Turn a snake/camel/kebab attribute name into a human label:
 * `numberOfTags` → `Number Of Tags`, `show_counts` → `Show Counts`.
 */
export function humanizeAttributeName(name: string): string {
    const spaced = name
        .replace(/[_-]+/g, ' ')
        .replace(/([a-z0-9])([A-Z])/g, '$1 $2')
        .trim();

    if (spaced === '') {
        return name;
    }

    return spaced
        .split(/\s+/)
        .map((word) => word.charAt(0).toUpperCase() + word.slice(1))
        .join(' ');
}

function toOptions(
    values: unknown
): ReadonlyArray<{ label: string; value: string }> | null {
    if (!Array.isArray(values) || values.length === 0) {
        return null;
    }

    const options: Array<{ label: string; value: string }> = [];

    for (const value of values) {
        if (typeof value === 'string' || typeof value === 'number' || typeof value === 'boolean') {
            const stringValue = String(value);
            options.push({ label: humanizeAttributeName(stringValue), value: stringValue });
        }
    }

    return options.length > 0 ? options : null;
}

function numberBound(value: unknown): number | undefined {
    return typeof value === 'number' && Number.isFinite(value) ? value : undefined;
}

/**
 * Resolve the inspector control for one attribute, or `null` when the
 * attribute should not surface in the inspector (content-sourced, hidden via
 * `apControl: false`, or a type — array/object — with no generic control).
 */
export function resolveAttributeControl(
    name: string,
    schema: AttributeSchema
): AttributeControlDescriptor | null {
    if (name === '' || schema.apControl === false || schema.source !== undefined) {
        return null;
    }

    const hint: ApControlHint = typeof schema.apControl === 'object' && schema.apControl !== null
        ? schema.apControl
        : {};
    const label = typeof hint.label === 'string' && hint.label !== '' ? hint.label : humanizeAttributeName(name);
    const help = typeof hint.help === 'string' && hint.help !== '' ? hint.help : undefined;
    const min = numberBound(hint.min);
    const max = numberBound(hint.max);
    const step = numberBound(hint.step);

    // Explicit control wins over inference.
    switch (hint.control) {
        case 'text':
            return { kind: 'text', name, label, help };
        case 'textarea':
            return { kind: 'textarea', name, label, help };
        case 'toggle':
            return { kind: 'toggle', name, label, help };
        case 'number':
            return { kind: 'number', name, label, help, min, max, step };
        case 'range':
            if (min !== undefined && max !== undefined) {
                return { kind: 'range', name, label, help, min, max, step };
            }
            return { kind: 'number', name, label, help, min, max, step };
        case 'select': {
            const options = hint.options && hint.options.length > 0 ? hint.options : toOptions(schema.enum);
            if (options && options.length > 0) {
                return { kind: 'select', name, label, help, options };
            }
            return { kind: 'text', name, label, help };
        }
    }

    // Inference from the JSON type + enum.
    const enumOptions = toOptions(schema.enum);
    if (enumOptions) {
        return { kind: 'select', name, label, help, options: enumOptions };
    }

    switch (schema.type) {
        case 'boolean':
            return { kind: 'toggle', name, label, help };
        case 'number':
        case 'integer':
            if (min !== undefined && max !== undefined) {
                return { kind: 'range', name, label, help, min, max, step };
            }
            return { kind: 'number', name, label, help, min, max, step };
        case 'string':
            return { kind: 'text', name, label, help };
        default:
            // array / object / untyped — no safe generic control.
            return null;
    }
}

/**
 * Resolve every inspector control for an `attributes` schema, preserving the
 * schema's own key order so the inspector is deterministic.
 */
export function resolveAttributeControls(
    attributes: Record<string, AttributeSchema> | undefined
): ReadonlyArray<AttributeControlDescriptor> {
    if (!attributes || typeof attributes !== 'object') {
        return [];
    }

    const descriptors: AttributeControlDescriptor[] = [];

    for (const [name, schema] of Object.entries(attributes)) {
        if (schema === null || typeof schema !== 'object') {
            continue;
        }

        const descriptor = resolveAttributeControl(name, schema as AttributeSchema);

        if (descriptor !== null) {
            descriptors.push(descriptor);
        }
    }

    return descriptors;
}

interface AttributeControlProps {
    readonly descriptor: AttributeControlDescriptor;
    readonly value: unknown;
    readonly onChange: (value: unknown) => void;
}

/**
 * Render one resolved descriptor as its `@wordpress/components` control.
 *
 * The `__next*` props opt the controls into the current design-system
 * sizing/spacing so a synthesized inspector matches a hand-authored one.
 */
export function AttributeControl({
    descriptor,
    value,
    onChange,
}: AttributeControlProps): ReactElement {
    switch (descriptor.kind) {
        case 'toggle':
            return (
                <ToggleControl
                    // @ts-expect-error - upstream prop
                    __nextHasNoMarginBottom
                    label={descriptor.label}
                    help={descriptor.help}
                    checked={Boolean(value)}
                    onChange={(next: boolean) => onChange(next)}
                />
            );
        case 'textarea':
            return (
                <TextareaControl
                    // @ts-expect-error - upstream prop
                    __nextHasNoMarginBottom
                    label={descriptor.label}
                    help={descriptor.help}
                    value={typeof value === 'string' ? value : ''}
                    onChange={(next: string) => onChange(next)}
                />
            );
        case 'number':
            return (
                <TextControl
                    // @ts-expect-error - upstream prop
                    __nextHasNoMarginBottom
                    __next40pxDefaultSize
                    type="number"
                    label={descriptor.label}
                    help={descriptor.help}
                    min={descriptor.min}
                    max={descriptor.max}
                    step={descriptor.step}
                    value={typeof value === 'number' ? String(value) : typeof value === 'string' ? value : ''}
                    onChange={(next: string) => {
                        if (next === '') {
                            onChange(undefined);
                            return;
                        }
                        const parsed = Number(next);
                        onChange(Number.isNaN(parsed) ? next : parsed);
                    }}
                />
            );
        case 'range':
            return (
                <RangeControl
                    // @ts-expect-error - upstream prop
                    __nextHasNoMarginBottom
                    __next40pxDefaultSize
                    label={descriptor.label}
                    help={descriptor.help}
                    min={descriptor.min}
                    max={descriptor.max}
                    step={descriptor.step}
                    value={typeof value === 'number' ? value : undefined}
                    onChange={(next?: number) => onChange(next)}
                />
            );
        case 'select':
            return (
                <SelectControl
                    // @ts-expect-error - upstream prop
                    __nextHasNoMarginBottom
                    __next40pxDefaultSize
                    label={descriptor.label}
                    help={descriptor.help}
                    value={typeof value === 'string' ? value : value === undefined ? '' : String(value)}
                    options={descriptor.options as Array<{ label: string; value: string }>}
                    onChange={(next: string) => onChange(next)}
                />
            );
        case 'text':
        default:
            return (
                <TextControl
                    // @ts-expect-error - upstream prop
                    __nextHasNoMarginBottom
                    __next40pxDefaultSize
                    label={descriptor.label}
                    help={descriptor.help}
                    value={typeof value === 'string' ? value : value === undefined ? '' : String(value)}
                    onChange={(next: string) => onChange(next)}
                />
            );
    }
}
