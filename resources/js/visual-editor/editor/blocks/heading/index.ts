import { registerBlockType, type BlockJsonMetadata } from '../../registry';
import rawMetadata from './block.json';
import HeadingEdit, { HEADING_LEVELS, normalizeHeadingLevel } from './edit';

const metadata = rawMetadata as unknown as BlockJsonMetadata;

export const HEADING_BLOCK_NAME = metadata.name;

export function registerHeadingBlock(): void {
    registerBlockType(metadata, {
        edit: HeadingEdit,
        factory: () => ({
            name: HEADING_BLOCK_NAME,
            attributes: { level: 2, content: '<h2></h2>' },
            innerBlocks: [],
        }),
    });
}

export { HEADING_LEVELS, normalizeHeadingLevel };
export { default as HeadingEdit } from './edit';
