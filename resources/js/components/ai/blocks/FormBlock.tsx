import { useState } from 'react';
import { ConfirmBlock } from '@/components/ai/blocks/ConfirmBlock';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { postJson } from '@/lib/ai-blocks';
import { store } from '@/routes/ai-proposal';
import type { AiBlock } from '@/types/ai-blocks';

/**
 * A block that asks rather than acts. Submitting it proposes the action and
 * gets a confirm block back; the write itself needs the second click.
 */
export function FormBlock({
    block,
}: {
    block: Extract<AiBlock, { type: 'form' }>;
}) {
    const [values, setValues] = useState<Record<string, string>>(() =>
        Object.fromEntries(
            block.fields.map((field) => [field.name, field.value]),
        ),
    );
    const [confirm, setConfirm] = useState<Extract<
        AiBlock,
        { type: 'confirm' }
    > | null>(null);

    if (confirm !== null) {
        return <ConfirmBlock block={confirm} />;
    }

    return (
        <form
            className="flex flex-col gap-3 rounded-md border p-3"
            data-test="ai-form-block"
            onSubmit={(event) => {
                event.preventDefault();

                void postJson<Extract<AiBlock, { type: 'confirm' }>>(
                    store().url,
                    { action: block.action, fields: values },
                ).then(setConfirm);
            }}
        >
            <p className="text-sm font-medium">{block.summary}</p>

            {block.fields.map((field) => (
                <div key={field.name} className="flex flex-col gap-1">
                    <Label htmlFor={`ai-form-${block.action}-${field.name}`}>
                        {field.name}
                    </Label>
                    <Input
                        id={`ai-form-${block.action}-${field.name}`}
                        name={field.name}
                        value={values[field.name]}
                        onChange={(event) =>
                            setValues({
                                ...values,
                                [field.name]: event.target.value,
                            })
                        }
                    />
                </div>
            ))}

            <Button type="submit" size="sm" data-test="ai-form-submit">
                Propose
            </Button>
        </form>
    );
}
