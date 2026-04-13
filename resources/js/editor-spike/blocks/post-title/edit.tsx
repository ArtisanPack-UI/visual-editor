import type { BlockEditProps } from '../../registry';
import { useBlockContextValue } from '../../primitives/BlockContext';
import { fetchPostField } from '../../mocks/mockApi';

export default function PostTitleEdit({ clientId }: BlockEditProps) {
    const postId = useBlockContextValue<number>('postId');
    const postType = useBlockContextValue<string>('postType');

    if (typeof postId !== 'number' || typeof postType !== 'string') {
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

    const title = fetchPostField(postType, postId, 'title');

    return (
        <h2 data-client-id={clientId} data-block-name="ve/post-title">
            {title}
        </h2>
    );
}
