import type { Block } from '../src/types';

export function makeBlock(
    name: string,
    attributes: Record<string, unknown> = {},
    innerBlocks: Block[] = [],
    clientId?: string
): Block {
    return {
        clientId: clientId ?? `cid-${Math.random().toString(36).slice(2, 10)}`,
        name,
        attributes,
        innerBlocks,
    };
}

export function normalizeHtml(html: string): string {
    return html.replace(/>\s+</g, '><').replace(/\s+/g, ' ').trim();
}
