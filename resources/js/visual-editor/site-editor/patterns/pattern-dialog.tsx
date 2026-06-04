/**
 * Shared dialog shell for the patterns workflow.
 *
 * The site-editor's `CreateEntityDialog` is templated against the
 * shared `EntityKind` (template / template-part) and is too coupled to
 * the templates API client to reuse for patterns. This shell is a thin
 * focus-trapping shim that the patterns dialogs wrap their bodies in
 * — no HTTP, no validation, just keyboard semantics.
 */

import { __ } from '@wordpress/i18n';
import {
    useCallback,
    useEffect,
    useId,
    useRef,
    type KeyboardEvent as ReactKeyboardEvent,
    type ReactNode,
} from 'react';

import { TEXT_DOMAIN } from '../../vendor/i18n';

import './pattern-dialog.css';

export interface PatternDialogProps {
    title: string;
    onClose: () => void;
    children: ReactNode;
    /** `data-testid` suffix so each dialog has a stable test handle. */
    testKey: string;
    /** Optional aria-describedby target for the dialog body. */
    descriptionId?: string;
}

function makeFocusables(container: HTMLElement): HTMLElement[] {
    return Array.from(
        container.querySelectorAll<HTMLElement>(
            'button,[href],input,select,textarea,[tabindex]:not([tabindex="-1"])'
        )
    ).filter((element) => !element.hasAttribute('disabled'));
}

export function PatternDialog(props: PatternDialogProps): JSX.Element {
    const { title, onClose, children, testKey, descriptionId } = props;

    const dialogRef = useRef<HTMLDivElement | null>(null);
    const previousFocusRef = useRef<HTMLElement | null>(null);
    const titleId = useId();

    useEffect(() => {
        if (typeof document === 'undefined') {
            return;
        }

        previousFocusRef.current =
            document.activeElement instanceof HTMLElement
                ? document.activeElement
                : null;

        const dialog = dialogRef.current;

        if (dialog !== null) {
            const focusables = makeFocusables(dialog);
            focusables[0]?.focus();
        }

        return () => {
            previousFocusRef.current?.focus();
        };
    }, []);

    const handleKeyDown = useCallback(
        (event: ReactKeyboardEvent<HTMLDivElement>): void => {
            if (event.key === 'Escape') {
                event.preventDefault();
                onClose();
                return;
            }

            if (event.key !== 'Tab') {
                return;
            }

            const dialog = dialogRef.current;

            if (dialog === null) {
                return;
            }

            const focusables = makeFocusables(dialog);

            if (focusables.length === 0) {
                return;
            }

            const first = focusables[0];
            const last = focusables[focusables.length - 1];

            if (first === undefined || last === undefined) {
                return;
            }

            if (event.shiftKey && document.activeElement === first) {
                event.preventDefault();
                last.focus();
            } else if (!event.shiftKey && document.activeElement === last) {
                event.preventDefault();
                first.focus();
            }
        },
        [onClose]
    );

    return (
        <div
            className="ap-pattern-dialog__scrim"
            data-testid={`ap-pattern-dialog-${testKey}`}
            onClick={(event) => {
                if (event.target === event.currentTarget) {
                    onClose();
                }
            }}
        >
            <div
                ref={dialogRef}
                className="ap-pattern-dialog"
                role="dialog"
                aria-modal="true"
                aria-labelledby={titleId}
                aria-describedby={descriptionId}
                onKeyDown={handleKeyDown}
            >
                <header className="ap-pattern-dialog__header">
                    <h2
                        id={titleId}
                        className="ap-pattern-dialog__title"
                    >
                        {title}
                    </h2>
                    <button
                        type="button"
                        className="ap-pattern-dialog__close"
                        aria-label={__('Close dialog', TEXT_DOMAIN)}
                        onClick={onClose}
                        data-testid={`ap-pattern-dialog-close-${testKey}`}
                    >
                        <span aria-hidden="true">{'×'}</span>
                    </button>
                </header>
                {children}
            </div>
        </div>
    );
}
