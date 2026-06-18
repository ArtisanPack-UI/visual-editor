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
        const normalized = value.trim().toLowerCase();

        return normalized !== '' && normalized !== 'false' && normalized !== '0';
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

/**
 * Tailwind v4 default breakpoint keys recognised by the post-variant
 * span emitter (#592). Anything outside this list is dropped silently
 * so a malformed save can't produce CSS classes the stylesheet has no
 * matching rule for.
 */
const POST_TEMPLATE_ITEM_SPAN_BREAKPOINTS = new Set<string>([
    'base',
    'sm',
    'md',
    'lg',
    'xl',
    '2xl',
]);

const POST_TEMPLATE_ITEM_SPAN_MIN = 1;
const POST_TEMPLATE_ITEM_SPAN_MAX = 12;

function clampSpan(value: unknown): number | null {
    if (typeof value === 'number' && Number.isFinite(value)) {
        const trimmed = Math.trunc(value);

        if (trimmed < POST_TEMPLATE_ITEM_SPAN_MIN) {
            return POST_TEMPLATE_ITEM_SPAN_MIN;
        }

        if (trimmed > POST_TEMPLATE_ITEM_SPAN_MAX) {
            return POST_TEMPLATE_ITEM_SPAN_MAX;
        }

        return trimmed;
    }

    if (typeof value === 'string' && value.trim() !== '') {
        const parsed = Number.parseInt(value, 10);

        if (Number.isFinite(parsed)) {
            return clampSpan(parsed);
        }
    }

    return null;
}

/**
 * Build the `ap-post-span-N-{bp}-{columns,row}` class list for a
 * post-template-item from the `_resolvedGridSpan` attribute the
 * server-side `QueryInliner` (PHP) or its client-side mirror stamps
 * onto each iteration wrapper when (a) a variant matched and (b) the
 * post-template's layout is grid.
 *
 * The shape mirrors the PHP `resolveVariantSpans()` output:
 *
 *     { columns: { base: 2, md: 3 }, rows: { base: 1 } }
 *
 * Unknown breakpoint keys are dropped so the public CSS bundle stays
 * the only source of truth for the rule set. Returns an empty array
 * when the attribute is absent or empty so the caller can spread it
 * unconditionally.
 */
export function postTemplateItemSpanClasses(value: unknown): string[] {
    if (value === null || value === undefined || typeof value !== 'object') {
        return [];
    }

    const record = value as Record<string, unknown>;

    return [
        ...buildSpanClasses(record.columns, 'columns'),
        ...buildSpanClasses(record.rows, 'row'),
    ];
}

function buildSpanClasses(overrides: unknown, suffix: 'columns' | 'row'): string[] {
    if (overrides === null || overrides === undefined || typeof overrides !== 'object') {
        return [];
    }

    if (Array.isArray(overrides)) {
        return [];
    }

    const out: string[] = [];

    for (const [bp, raw] of Object.entries(overrides as Record<string, unknown>)) {
        if (!POST_TEMPLATE_ITEM_SPAN_BREAKPOINTS.has(bp)) {
            continue;
        }

        const span = clampSpan(raw);

        if (span === null) {
            continue;
        }

        out.push(`ap-post-span-${span}-${bp}-${suffix}`);
    }

    return out;
}
