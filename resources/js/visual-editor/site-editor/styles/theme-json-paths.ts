/**
 * theme.json helpers.
 *
 * The global-styles payload is a deeply-nested theme.json-shaped object
 * (`settings.color.palette`, `styles.elements.link.color.text`, …).
 * Panels poke at individual leaves without wanting to rebuild the whole
 * object tree by hand — these helpers do the lens-style set / get /
 * unset work in one place so each panel stays focused on UX rather than
 * deep-object plumbing.
 *
 * Per plan §4.2 the schema is pinned (version 3); the helpers only
 * enforce shape at the leaf they touch and leave higher-level keys
 * alone, so a schema bump that adds a new top-level key (e.g.
 * `settings.spacing`) doesn't need to cascade through these utilities.
 */

export type ThemeJsonPath = ReadonlyArray<string>;

export type JsonShape = Record<string, unknown>;

function isPlainObject(value: unknown): value is JsonShape {
    return (
        value !== null &&
        typeof value === 'object' &&
        !Array.isArray(value) &&
        Object.prototype.toString.call(value) === '[object Object]'
    );
}

/**
 * Safe deep read. Returns `undefined` for any miss along the path —
 * panels use the base payload as a fallback when the user record hasn't
 * overridden a given key.
 */
export function readPath(source: unknown, path: ThemeJsonPath): unknown {
    let cursor: unknown = source;

    for (const segment of path) {
        if (!isPlainObject(cursor)) {
            return undefined;
        }

        cursor = cursor[segment];
    }

    return cursor;
}

/**
 * Returns a shallow-cloned object with the value at `path` set to `value`.
 * Creates intermediate objects as needed. Never mutates the input.
 */
export function writePath(
    source: unknown,
    path: ThemeJsonPath,
    value: unknown
): JsonShape {
    const root: JsonShape = isPlainObject(source) ? { ...source } : {};

    if (path.length === 0) {
        return root;
    }

    let cursor: JsonShape = root;

    for (let index = 0; index < path.length - 1; index += 1) {
        const segment = path[index];

        if (segment === undefined) {
            continue;
        }

        const existing = cursor[segment];
        const next: JsonShape = isPlainObject(existing) ? { ...existing } : {};
        cursor[segment] = next;
        cursor = next;
    }

    const tail = path[path.length - 1];

    if (tail !== undefined) {
        cursor[tail] = value;
    }

    return root;
}

/**
 * Deletes the key at `path`; prunes newly-empty ancestor objects on the
 * way back so a user record doesn't accumulate empty `{ color: {} }`-style
 * husks as they reset customizations. Never mutates the input.
 */
export function unsetPath(source: unknown, path: ThemeJsonPath): JsonShape {
    const root: JsonShape = isPlainObject(source) ? { ...source } : {};

    if (path.length === 0) {
        return root;
    }

    // Walk down cloning each node we touch so the mutation is isolated.
    const stack: JsonShape[] = [root];
    let cursor: JsonShape = root;

    for (let index = 0; index < path.length - 1; index += 1) {
        const segment = path[index];

        if (segment === undefined) {
            continue;
        }

        const existing = cursor[segment];

        if (!isPlainObject(existing)) {
            return root;
        }

        const next: JsonShape = { ...existing };
        cursor[segment] = next;
        stack.push(next);
        cursor = next;
    }

    const tail = path[path.length - 1];

    if (tail === undefined) {
        return root;
    }

    if (tail in cursor) {
        delete cursor[tail];
    }

    // Prune empty ancestors. Leave the root object in place even when
    // empty so `settings` / `styles` keys are always present on the
    // envelope.
    for (let index = stack.length - 1; index > 0; index -= 1) {
        const node = stack[index];
        const parent = stack[index - 1];

        if (
            node !== undefined &&
            parent !== undefined &&
            Object.keys(node).length === 0
        ) {
            const parentKey = path[index - 1];

            if (parentKey !== undefined) {
                delete parent[parentKey];
            }
        } else {
            break;
        }
    }

    return root;
}

/**
 * True when `userValue` differs from `baseValue`. Strings / numbers /
 * booleans compare directly; objects and arrays compare by JSON round-trip
 * so palette-entry reorders and value changes both register as dirty.
 *
 * `undefined` user values are not customized — they fall through to the
 * theme default.
 */
export function isCustomized(userValue: unknown, baseValue: unknown): boolean {
    if (userValue === undefined) {
        return false;
    }

    if (userValue === baseValue) {
        return false;
    }

    if (
        (userValue === null || typeof userValue !== 'object') &&
        (baseValue === null || typeof baseValue !== 'object')
    ) {
        return userValue !== baseValue;
    }

    try {
        return JSON.stringify(userValue) !== JSON.stringify(baseValue);
    } catch {
        return userValue !== baseValue;
    }
}
