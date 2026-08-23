import { Head } from '@inertiajs/react';
import { BooleanPill } from '@/components/value/boolean-pill';
import { CodeValue } from '@/components/value/code-value';
import { DateValue } from '@/components/value/date-value';
import { EmailValue } from '@/components/value/email-value';
import { EmptyValue } from '@/components/value/empty-value';
import { LongText } from '@/components/value/long-text';
import { Money } from '@/components/value/money';
import { Percent } from '@/components/value/percent';
import { PhoneValue } from '@/components/value/phone-value';
import { RelativeTime } from '@/components/value/relative-time';
import { StatusBadge } from '@/components/value/status-badge';
import { TagList } from '@/components/value/tag-list';
import { UrlValue } from '@/components/value/url-value';

/**
 * Every value component with a value and with nothing, on one page. It is the
 * reference for what each one looks like, and what the browser tests read.
 */
export default function ValueGallery({ now }: { now: string }) {
    const then = new Date(new Date(now).getTime() - 3 * 24 * 60 * 60 * 1000);

    return (
        <div className="flex flex-col gap-4 p-8">
            <Head title="Value gallery" />

            <section data-test="with-value" className="flex flex-col gap-2">
                <Money amount={1234.5} currency="USD" locale="en-US" />
                <Percent value={0.125} signed locale="en-US" />
                <DateValue value={then} locale="en-US" />
                <DateValue value={then} withTime locale="en-US" />
                <RelativeTime value={then} now={new Date(now)} locale="en-US" />
                <BooleanPill value={true} />
                <BooleanPill value={false} />
                <StatusBadge status="Suspended" />
                <EmailValue email="taylor@example.com" />
                <UrlValue url="example.com/pricing" />
                <PhoneValue phone="+1 (555) 010-9999" />
                <TagList tags={['alpha', 'beta', 'gamma', 'delta']} max={2} />
                <CodeValue value="org_01H9" />
                <LongText text="A description long enough to need clamping." />
            </section>

            <section data-test="without-value" className="flex flex-col gap-2">
                <Money amount={null} />
                <Percent value={null} />
                <DateValue value={null} />
                <RelativeTime value={null} />
                <BooleanPill value={null} />
                <StatusBadge status={null} />
                <EmailValue email={null} />
                <UrlValue url={null} />
                <PhoneValue phone={null} />
                <TagList tags={null} />
                <CodeValue value={null} />
                <LongText text={null} />
                <EmptyValue />
            </section>
        </div>
    );
}
