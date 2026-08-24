import { Head } from '@inertiajs/react';
import { useState } from 'react';
import { AiBlocks } from '@/components/ai/blocks/AiBlocks';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { streamBlocks } from '@/lib/ai-blocks';
import { store } from '@/routes/ai-block';
import type { AiBlock } from '@/types/ai-blocks';

/**
 * Every block the renderer knows, and one it does not, on one page — plus the
 * live stream, so the same components are read twice: once from a prop and once
 * arriving a line at a time.
 */
export default function BlockGallery({ blocks }: { blocks: AiBlock[] }) {
    const [prompt, setPrompt] = useState('Summarise the organization.');
    const [streamed, setStreamed] = useState<AiBlock[]>([]);

    return (
        <main className="flex flex-col gap-6 p-8">
            <Head title="Block gallery" />

            <h1 className="text-xl font-semibold">Block gallery</h1>

            <section className="flex flex-col gap-4" data-test="static-blocks">
                <h2 className="text-sm font-medium">Every block</h2>

                <AiBlocks blocks={blocks} />
            </section>

            <section
                className="flex flex-col gap-4"
                data-test="streamed-blocks"
            >
                <h2 className="text-sm font-medium">Streamed</h2>

                <form
                    className="flex items-end gap-2"
                    onSubmit={(event) => {
                        event.preventDefault();

                        setStreamed([]);

                        void streamBlocks(store().url, prompt, (block) =>
                            setStreamed((current) => [...current, block]),
                        );
                    }}
                >
                    <div className="flex flex-col gap-1">
                        <Label htmlFor="ai-stream-prompt">Prompt</Label>
                        <Input
                            id="ai-stream-prompt"
                            value={prompt}
                            onChange={(event) => setPrompt(event.target.value)}
                        />
                    </div>

                    <Button
                        type="submit"
                        size="sm"
                        data-test="ai-stream-submit"
                    >
                        Stream
                    </Button>
                </form>

                <AiBlocks blocks={streamed} />
            </section>
        </main>
    );
}
