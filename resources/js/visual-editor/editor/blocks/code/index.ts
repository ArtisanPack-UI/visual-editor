import { registerBlockType, type BlockJsonMetadata } from '../../registry';
import rawMetadata from './block.json';
import CodeEdit, { CODE_BLOCK_NAME } from './edit';

const metadata = rawMetadata as unknown as BlockJsonMetadata;

export function registerCodeBlock(): void {
    registerBlockType(metadata, {
        edit: CodeEdit,
        factory: () => ({
            name: CODE_BLOCK_NAME,
            attributes: { content: '', language: 'plaintext' },
            innerBlocks: [],
        }),
    });
}

export { CODE_BLOCK_NAME };
export { CODE_LANGUAGES, normalizeLanguage } from './edit';
export { default as CodeEdit } from './edit';
