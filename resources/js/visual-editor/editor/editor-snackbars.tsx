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
import { __ } from '@wordpress/i18n';
import { store as noticesStore } from '@wordpress/notices';

import { TEXT_DOMAIN } from '../vendor/i18n';

/**
 * The snackbar stack, fixed to the corner of the screen.
 *
 * This renders each `Snackbar` rather than a `SnackbarList`, which is
 * positioned absolutely and so would hang off the bottom of the screen
 * from a corner like this one.
 *
 * The stack is a labelled region, rendered after the editor shell, so a
 * keyboard user reaches a snackbar's action (such as "Import to media
 * library") by tabbing forward from the inspector.
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
            role="region"
            aria-label={ __( 'Editor notifications', TEXT_DOMAIN ) }
            tabIndex={ -1 }
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
