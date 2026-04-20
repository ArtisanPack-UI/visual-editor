/**
 * Attribute coercion helpers.
 *
 * Block attributes come off a JSON payload, so every value is `unknown` at
 * runtime. These helpers coerce individual attributes to the shapes the
 * per-block renderers expect while keeping every renderer terse.
 */

export function attrString(value: unknown, fallback = ''): string {
    if (typeof value === 'string') {
        return value;
    }

    if (typeof value === 'number' || typeof value === 'boolean') {
        return String(value);
    }

    return fallback;
}

export function attrBoolean(value: unknown, fallback = false): boolean {
    if (typeof value === 'boolean') {
        return value;
    }

    if (value === null || value === undefined) {
        return fallback;
    }

    if (typeof value === 'string') {
        return value.trim() !== '' && value !== 'false' && value !== '0';
    }

    if (typeof value === 'number') {
        return value !== 0;
    }

    return Boolean(value);
}

export function attrInt(value: unknown, fallback = 0): number {
    if (typeof value === 'number' && Number.isFinite(value)) {
        return Math.trunc(value);
    }

    if (typeof value === 'string' && value.trim() !== '') {
        const parsed = Number.parseInt(value, 10);

        if (Number.isFinite(parsed)) {
            return parsed;
        }
    }

    return fallback;
}

export function attrFloat(value: unknown, fallback = 0): number {
    if (typeof value === 'number' && Number.isFinite(value)) {
        return value;
    }

    if (typeof value === 'string' && value.trim() !== '') {
        const parsed = Number.parseFloat(value);

        if (Number.isFinite(parsed)) {
            return parsed;
        }
    }

    return fallback;
}

export function attrRecord(value: unknown): Record<string, unknown> {
    if (value === null || value === undefined || typeof value !== 'object') {
        return {};
    }

    if (Array.isArray(value)) {
        return {};
    }

    return value as Record<string, unknown>;
}

export function attrArray(value: unknown): unknown[] {
    return Array.isArray(value) ? value : [];
}

export function classList(classes: Array<string | false | null | undefined>): string {
    return classes
        .filter((c): c is string => typeof c === 'string' && c.trim() !== '')
        .map((c) => c.trim())
        .join(' ');
}

export function formatPercent(value: number): string {
    const fixed = value.toFixed(6);
    const trimmed = fixed.replace(/0+$/, '').replace(/\.$/, '');

    return (trimmed === '' ? '0' : trimmed) + '%';
}
