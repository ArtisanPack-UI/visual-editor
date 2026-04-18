import { registerBlockType, type BlockJsonMetadata } from '../../registry';
import rawMetadata from './block.json';
import ListEdit, { LIST_BLOCK_NAME } from './edit';

const metadata = rawMetadata as unknown as BlockJsonMetadata;

export function registerListBlock(): void {
    registerBlockType(metadata, {
        edit: ListEdit,
        factory: () => ({
            name: LIST_BLOCK_NAME,
            attributes: { ordered: false, content: '<ul><li><p></p></li></ul>' },
            innerBlocks: [],
        }),
    });
}

export { LIST_BLOCK_NAME };
export { normalizeOrdered } from './edit';
export { default as ListEdit } from './edit';
