/**
 * Keyboard shortcuts reference dialog.
 *
 * One of the M8 (#318) swap points: instead of `@wordpress/components`'s
 * `Modal` (which pulls in WP-blue primary and its own focus chrome), this
 * uses `@artisanpack-ui/react`'s `Modal` + `Button` so the dialog inherits
 * DaisyUI palette, radii, and focus rings. The dialog is mounted lazily
 * from `EditorApp` when the user picks "Keyboard shortcuts" from the top
 * bar's more-options menu or hits ⌘/.
 */

import { Button } from '@artisanpack-ui/react/form';
import { Modal } from '@artisanpack-ui/react/layout';
import { __ } from '@wordpress/i18n';

import { TEXT_DOMAIN } from '../vendor/i18n';

export interface KeyboardShortcutsModalProps {
    open: boolean;
    onClose: () => void;
}

interface ShortcutEntry {
    readonly keys: readonly string[];
    readonly label: string;
}

function isMacPlatform(): boolean {
    if (typeof navigator === 'undefined') {
        return false;
    }

    const platform =
        (navigator as Navigator & { userAgentData?: { platform?: string } })
            .userAgentData?.platform ?? navigator.platform;

    return /Mac|iPhone|iPad|iPod/i.test(platform ?? '');
}

function shortcuts(): readonly ShortcutEntry[] {
    const isMac = isMacPlatform();
    const mod = isMac ? '⌘' : 'Ctrl';
    const shift = isMac ? '⇧' : 'Shift';

    return [
        {
            keys: [mod, 'S'],
            label: __('Save the current draft.', TEXT_DOMAIN),
        },
        {
            keys: [mod, 'Z'],
            label: __('Undo the last block edit.', TEXT_DOMAIN),
        },
        {
            keys: [mod, shift, 'Z'],
            label: __('Redo the last undone edit.', TEXT_DOMAIN),
        },
        {
            keys: ['/'],
            label: __(
                'Open the inline block inserter inside the canvas.',
                TEXT_DOMAIN
            ),
        },
        {
            keys: ['Esc'],
            label: __(
                'Close open menus or return focus to the canvas.',
                TEXT_DOMAIN
            ),
        },
    ];
}

export function KeyboardShortcutsModal(
    props: KeyboardShortcutsModalProps
): JSX.Element {
    const { open, onClose } = props;

    return (
        <Modal
            open={open}
            onClose={onClose}
            title={__('Keyboard shortcuts', TEXT_DOMAIN)}
            subtitle={__(
                'Move faster without leaving the keyboard.',
                TEXT_DOMAIN
            )}
            actions={
                <Button color="primary" onClick={onClose}>
                    {__('Got it', TEXT_DOMAIN)}
                </Button>
            }
            data-testid="ap-visual-editor-keyboard-shortcuts-modal"
        >
            <dl className="ap-visual-editor-shortcuts">
                {shortcuts().map((shortcut) => (
                    <div
                        key={shortcut.keys.join('+')}
                        className="ap-visual-editor-shortcuts__row"
                    >
                        <dt className="ap-visual-editor-shortcuts__keys">
                            {shortcut.keys.map((key, index) => (
                                <span
                                    key={`${key}-${index}`}
                                    className="ap-visual-editor-shortcuts__key"
                                >
                                    {key}
                                </span>
                            ))}
                        </dt>
                        <dd className="ap-visual-editor-shortcuts__label">
                            {shortcut.label}
                        </dd>
                    </div>
                ))}
            </dl>
        </Modal>
    );
}
