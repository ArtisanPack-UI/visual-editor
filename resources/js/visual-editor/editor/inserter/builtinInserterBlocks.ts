import { HEADING_BLOCK_NAME } from '../blocks/heading';
import { PARAGRAPH_BLOCK_NAME } from '../blocks/paragraph';
import { LIST_BLOCK_NAME } from '../blocks/list';
import { QUOTE_BLOCK_NAME } from '../blocks/quote';
import { CODE_BLOCK_NAME } from '../blocks/code';
import { PREFORMATTED_BLOCK_NAME } from '../blocks/preformatted';
import { registerBlockFactory, registerInserterBlock } from './inserterRegistry';

/**
 * Register the inserter metadata and block factories for the core text
 * block types. These seeds guarantee the inserter has something to show
 * even when the REST API (#274) is not yet available.
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
    registerInserterBlock({
        name: LIST_BLOCK_NAME,
        title: 'List',
        description: 'Create a bulleted or numbered list.',
        keywords: ['ul', 'ol', 'bullet', 'numbered', 'ordered', 'unordered'],
    });
    registerInserterBlock({
        name: QUOTE_BLOCK_NAME,
        title: 'Quote',
        description: 'Give quoted text visual emphasis.',
        keywords: ['blockquote', 'citation', 'pullquote'],
    });
    registerInserterBlock({
        name: CODE_BLOCK_NAME,
        title: 'Code',
        description: 'Display code snippets with monospaced formatting.',
        keywords: ['pre', 'snippet', 'syntax'],
    });
    registerInserterBlock({
        name: PREFORMATTED_BLOCK_NAME,
        title: 'Preformatted',
        description: 'Preserve whitespace and line breaks in a monospaced block.',
        keywords: ['pre', 'ascii', 'whitespace'],
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
    registerBlockFactory(LIST_BLOCK_NAME, () => ({
        name: LIST_BLOCK_NAME,
        attributes: { ordered: false, content: '<ul><li><p></p></li></ul>' },
        innerBlocks: [],
    }));
    registerBlockFactory(QUOTE_BLOCK_NAME, () => ({
        name: QUOTE_BLOCK_NAME,
        attributes: { content: '<blockquote><p></p></blockquote>', citation: '' },
        innerBlocks: [],
    }));
    registerBlockFactory(CODE_BLOCK_NAME, () => ({
        name: CODE_BLOCK_NAME,
        attributes: { content: '', language: 'plaintext' },
        innerBlocks: [],
    }));
    registerBlockFactory(PREFORMATTED_BLOCK_NAME, () => ({
        name: PREFORMATTED_BLOCK_NAME,
        attributes: { content: '<pre></pre>' },
        innerBlocks: [],
    }));
}
