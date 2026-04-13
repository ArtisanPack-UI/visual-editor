import { registerBlock } from '../../registry';
import PostTitleEdit from './edit';

export const POST_TITLE_BLOCK_NAME = 've/post-title';

export function registerPostTitleBlock(): void {
    registerBlock({
        name: POST_TITLE_BLOCK_NAME,
        edit: PostTitleEdit,
        usesContext: ['postId', 'postType'],
    });
}

export { default as PostTitleEdit } from './edit';
