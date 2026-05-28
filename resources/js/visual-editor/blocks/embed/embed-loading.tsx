/**
 * Embed — loading state.
 *
 * Ported from `@wordpress/block-library/src/embed/embed-loading.js`
 * (v9.43.0).
 */

import type { ReactElement } from 'react';
import { Spinner } from '@wordpress/components';

export default function EmbedLoading(): ReactElement {
    return (
        <div className="wp-block-embed is-loading">
            <Spinner />
        </div>
    );
}
