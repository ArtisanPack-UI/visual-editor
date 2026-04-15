import { HEADING_BLOCK_NAME } from '../blocks/heading';
import { PARAGRAPH_BLOCK_NAME } from '../blocks/paragraph';
import { registerBlockFactory, registerInserterBlock } from './inserterRegistry';

/**
 * Register the inserter metadata and block factories for the two core text
 * block types shipped in Phase 1.10 (#272). These seeds guarantee the
 * inserter has something to show even when the REST API (#274) is not yet
 * available.
 */
export function registerBuiltinInserterBlocks(): void {
    registerInserterBlock({
        name: PARAGRAPH_BLOCK_NAME,
        title: 'Paragraph',
        description: 'Start with the basic building block of all narrative.',
        keywords: ['text', 'body', 'p'],
    });
    registerInserterBlock({
        name: HEADING_BLOCK_NAME,
        title: 'Heading',
        description: 'Introduce new sections and organize content to help readers scan.',
        keywords: ['title', 'h1', 'h2', 'h3', 'h4', 'h5', 'h6'],
    });

    registerBlockFactory(PARAGRAPH_BLOCK_NAME, () => ({
        name: PARAGRAPH_BLOCK_NAME,
        attributes: { content: '<p></p>' },
        innerBlocks: [],
    }));
    registerBlockFactory(HEADING_BLOCK_NAME, () => ({
        name: HEADING_BLOCK_NAME,
        attributes: { level: 2, content: '<h2></h2>' },
        innerBlocks: [],
    }));
}
