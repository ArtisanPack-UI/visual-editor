/**
 * Canonical markup serializer for the Blade-vs-JS parity check (#704).
 *
 * Parses rendered HTML and re-emits it as an indented, deterministic tree
 * so the React / Vue renderers' output can be compared against the golden
 * files the Blade suite writes, without tripping over incidental
 * formatting differences between the three renderers.
 *
 * Normalization is deliberately narrow — see README.md. In short:
 * whitespace and attribute order are normalized; element names, class
 * tokens and attribute values are not.
 *
 * The PHP twin of this module lives at
 * packages/visual-editor-renderer-blade/tests/Support/CanonicalMarkup.php.
 * Any change here must be mirrored there or the two suites stop agreeing.
 */

const VOID_ELEMENTS = new Set([
    'area',
    'base',
    'br',
    'col',
    'embed',
    'hr',
    'img',
    'input',
    'link',
    'meta',
    'param',
    'source',
    'track',
    'wbr',
]);

/**
 * Strips the renderer-injected `<style data-ve-*>` blocks.
 *
 * This check covers block markup only. The layout baseline / global-styles
 * CSS is a known, documented divergence between Blade (which emits it from
 * `ThemeJsonTokensCompiler::compileLayoutRules()`, gated on theme.json
 * layout sizes) and the JS renderers (which ship `LAYOUT_BASELINE_CSS`), so
 * comparing it here would only encode that difference twice.
 */
export function stripRendererStyleTags(html: string): string {
    return html.replace(/<style\s+data-ve-[^>]*>[\s\S]*?<\/style>/g, '');
}

/**
 * Class-token regexes declared as known renderer divergences.
 */
let dropClassPatterns: RegExp[] = [];

/**
 * Serializes rendered HTML into the canonical comparison form.
 *
 * `dropClassPatternSources` carries regex sources for class tokens that
 * are declared, documented divergences (see the `knownDivergences` block
 * in fixtures.json). Matching tokens are dropped from every class
 * attribute before comparison.
 */
export function canonicalizeHtml(
    html: string,
    dropClassPatternSources: string[] = []
): string {
    dropClassPatterns = dropClassPatternSources.map((source) => new RegExp(source));

    const root = document.createElement('div');

    root.innerHTML = stripRendererStyleTags(html);

    const lines: string[] = [];

    serializeChildren(root, 0, lines);

    return lines.join('\n');
}

function serializeChildren(parent: Node, depth: number, lines: string[]): void {
    const kept: Array<{ node: Node; text: string | null }> = [];

    parent.childNodes.forEach((child) => {
        if (child.nodeType === 3 /* TEXT_NODE */) {
            const text = collapseWhitespace(child.textContent ?? '');

            if (text.trim() === '') {
                return;
            }

            kept.push({ node: child, text });

            return;
        }

        if (child.nodeType !== 1 /* ELEMENT_NODE */) {
            // Comments carry no rendered markup; Vue's SSR fragment
            // markers land here too.
            return;
        }

        kept.push({ node: child, text: null });
    });

    const lastIndex = kept.length - 1;

    kept.forEach((entry, index) => {
        if (entry.text !== null) {
            let text = entry.text;

            if (index === 0) {
                text = text.replace(/^ +/, '');
            }

            if (index === lastIndex) {
                text = text.replace(/ +$/, '');
            }

            if (text === '') {
                return;
            }

            lines.push(`${'  '.repeat(depth)}"${escape(text)}"`);

            return;
        }

        serializeElement(entry.node as Element, depth, lines);
    });
}

function serializeElement(element: Element, depth: number, lines: string[]): void {
    const indent = '  '.repeat(depth);
    const tag = element.tagName.toLowerCase();

    const attributes = Array.from(element.attributes)
        .map((attribute) => ({
            name: attribute.name.toLowerCase(),
            value: canonicalAttributeValue(
                attribute.name.toLowerCase(),
                attribute.value
            ),
        }))
        .sort((a, b) => (a.name < b.name ? -1 : a.name > b.name ? 1 : 0));

    const serialized = attributes
        .map(({ name, value }) => ` ${name}="${escape(value)}"`)
        .join('');

    if (VOID_ELEMENTS.has(tag)) {
        lines.push(`${indent}<${tag}${serialized} />`);

        return;
    }

    lines.push(`${indent}<${tag}${serialized}>`);

    serializeChildren(element, depth + 1, lines);

    lines.push(`${indent}</${tag}>`);
}

/**
 * Applies the narrow, per-attribute whitespace normalization.
 *
 * `class` gets its whitespace collapsed (token order and spelling are
 * preserved). `style` gets each declaration trimmed and re-joined with
 * `; ` so Vue's trailing semicolon and Blade's indentation don't count as
 * divergence. Every other attribute value is passed through as-is.
 */
function canonicalAttributeValue(name: string, value: string): string {
    if (name === 'class') {
        return value
            .trim()
            .split(/\s+/)
            .filter(
                (token) =>
                    token !== '' &&
                    !dropClassPatterns.some((pattern) => pattern.test(token))
            )
            .join(' ');
    }

    if (name === 'style') {
        return value
            .split(';')
            .map((declaration) => collapseWhitespace(declaration).trim())
            .filter((declaration) => declaration !== '')
            .map((declaration) => {
                // Whitespace around the property/value colon is
                // insignificant CSS; React serializes `flex-basis:60%`
                // where Blade writes `flex-basis: 60%`.
                const separator = declaration.indexOf(':');

                return separator === -1
                    ? declaration
                    : `${declaration.slice(0, separator).trim()}: ${declaration
                          .slice(separator + 1)
                          .trim()}`;
            })
            .join('; ');
    }

    return value;
}

function collapseWhitespace(value: string): string {
    return value.replace(/\s+/g, ' ');
}

function escape(value: string): string {
    return value.replace(/\\/g, '\\\\').replace(/"/g, '\\"');
}
