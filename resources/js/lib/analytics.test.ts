import { beforeEach, describe, expect, it } from 'bun:test';
import type { AnalyticsProvider } from './analytics';
import {
    configureAnalytics,
    identifyOnce,
    resetAnalytics,
    trackPageview,
} from './analytics';

function fakeProvider() {
    const calls: Array<{ method: string; args: unknown[] }> = [];

    const provider: AnalyticsProvider = {
        capture: (...args) => calls.push({ method: 'capture', args }),
        identify: (...args) => calls.push({ method: 'identify', args }),
        group: (...args) => calls.push({ method: 'group', args }),
        reset: () => calls.push({ method: 'reset', args: [] }),
    };

    return { provider, calls };
}

beforeEach(() => {
    configureAnalytics(null, false);
    resetAnalytics();
});

describe('analytics', () => {
    it('is a no-op with no provider', () => {
        expect(() => {
            trackPageview('/dashboard');
            identifyOnce('u1', 'o1');
            resetAnalytics();
        }).not.toThrow();
    });

    it('sends nothing when Do Not Track is on, regardless of provider', () => {
        const { provider, calls } = fakeProvider();
        configureAnalytics(provider, true);

        trackPageview('/dashboard');
        identifyOnce('u1', 'o1');

        expect(calls).toEqual([]);
    });

    it('captures a pageview with the url', () => {
        const { provider, calls } = fakeProvider();
        configureAnalytics(provider, false);

        trackPageview('/settings');

        expect(calls).toEqual([
            { method: 'capture', args: ['$pageview', { url: '/settings' }] },
        ]);
    });

    it('identifies and groups once per user', () => {
        const { provider, calls } = fakeProvider();
        configureAnalytics(provider, false);

        identifyOnce('u1', 'o1');
        identifyOnce('u1', 'o1');

        expect(calls).toEqual([
            { method: 'identify', args: ['u1'] },
            { method: 'group', args: ['o1'] },
        ]);
    });

    it('skips group without an organization', () => {
        const { provider, calls } = fakeProvider();
        configureAnalytics(provider, false);

        identifyOnce('u1', null);

        expect(calls).toEqual([{ method: 'identify', args: ['u1'] }]);
    });

    it('reset clears identity and forwards to the provider', () => {
        const { provider, calls } = fakeProvider();
        configureAnalytics(provider, false);

        identifyOnce('u1', null);
        resetAnalytics();
        identifyOnce('u1', null);

        expect(calls.map((c) => c.method)).toEqual([
            'identify',
            'reset',
            'identify',
        ]);
    });
});
