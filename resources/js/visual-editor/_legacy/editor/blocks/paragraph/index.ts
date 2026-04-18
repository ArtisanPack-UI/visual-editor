import { registerBlockType, type BlockJsonMetadata } from '../../registry';
import rawMetadata from './block.json';
import ParagraphEdit from './edit';

const metadata = rawMetadata as unknown as BlockJsonMetadata;

export const PARAGRAPH_BLOCK_NAME = metadata.name;

export function registerParagraphBlock(): void {
    registerBlockType(metadata, {
        edit: ParagraphEdit,
        factory: () => ({
            name: PARAGRAPH_BLOCK_NAME,
            attributes: { content: '<p></p>' },
            innerBlocks: [],
        }),
    });
}

export { default as ParagraphEdit } from './edit';
