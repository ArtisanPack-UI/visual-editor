/**
 * Shows `core/notices` snackbars (#1066).
 *
 * Blocks and the core-data shim raise notices, but the editors have no
 * upstream `EditorSnackbars` to show them. Render it once in each shell,
 * outside the canvas iframe.
 *
 * @package @artisanpack-ui/visual-editor
 * @since 1.20.0
 */

import type { ReactElement } from 'react';

import { Snackbar } from '@wordpress/components';
import { useDispatch, useSelect } from '@wordpress/data';
import { store as noticesStore } from '@wordpress/notices';

/**
 * The snackbar stack, fixed to the corner of the screen.
 *
 * This renders each `Snackbar` rather than a `SnackbarList`, which is
 * positioned absolutely and so would hang off the bottom of the screen
 * from a corner like this one.
 *
 * @since 1.20.0
 */
export function EditorSnackbars(): ReactElement {
    const notices = useSelect(
        ( select ) => select( noticesStore ).getNotices().filter( ( { type } ) => 'snackbar' === type ),
        [],
    );
    const { removeNotice } = useDispatch( noticesStore );

    return (
        <div
            style={ {
                position: 'fixed',
                insetBlockEnd: 16,
                insetInlineStart: 16,
                zIndex: 100000,
                display: 'flex',
                flexDirection: 'column',
                gap: 8,
                pointerEvents: 'none',
            } }
        >
            { notices.map( ( notice ) => (
                <Snackbar
                    key={ notice.id }
                    actions={ notice.actions }
                    explicitDismiss={ notice.explicitDismiss }
                    onDismiss={ notice.onDismiss }
                    onRemove={ () => removeNotice( notice.id ) }
                >
                    { notice.content }
                </Snackbar>
            ) ) }
        </div>
    );
}
