/**
 * Shared style hosts for block-scoped editor-canvas CSS.
 *
 * Several `editor.BlockListBlock` filters (visibility, position, box
 * shadow) emit CSS scoped to one block by a unique class. They used to
 * render that CSS as a `<style>` sibling of the block, which put an
 * extra element into the block list's layout flow and broke
 * `:first-child`, `* + *` and block-gap selectors around it.
 *
 * This module keeps the CSS out of the flow instead: each filter
 * ("channel") owns one `<style data-ap-canvas-styles="<channel>">`
 * element in the `<head>` of the document the block renders in (the
 * post editor's iframe canvas or the site editor's in-tree canvas),
 * holding every mounted block's entry. Entries are removed when their
 * block unmounts, and the element is dropped once it's empty.
 *
 * @package @artisanpack-ui/visual-editor
 * @since 1.13.0
 */

import { useLayoutEffect, useRef } from 'react';

interface StyleHost {
    readonly element: HTMLStyleElement;
    readonly entries: Map<object, string>;
}

/** Channel → document → host. */
const hosts = new Map<string, WeakMap<Document, StyleHost>>();

/** Attribute naming the channel on a host `<style>` element. */
export const CANVAS_STYLE_HOST_ATTRIBUTE = 'data-ap-canvas-styles';

function hostsFor(channel: string): WeakMap<Document, StyleHost> {
    let perDocument = hosts.get(channel);

    if (perDocument === undefined) {
        perDocument = new WeakMap();
        hosts.set(channel, perDocument);
    }

    return perDocument;
}

function findBlockIn(doc: Document, clientId: string): Element | null {
    return (
        doc.getElementById(`block-${clientId}`) ??
        doc.querySelector(`[data-block="${clientId.replace(/["\\]/g, '\\$&')}"]`)
    );
}

/**
 * The document a block renders in: the main document, or the
 * same-origin iframe canvas holding its wrapper. Falls back to the main
 * document when the block can't be found (e.g. not mounted yet).
 *
 * @since 1.13.0
 */
export function findBlockDocument(clientId: string): Document {
    if (typeof document === 'undefined') {
        throw new Error('findBlockDocument() needs a DOM.');
    }

    if (findBlockIn(document, clientId) !== null) {
        return document;
    }

    for (const frame of Array.from(document.querySelectorAll('iframe'))) {
        let frameDocument: Document | null = null;

        try {
            frameDocument = frame.contentDocument;
        } catch {
            frameDocument = null;
        }

        if (frameDocument !== null && findBlockIn(frameDocument, clientId) !== null) {
            return frameDocument;
        }
    }

    return document;
}

function writeHost(host: StyleHost): void {
    host.element.textContent = Array.from(host.entries.values()).join('\n');
}

/**
 * Adds or replaces one entry in a channel's host for `doc`, creating the
 * host on first use. Returns a function that removes the entry again.
 *
 * @since 1.13.0
 *
 * @param channel Host name, e.g. `visibility`.
 * @param owner   Identity of the entry; one entry per owner.
 * @param css     The entry's CSS. `''` removes the entry.
 * @param doc     Document whose `<head>` holds the host.
 */
export function setCanvasScopedStyle(
    channel: string,
    owner: object,
    css: string,
    doc: Document = document
): () => void {
    const perDocument = hostsFor(channel);
    let host = perDocument.get(doc);

    const remove = (): void => {
        const current = perDocument.get(doc);

        if (current === undefined || !current.entries.delete(owner)) {
            return;
        }

        if (current.entries.size === 0) {
            current.element.remove();
            perDocument.delete(doc);

            return;
        }

        writeHost(current);
    };

    if (css === '') {
        remove();

        return () => undefined;
    }

    if (host === undefined) {
        const element = doc.createElement('style');

        element.setAttribute(CANVAS_STYLE_HOST_ATTRIBUTE, channel);
        (doc.head ?? doc.documentElement).appendChild(element);
        host = { element, entries: new Map() };
        perDocument.set(doc, host);
    }

    if (host.entries.get(owner) !== css) {
        host.entries.set(owner, css);
        writeHost(host);
    }

    return remove;
}

/**
 * The host `<style>` element for a channel in `doc`, or `null` when no
 * mounted block has CSS there.
 *
 * @since 1.13.0
 */
export function getCanvasScopedStyleElement(
    channel: string,
    doc: Document = document
): HTMLStyleElement | null {
    return hosts.get(channel)?.get(doc)?.element ?? null;
}

/**
 * Publishes a block's scoped CSS into its canvas document's shared host
 * for `channel` for as long as the calling component is mounted.
 *
 * @since 1.13.0
 *
 * @param channel  Host name, e.g. `visibility`.
 * @param clientId Block whose wrapper locates the canvas document.
 * @param css      The block's CSS; `''` publishes nothing.
 */
export function useCanvasScopedStyle(channel: string, clientId: string, css: string): void {
    const ownerRef = useRef<object>({});
    const documentRef = useRef<Document | null>(null);

    useLayoutEffect(() => {
        if (css === '' || typeof document === 'undefined') {
            return undefined;
        }

        let doc = documentRef.current;

        if (doc === null || findBlockIn(doc, clientId) === null) {
            doc = findBlockDocument(clientId);
            documentRef.current = doc;
        }

        return setCanvasScopedStyle(channel, ownerRef.current, css, doc);
    }, [channel, clientId, css]);
}
