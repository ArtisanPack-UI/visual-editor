import { render, screen, waitFor } from '@testing-library/react';
import { ToastProvider } from '@artisanpack-ui/react/feedback';
import { afterEach, describe, expect, it, vi } from 'vitest';

import { useSaveNotifications } from '../save-notifications';
import type { SaveStatus } from '../top-bar';

interface HarnessProps {
    saveStatus: SaveStatus;
    saveErrorMessage?: string | null;
}

function Harness(props: HarnessProps): JSX.Element {
    useSaveNotifications(props);

    return <div data-testid="ap-save-notifications-harness" />;
}

function renderHarness(props: HarnessProps) {
    return render(
        <ToastProvider>
            <Harness {...props} />
        </ToastProvider>
    );
}

afterEach(() => {
    vi.restoreAllMocks();
});

describe('useSaveNotifications', () => {
    it('fires a toast when transitioning into the error state', async () => {
        const { rerender } = renderHarness({ saveStatus: 'idle' });

        expect(screen.queryByText('Save crashed.')).not.toBeInTheDocument();

        rerender(
            <ToastProvider>
                <Harness saveStatus="error" saveErrorMessage="Save crashed." />
            </ToastProvider>
        );

        await waitFor(() => {
            expect(screen.getByText('Save crashed.')).toBeInTheDocument();
        });
    });

    it('falls back to a default message when no save error message is provided', async () => {
        const { rerender } = renderHarness({ saveStatus: 'saving' });

        rerender(
            <ToastProvider>
                <Harness saveStatus="error" />
            </ToastProvider>
        );

        await waitFor(() => {
            expect(
                screen.getByText('Unable to save changes. Please try again.')
            ).toBeInTheDocument();
        });
    });

    it('does not fire a second toast when the save stays in the error state', async () => {
        const { rerender } = renderHarness({ saveStatus: 'saving' });

        rerender(
            <ToastProvider>
                <Harness saveStatus="error" saveErrorMessage="Boom." />
            </ToastProvider>
        );

        await waitFor(() => {
            expect(screen.getAllByText('Boom.').length).toBe(1);
        });

        rerender(
            <ToastProvider>
                <Harness saveStatus="error" saveErrorMessage="Boom." />
            </ToastProvider>
        );

        // Give React a tick to flush any queued effect; if `useSaveNotifications`
        // were to (incorrectly) re-fire, a second toast would appear here.
        await waitFor(() => {
            expect(screen.getAllByText('Boom.').length).toBe(1);
        });
    });
});
