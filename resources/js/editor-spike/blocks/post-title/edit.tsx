import type { BlockEditProps } from '../../registry';
import { useBlockContextValue } from '../../primitives/BlockContext';
import { fetchPostField } from '../../mocks/mockApi';

export default function PostTitleEdit({ clientId }: BlockEditProps) {
    const postId = useBlockContextValue<number>('postId');
    const postType = useBlockContextValue<string>('postType');

    const title =
        typeof postId === 'number' && typeof postType === 'string'
            ? fetchPostField(postType, postId, 'title')
            : '';

    if (title === '') {
        return (
            <h2
                data-client-id={clientId}
                data-block-name="ve/post-title"
                data-placeholder="true"
            >
                [post title]
            </h2>
        );
    }

    return (
        <h2 data-client-id={clientId} data-block-name="ve/post-title">
            {title}
        </h2>
    );
}
