/**
 * Minimal WP block-comment parser scoped to navigation content
 * (Keystone #48).
 *
 * The shim's `useEntityBlockEditor` needs to hydrate an entity's
 * pre-parsed block tree from its content payload. For most entities
 * the server ships `content.blocks` as an array; for `wp_navigation`
 * — and after `flattenRawProperties` collapses `content: {raw,
 * blocks}` down to a plain string — we only have the serialized
 * block-comment markup. This module parses just enough of that
 * markup to rebuild the `core/navigation-link` /
 * `core/navigation-submenu` tree the canvas expects.
 *
 * We avoid importing `@wordpress/blocks`'s full `parse()` here for
 * two reasons:
 *  1. `@wordpress/blocks` ships JSON imports that break vitest's
 *     module resolver, which would block the shim's own test suite.
 *  2. Navigation menus are the only content shape that reaches this
 *     path — a focused parser keeps the shim's runtime dependencies
 *     small and the failure mode predictable.
 *
 * Mirrors `MenuItemBlockBridge::rawToBlocks` (PHP) so a server-
 * shipped `content.raw` round-trips into the same tree the
 * editor reads in either direction.
 */

export interface ParsedBlock {
    name: string;
    attributes: Record<string, unknown>;
    innerBlocks: ParsedBlock[];
}

interface OpenToken {
    kind: 'open';
    name: string;
    attributes: Record<string, unknown>;
}

interface SelfCloseToken {
    kind: 'selfclose';
    name: string;
    attributes: Record<string, unknown>;
}

interface CloseToken {
    kind: 'close';
    name: string;
}

type Token = OpenToken | SelfCloseToken | CloseToken;

const SUPPORTED_BLOCK_NAMES = new Set<string>([
    'core/navigation-link',
    'core/navigation-submenu',
]);

/**
 * Parse a WP block-comment string carrying a navigation tree into a
 * flat array of blocks. Drops any block name that isn't a navigation
 * link / submenu — the shim's only consumer of this output is the
 * `core/navigation` block, and the editor's renderer can't do
 * anything sensible with a stray paragraph inside the nav tree.
 */
export function parseNavigationContent(raw: string): ParsedBlock[] {
    const trimmed = raw.trim();

    if (trimmed === '') {
        return [];
    }

    const tokens = tokenize(trimmed);
    const cursor = { index: 0 };

    return consumeChildren(tokens, cursor);
}

function tokenize(raw: string): Token[] {
    const tokens: Token[] = [];
    let pos = 0;
    const len = raw.length;

    while (pos < len) {
        const openAt = raw.indexOf('<!--', pos);

        if (openAt < 0) {
            break;
        }

        const closeAt = raw.indexOf('-->', openAt);

        if (closeAt < 0) {
            break;
        }

        let body = raw.slice(openAt + 4, closeAt).trim();
        pos = closeAt + 3;

        let selfClose = false;

        if (body.endsWith('/')) {
            selfClose = true;
            body = body.slice(0, -1).trimEnd();
        }

        if (body.startsWith('/wp:')) {
            tokens.push({
                kind: 'close',
                name: 'core/' + body.slice(4).trim(),
            });

            continue;
        }

        if (!body.startsWith('wp:')) {
            continue;
        }

        const tail = body.slice(3);
        const nameEnd = firstSplitIndex(tail);
        const name = 'core/' + tail.slice(0, nameEnd);
        const attrsStr = tail.slice(nameEnd).trim();

        let attributes: Record<string, unknown> = {};

        if (attrsStr !== '') {
            try {
                const decoded = JSON.parse(attrsStr) as unknown;

                if (decoded !== null && typeof decoded === 'object' && !Array.isArray(decoded)) {
                    attributes = decoded as Record<string, unknown>;
                }
            } catch {
                // Malformed JSON in a block comment is a server-side
                // bug; surface as "no attributes" rather than crashing
                // the editor.
            }
        }

        tokens.push(
            selfClose
                ? { kind: 'selfclose', name, attributes }
                : { kind: 'open', name, attributes }
        );
    }

    return tokens;
}

function firstSplitIndex(value: string): number {
    for (let i = 0; i < value.length; i += 1) {
        const ch = value[i];

        if (
            ch === ' ' ||
            ch === '\t' ||
            ch === '\n' ||
            ch === '\r' ||
            ch === '\f' ||
            ch === '\v' ||
            ch === '{'
        ) {
            return i;
        }
    }

    return value.length;
}

function consumeChildren(tokens: Token[], cursor: { index: number }): ParsedBlock[] {
    const children: ParsedBlock[] = [];

    while (cursor.index < tokens.length) {
        const token = tokens[cursor.index]!;

        if (token.kind === 'close') {
            return children;
        }

        if (!SUPPORTED_BLOCK_NAMES.has(token.name)) {
            // Skip unsupported block plus its matching close if any —
            // matches the PHP parser's defensive drop.
            cursor.index += 1;

            if (token.kind === 'open') {
                consumeChildren(tokens, cursor);
                cursor.index += 1;
            }

            continue;
        }

        if (token.kind === 'selfclose') {
            children.push({
                name: token.name,
                attributes: token.attributes,
                innerBlocks: [],
            });
            cursor.index += 1;

            continue;
        }

        // `open` — recurse for inner blocks, then skip the matching close.
        cursor.index += 1;
        const inner = consumeChildren(tokens, cursor);
        cursor.index += 1;

        children.push({
            name: token.name,
            attributes: token.attributes,
            innerBlocks: inner,
        });
    }

    return children;
}
