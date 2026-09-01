/**
 * Provider-agnostic client analytics seam. No provider ships by default —
 * every call is a no-op until configureAnalytics() receives one. Do Not
 * Track (server-shared prop or the browser signal) silences everything
 * regardless of provider.
 */
export interface AnalyticsProvider {
    capture(event: string, properties?: Record<string, unknown>): void;
    identify(userId: string, traits?: Record<string, unknown>): void;
    group(groupId: string, traits?: Record<string, unknown>): void;
    reset(): void;
}

let provider: AnalyticsProvider | null = null;
let doNotTrack = false;
let identifiedUserId: string | null = null;

export function configureAnalytics(
    nextProvider: AnalyticsProvider | null,
    dnt: boolean,
): void {
    provider = nextProvider;
    doNotTrack = dnt;
}

function blocked(): boolean {
    if (doNotTrack || provider === null) {
        return true;
    }

    return typeof navigator !== 'undefined' && navigator.doNotTrack === '1';
}

export function trackPageview(url: string): void {
    if (blocked()) {
        return;
    }

    provider?.capture('$pageview', { url });
}

export function identifyOnce(
    userId: string,
    organizationId: string | null,
): void {
    if (blocked() || identifiedUserId === userId) {
        return;
    }

    identifiedUserId = userId;
    provider?.identify(userId);

    if (organizationId !== null) {
        provider?.group(organizationId);
    }
}

export function resetAnalytics(): void {
    identifiedUserId = null;
    provider?.reset();
}
