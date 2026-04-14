let counter = 0;

/**
 * Generates a unique client-side block ID. Uses `crypto.randomUUID` when
 * available and falls back to a timestamp + counter combination for
 * environments that do not expose the Web Crypto API.
 */
export function createClientId(): string {
    if (typeof globalThis.crypto?.randomUUID === 'function') {
        return globalThis.crypto.randomUUID();
    }

    counter += 1;

    return `ve-${Date.now().toString(36)}-${counter.toString(36)}`;
}
