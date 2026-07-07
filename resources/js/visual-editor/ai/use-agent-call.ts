/**
 * Generic hook that wraps a single AI API method with idle/loading/error/
 * result state. Each of the five feature-specific hooks in this folder
 * composes on top of this one so the state machine stays consistent.
 */

import { useCallback, useEffect, useRef, useState } from 'react';
import { AiApiError } from './ai-api-client';

export type AgentCallStatus = 'idle' | 'loading' | 'success' | 'error';

export interface AgentCallState<TOutput> {
    status: AgentCallStatus;
    result: TOutput | null;
    error: AiApiError | Error | null;
}

export interface UseAgentCallReturn<TInput, TOutput> extends AgentCallState<TOutput> {
    run: (input: TInput) => Promise<TOutput | null>;
    reset: () => void;
}

export function useAgentCall<TInput, TOutput>(
    invoke: (input: TInput) => Promise<TOutput>,
): UseAgentCallReturn<TInput, TOutput> {
    const [state, setState] = useState<AgentCallState<TOutput>>({
        status: 'idle',
        result: null,
        error: null,
    });
    const mountedRef = useRef(true);
    // Increments on every `run()`. A resolved request only commits to
    // state when its id still matches `latestRunIdRef` — this drops the
    // result of an older overlapping request that happens to settle
    // *after* a newer one (review #3), which would otherwise stomp the
    // fresher state.
    const latestRunIdRef = useRef(0);

    useEffect(() => {
        mountedRef.current = true;
        return () => {
            mountedRef.current = false;
        };
    }, []);

    const run = useCallback(
        async (input: TInput) => {
            const runId = ++latestRunIdRef.current;
            setState({ status: 'loading', result: null, error: null });
            try {
                const result = await invoke(input);
                if (mountedRef.current && latestRunIdRef.current === runId) {
                    setState({ status: 'success', result, error: null });
                }
                return result;
            } catch (err) {
                const normalized =
                    err instanceof Error ? err : new Error(String(err));
                if (mountedRef.current && latestRunIdRef.current === runId) {
                    setState({ status: 'error', result: null, error: normalized });
                }
                return null;
            }
        },
        [invoke],
    );

    const reset = useCallback(() => {
        // Bump the id so any in-flight request whose promise settles
        // later can't restore state after a manual reset.
        latestRunIdRef.current++;
        setState({ status: 'idle', result: null, error: null });
    }, []);

    return { ...state, run, reset };
}
