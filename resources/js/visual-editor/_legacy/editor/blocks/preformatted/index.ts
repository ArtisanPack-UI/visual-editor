import { registerBlockType, type BlockJsonMetadata } from '../../registry';
import rawMetadata from './block.json';
import PreformattedEdit, { PREFORMATTED_BLOCK_NAME } from './edit';

const metadata = rawMetadata as unknown as BlockJsonMetadata;

export function registerPreformattedBlock(): void {
    registerBlockType(metadata, {
        edit: PreformattedEdit,
        factory: () => ({
            name: PREFORMATTED_BLOCK_NAME,
            attributes: { content: '<pre></pre>' },
            innerBlocks: [],
        }),
    });
}

export { PREFORMATTED_BLOCK_NAME };
export { default as PreformattedEdit } from './edit';
