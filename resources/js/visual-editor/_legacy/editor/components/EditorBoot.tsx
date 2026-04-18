import { useCallback, useEffect, useMemo, useRef, useState } from 'react';
import { Button } from '@artisanpack-ui/react/form';
import { EditorShell } from './EditorShell';
import {
    fetchPost,
    useAutosave,
    type AutosaveState,
    type PostRestClientOptions,
} from '../rest';
import { createEditorStore, type Block, type EditorStore } from '../store';

export interface EditorBootProps {
    postId: string;
    postType: string;
    apiBase: string;
}

type LoadState =
    | { status: 'loading' }
    | { status: 'error'; error: Error }
    | { status: 'ready'; store: EditorStore };

export function EditorBoot(props: EditorBootProps) {
    const { postId, apiBase } = props;

    const clientOptions = useMemo<PostRestClientOptions>(
        () => ({ apiBase }),
        [apiBase]
    );

    const [loadState, setLoadState] = useState<LoadState>({ status: 'loading' });
    const [loadAttempt, setLoadAttempt] = useState(0);
    const abortRef = useRef<AbortController | null>(null);

    useEffect(() => {
        const controller = new AbortController();
        abortRef.current = controller;

        setLoadState({ status: 'loading' });

        fetchPost(postId, clientOptions, { signal: controller.signal })
            .then((payload) => {
                if (controller.signal.aborted) {
                    return;
                }

                const store = createEditorStore(payload.blocks as Block[]);
                setLoadState({ status: 'ready', store });
            })
            .catch((error: unknown) => {
                if (controller.signal.aborted) {
                    return;
                }

                const wrapped =
                    error instanceof Error ? error : new Error('Failed to load post.');
                setLoadState({ status: 'error', error: wrapped });
            });

        return () => {
            controller.abort();
            abortRef.current = null;
        };
    }, [postId, clientOptions, loadAttempt]);

    const retry = useCallback(() => {
        setLoadAttempt((attempt) => attempt + 1);
    }, []);

    if (loadState.status === 'loading') {
        return (
            <div
                className="ve-editor-boot ve-editor-boot--loading"
                data-ve-editor-boot="loading"
                role="status"
                aria-live="polite"
            >
                Loading editor…
            </div>
        );
    }

    if (loadState.status === 'error') {
        return (
            <div
                className="ve-editor-boot ve-editor-boot--error"
                data-ve-editor-boot="error"
                role="alert"
            >
                <p className="ve-editor-boot__message">
                    Failed to load the post: {loadState.error.message}
                </p>
                <Button onClick={retry} label="Retry" />

            </div>
        );
    }

    return (
        <EditorBootReady
            store={loadState.store}
            postId={postId}
            clientOptions={clientOptions}
        />
    );
}

interface EditorBootReadyProps {
    store: EditorStore;
    postId: string;
    clientOptions: PostRestClientOptions;
}

function EditorBootReady({ store, postId, clientOptions }: EditorBootReadyProps) {
    const autosave = useAutosave({ store, postId, clientOptions });

    return (
        <>
            <AutosaveAnnouncer state={autosave} />
            <EditorShell store={store} saveStatus={autosave} />
        </>
    );
}

function AutosaveAnnouncer({ state }: { state: AutosaveState }) {
    if (state.status !== 'error' || !state.lastError) {
        return null;
    }

    return (
        <div
            className="ve-editor-boot__toast"
            data-ve-editor-boot="save-error"
            role="alert"
        >
            Failed to save: {state.lastError.message}
        </div>
    );
}
