/**
 * Current-page detection for navigation links and submenus (#975).
 *
 * Mirrors `ArtisanPackUI\VisualEditorRendererBlade\Support\NavigationCurrentState`
 * step for step: a link whose URL is the page being viewed (same path,
 * ignoring a trailing slash, the query and the fragment; an absolute
 * link must also name the same host and port) is **current** and
 * renders `current-menu-item` + `aria-current="page"`; a link to a parent
 * path (`/blog` while on `/blog/hello`) is an **ancestor** and renders
 * `current-menu-ancestor`. `/` is never an ancestor. A submenu that leads
 * to the current page also gets `current-menu-ancestor`.
 *
 * The JS renderers have no request, so the host passes `currentUrl` to
 * `BlockTree` / `Template` and {@link stampNavigationCurrent} pre-walks
 * the tree, stamping private `_veNavCurrent` / `_veNavHasCurrentChild`
 * attributes the navigation renderers read. Pass an absolute URL: with a
 * relative one, only relative menu links can match.
 *
 * The shared cases live in
 * `packages/renderer-markup-parity/navigation-current-cases.json`. The
 * mirror lives at the matching path in the React renderer.
 *
 * @package @artisanpack-ui/visual-editor-renderer-vue
 * @since 1.15.2
 */

import type { Block } from '../types';
import { phpTrim } from './attributes';
import { safeUrl } from './urlSanitizer';

export type NavigationCurrentState = 'current' | 'ancestor' | '';

/** Blocks whose `url` takes part in current-page detection. */
export const NAVIGATION_LINK_BLOCKS = ['core/navigation-link', 'core/navigation-submenu'];

interface ParsedUrl {
    host: string | null;
    port: string;
    path: string;
}

/**
 * Split a URL into the parts matching compares, or `null` when it can't
 * match. Mirrors `NavigationCurrentState::parse()`.
 */
function parse(input: string): ParsedUrl | null {
    let url = phpTrim(input);

    for (const delimiter of ['#', '?']) {
        const position = url.indexOf(delimiter);

        if (position !== -1) {
            url = url.slice(0, position);
        }
    }

    if (url === '') {
        return null;
    }

    let host: string | null = null;
    let port = '';

    const scheme = /^([a-zA-Z][a-zA-Z0-9+.-]*):/.exec(url);

    if (scheme !== null) {
        const name = scheme[1].toLowerCase();

        if (name !== 'http' && name !== 'https') {
            return null;
        }

        url = url.slice(scheme[0].length);

        if (!url.startsWith('//')) {
            return null;
        }
    }

    if (url.startsWith('//')) {
        const rest = url.slice(2);
        const slash = rest.indexOf('/');
        let authority = slash === -1 ? rest : rest.slice(0, slash);
        url = slash === -1 ? '' : rest.slice(slash);
        const at = authority.lastIndexOf('@');

        if (at !== -1) {
            authority = authority.slice(at + 1);
        }

        authority = authority.toLowerCase();
        const colon = authority.lastIndexOf(':');

        if (colon !== -1 && !authority.slice(colon).includes(']')) {
            port = authority.slice(colon + 1);
            authority = authority.slice(0, colon);
        }

        if (port === '80' || port === '443') {
            port = '';
        }

        if (authority === '') {
            return null;
        }

        host = authority;
    } else if (!url.startsWith('/')) {
        return null;
    }

    const path = url.replace(/\/+$/, '');

    return { host, port, path: path === '' ? '/' : path };
}

/**
 * Match a link URL against the current URL. Mirrors
 * `NavigationCurrentState::match()`.
 */
export function matchNavigationUrl(linkUrl: string, currentUrl: string): NavigationCurrentState {
    const link = parse(linkUrl);
    const current = parse(currentUrl);

    if (link === null || current === null) {
        return '';
    }

    if (link.host !== null) {
        if (current.host === null || link.host !== current.host || link.port !== current.port) {
            return '';
        }
    }

    if (link.path === current.path) {
        return 'current';
    }

    if (link.path !== '/' && current.path.startsWith(`${link.path}/`)) {
        return 'ancestor';
    }

    return '';
}

function attributesOf(block: Block): Record<string, unknown> {
    return block.attributes !== null && typeof block.attributes === 'object' && !Array.isArray(block.attributes)
        ? (block.attributes as Record<string, unknown>)
        : {};
}

/**
 * Stamp `_veNavCurrent` on every navigation link / submenu matching
 * `currentUrl`, and `_veNavHasCurrentChild` on every submenu with a
 * matching link below it. Every link block is stamped, even without a
 * `currentUrl` (nothing matches then), so a stored `_veNavCurrent` can
 * never mark a link current on its own.
 */
export function stampNavigationCurrent(tree: Block[], currentUrl: string | null | undefined): Block[] {
    const url = typeof currentUrl === 'string' ? currentUrl : '';

    function walk(nodes: Block[]): { nodes: Block[]; matched: boolean } {
        let matched = false;

        const mapped = nodes.map((block) => {
            const children = Array.isArray(block.innerBlocks) ? walk(block.innerBlocks) : null;
            const name = typeof block.name === 'string' ? block.name : '';

            if (children !== null && children.matched) {
                matched = true;
            }

            if (!NAVIGATION_LINK_BLOCKS.includes(name)) {
                return children === null ? block : { ...block, innerBlocks: children.nodes };
            }

            const attributes = attributesOf(block);
            const state = matchNavigationUrl(safeUrl(attributes.url), url);

            if (state !== '') {
                matched = true;
            }

            const stamped: Record<string, unknown> = { ...attributes, _veNavCurrent: state };

            if (name === 'core/navigation-submenu') {
                stamped._veNavHasCurrentChild = children !== null && children.matched;
            }

            return {
                ...block,
                attributes: stamped,
                ...(children === null ? {} : { innerBlocks: children.nodes }),
            };
        });

        return { nodes: mapped, matched };
    }

    return walk(tree).nodes;
}

/**
 * The `<li>` classes a navigation link / submenu earns from the stamped
 * state, in the order the Blade partials print them.
 */
export function navigationCurrentClasses(attributes: Record<string, unknown>, isSubmenu: boolean): string[] {
    const state = attributes._veNavCurrent;
    const classes: string[] = [];

    if (state === 'current') {
        classes.push('current-menu-item');
    }

    if (state === 'ancestor' || (isSubmenu && attributes._veNavHasCurrentChild === true)) {
        classes.push('current-menu-ancestor');
    }

    return classes;
}
