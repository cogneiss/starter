import { Link } from '@inertiajs/react';
import { CheckCircle2, Circle } from 'lucide-react';
import {
    Card,
    CardContent,
    CardDescription,
    CardHeader,
    CardTitle,
} from '@/components/ui/card';

type ChecklistStep = {
    key: string;
    title: string;
    description: string;
    href: string;
    required: boolean;
    complete: boolean;
};

export type Checklist = {
    steps: ChecklistStep[];
    next: string | null;
    complete: boolean;
    dismissed: boolean;
};

/**
 * What is left to do before this organization is set up, in the order it makes
 * sense to do it. The server decides what is done; nothing here keeps state.
 */
export default function ActivationChecklist({
    checklist,
}: {
    checklist: Checklist;
}) {
    return (
        <Card>
            <CardHeader>
                <CardTitle>Get set up</CardTitle>
                <CardDescription>
                    A few things make the rest of this useful.
                </CardDescription>
            </CardHeader>
            <CardContent>
                <ol className="flex flex-col gap-3">
                    {checklist.steps.map((step) => (
                        <li key={step.key} className="flex items-start gap-3">
                            {step.complete ? (
                                <CheckCircle2
                                    aria-hidden
                                    className="mt-0.5 size-5 text-emerald-600"
                                />
                            ) : (
                                <Circle
                                    aria-hidden
                                    className="mt-0.5 size-5 text-muted-foreground"
                                />
                            )}
                            <div className="flex flex-col">
                                <Link
                                    href={step.href}
                                    className="font-medium underline-offset-4 hover:underline"
                                    aria-current={
                                        step.key === checklist.next
                                            ? 'step'
                                            : undefined
                                    }
                                >
                                    {step.title}
                                </Link>
                                <span className="text-sm text-muted-foreground">
                                    {step.description}
                                </span>
                            </div>
                        </li>
                    ))}
                </ol>
            </CardContent>
        </Card>
    );
}
