import { registerBlock } from '../../registry';
import CodeEdit, {
    CODE_BLOCK_NAME,
    CODE_LANGUAGES,
    normalizeLanguage,
} from './edit';

export function registerCodeBlock(): void {
    registerBlock({
        name: CODE_BLOCK_NAME,
        edit: CodeEdit,
    });
}

export { CODE_BLOCK_NAME, CODE_LANGUAGES, normalizeLanguage };
export { default as CodeEdit } from './edit';
