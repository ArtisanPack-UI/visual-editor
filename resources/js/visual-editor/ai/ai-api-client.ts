/**
 * REST client for the visual editor's AI trigger endpoints (#610-#614).
 *
 * Thin `fetch` wrapper over `/visual-editor/api/ai/*`. Each call maps to
 * one agent invocation server-side and returns the shaped output verbatim.
 * The server enforces feature toggles + credentials, so callers should
 * treat a 403 as "feature disabled" rather than an auth failure.
 */

/**
 * The full set of feature keys the visual-editor's AI surface exposes.
 * This is the JS-side mirror of
 * `ArtisanPackUI\VisualEditor\VisualEditorServiceProvider::AI_FEATURE_KEYS`;
 * both are pinned to the same list so the React bundle can't drift out of
 * sync with the HTTP + Livewire feature-map endpoints. If you add a 6th
 * key, add it in both places (review #6).
 */
export const AI_FEATURE_KEYS = [
    'visual_editor.suggest_next_block',
    'visual_editor.suggest_layout',
    'visual_editor.heading_hierarchy',
    'ai.alt_text',
    'ai.content_rewrite',
] as const;

export type AiFeatureKey = (typeof AI_FEATURE_KEYS)[number];

export type AiFeaturesMap = Record<AiFeatureKey, boolean>;

export interface SuggestNextBlockInput {
    existing_blocks: readonly unknown[];
    cursor_position: number;
    document_type?: string | null;
}

export interface BlockSuggestion {
    block_type: string;
    why: string;
    starter_content?: string;
}

export interface SuggestNextBlockOutput {
    suggestions: BlockSuggestion[];
}

export interface SuggestLayoutInput {
    section_content: readonly unknown[];
    available_patterns: readonly string[];
}

export interface LayoutMatch {
    pattern_slug: string;
    confidence: number;
    rationale: string;
}

export interface SuggestLayoutOutput {
    matches: LayoutMatch[];
}

export type AltTextInput =
    | string
    | { source: 'path' | 'url' | 'base64'; value: string };

export interface AltTextOutput {
    alt_text: string;
    confidence: number;
    warnings: string[];
}

export interface RewriteInput {
    content: string;
    intent: string;
}

export interface RewriteOutput {
    rewrite: string;
    changed_ratio: number;
    rationale: string;
}

export interface HeadingHierarchyInput {
    blocks: readonly unknown[];
}

export interface HeadingIssue {
    block_id: string;
    issue: string;
    suggestion: string;
}

export interface HeadingHierarchyOutput {
    issues: HeadingIssue[];
}

export class AiApiError extends Error {
    public readonly status: number;

    public readonly code: string;

    public readonly feature: string | null;

    public constructor(
        message: string,
        status: number,
        code: string,
        feature: string | null,
    ) {
        super(message);
        this.name = 'AiApiError';
        this.status = status;
        this.code = code;
        this.feature = feature;
    }
}

export interface AiApiClientConfig {
    apiBase: string;
    /** Optional `fetch` override for tests. */
    fetchImpl?: typeof fetch;
    /** Extra headers merged into every request (e.g. `Authorization`). */
    headers?: Record<string, string>;
}

function readCsrfToken(): string | null {
    if (typeof document === 'undefined') {
        return null;
    }
    const meta = document.querySelector<HTMLMetaElement>('meta[name="csrf-token"]');
    return meta?.content?.trim() || null;
}

interface AgentEnvelope<T> {
    feature: string;
    output: T;
}

interface AgentErrorEnvelope {
    feature?: string;
    error?: string;
    message?: string;
}

export function createAiApiClient(config: AiApiClientConfig) {
    const base = config.apiBase.replace(/\/$/, '');
    const doFetch = config.fetchImpl ?? globalThis.fetch;

    async function call<TOut>(path: string, method: 'GET' | 'POST', body?: unknown): Promise<TOut> {
        const csrf = readCsrfToken();
        const headers: Record<string, string> = {
            Accept: 'application/json',
            ...(config.headers ?? {}),
        };
        if (method === 'POST') {
            headers['Content-Type'] = 'application/json';
        }
        if (csrf) {
            headers['X-CSRF-TOKEN'] = csrf;
        }

        const response = await doFetch(`${base}${path}`, {
            method,
            headers,
            credentials: 'same-origin',
            body: method === 'POST' && body !== undefined ? JSON.stringify(body) : undefined,
        });

        const raw = await response.text();
        let parsed: unknown = null;
        if (raw.length > 0) {
            try {
                parsed = JSON.parse(raw);
            } catch {
                parsed = raw;
            }
        }

        if (!response.ok) {
            const envelope = (parsed ?? {}) as AgentErrorEnvelope;
            throw new AiApiError(
                envelope.message ?? `AI API responded with ${response.status}`,
                response.status,
                envelope.error ?? 'http_error',
                envelope.feature ?? null,
            );
        }

        return parsed as TOut;
    }

    return {
        async features(): Promise<AiFeaturesMap> {
            const data = await call<{ features: AiFeaturesMap }>('/ai/features', 'GET');
            return data.features;
        },

        async suggestNextBlock(input: SuggestNextBlockInput): Promise<SuggestNextBlockOutput> {
            const env = await call<AgentEnvelope<SuggestNextBlockOutput>>('/ai/suggest-next-block', 'POST', input);
            return env.output;
        },

        async suggestLayout(input: SuggestLayoutInput): Promise<SuggestLayoutOutput> {
            const env = await call<AgentEnvelope<SuggestLayoutOutput>>('/ai/suggest-layout', 'POST', input);
            return env.output;
        },

        async altText(image: AltTextInput): Promise<AltTextOutput> {
            const env = await call<AgentEnvelope<AltTextOutput>>('/ai/alt-text', 'POST', { image });
            return env.output;
        },

        async rewrite(input: RewriteInput): Promise<RewriteOutput> {
            const env = await call<AgentEnvelope<RewriteOutput>>('/ai/rewrite', 'POST', input);
            return env.output;
        },

        async headingHierarchy(input: HeadingHierarchyInput): Promise<HeadingHierarchyOutput> {
            const env = await call<AgentEnvelope<HeadingHierarchyOutput>>('/ai/heading-hierarchy', 'POST', input);
            return env.output;
        },
    };
}

export type AiApiClient = ReturnType<typeof createAiApiClient>;
