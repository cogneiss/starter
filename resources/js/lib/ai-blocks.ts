import type { AiBlock } from '@/types/ai-blocks';

const KNOWN_TYPES = new Set([
    'text',
    'markdown',
    'table',
    'list',
    'metric',
    'form',
    'confirm',
]);

function csrfToken(): string {
    const cookie = document.cookie.match(/XSRF-TOKEN=([^;]+)/);

    return cookie === null ? '' : decodeURIComponent(cookie[1]);
}

/**
 * Posts JSON and reads JSON back, with the cookie CSRF token Laravel expects.
 */
export async function postJson<T>(url: string, body: unknown): Promise<T> {
    const response = await fetch(url, {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json',
            Accept: 'application/json',
            'X-XSRF-TOKEN': csrfToken(),
        },
        body: JSON.stringify(body),
    });

    if (!response.ok) {
        throw new Error(`The request failed with status ${response.status}.`);
    }

    return (await response.json()) as T;
}

/**
 * Reads the newline-delimited block stream, calling back once per complete
 * line. A line that is not a block this build knows is dropped here as well as
 * on the server, so an older client meeting a newer server renders nothing
 * rather than a blank slot.
 */
export async function streamBlocks(
    url: string,
    prompt: string,
    onBlock: (block: AiBlock) => void,
): Promise<void> {
    const response = await fetch(url, {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json',
            'X-XSRF-TOKEN': csrfToken(),
        },
        body: JSON.stringify({ prompt }),
    });

    if (response.body === null) {
        throw new Error('The stream returned no body.');
    }

    const reader = response.body.getReader();
    const decoder = new TextDecoder();
    let buffer = '';

    for (;;) {
        const { done, value } = await reader.read();

        buffer += decoder.decode(value, { stream: !done });

        let brk = buffer.indexOf('\n');

        while (brk !== -1) {
            emit(buffer.slice(0, brk), onBlock);
            buffer = buffer.slice(brk + 1);
            brk = buffer.indexOf('\n');
        }

        if (done) {
            break;
        }
    }

    emit(buffer, onBlock);
}

function emit(line: string, onBlock: (block: AiBlock) => void): void {
    if (line.trim() === '') {
        return;
    }

    const parsed: unknown = JSON.parse(line);

    if (
        typeof parsed === 'object' &&
        parsed !== null &&
        'type' in parsed &&
        typeof parsed.type === 'string' &&
        KNOWN_TYPES.has(parsed.type)
    ) {
        onBlock(parsed as AiBlock);
    }
}
