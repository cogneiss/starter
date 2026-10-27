/**
 * Reads a response body as the type the caller expects of its own server.
 * `Response.json()` is untyped, so the assertion lives here once instead of at
 * every call site.
 */
export async function readJson<T>(response: Response): Promise<T> {
    // oxlint-disable-next-line typescript/no-unsafe-type-assertion -- the body is our own server's JSON, typed by the caller; there is nothing to narrow it with
    return (await response.json()) as T;
}
