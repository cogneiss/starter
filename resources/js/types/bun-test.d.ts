/**
 * Minimal ambient types for the built-in bun:test runner — just what the
 * suite uses, so no bun-types dependency is needed for tsc to pass.
 */
declare module 'bun:test' {
    export function describe(name: string, fn: () => void): void;
    export function it(name: string, fn: () => void | Promise<void>): void;
    export function beforeEach(fn: () => void | Promise<void>): void;

    interface Matchers {
        toEqual(expected: unknown): void;
        toThrow(): void;
        not: Matchers;
    }

    export function expect(value: unknown): Matchers;
}
