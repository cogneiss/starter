<?php

declare(strict_types=1);

namespace App\Support\Reporting;

use App\Support\OrganizationContext;
use Illuminate\Support\Str;
use Throwable;
use WeakMap;

/**
 * The half of every reporter that is the same: minting a reference id per
 * throwable, remembering it so the 500 payload can repeat it, and building
 * the context. Only send() differs between drivers.
 */
trait ReportsErrors
{
    /**
     * @var WeakMap<Throwable, string>|null
     */
    private ?WeakMap $references = null;

    /**
     * @param  array<string, mixed>  $context
     */
    abstract protected function send(Throwable $throwable, string $reference, array $context): void;

    public function report(Throwable $throwable): string
    {
        $this->references ??= new WeakMap();

        $reference = $this->references[$throwable] ??= (string) Str::uuid();

        $this->send($throwable, $reference, $this->context());

        return $reference;
    }

    public function reference(Throwable $throwable): ?string
    {
        return $this->references !== null && isset($this->references[$throwable])
            ? $this->references[$throwable]
            : null;
    }

    /**
     * Identifiers only. The request body, the headers and every token stay
     * out on purpose: they carry credentials and personal data, and a crash
     * tracker is the last place either belongs.
     *
     * @return array{organization_id: string|null, user_id: string|null, request_id: string, release: string|null}
     */
    private function context(): array
    {
        $userId = auth()->id();
        $release = config('services.sentry.release');

        return [
            'organization_id' => resolve(OrganizationContext::class)->id(),
            'user_id' => $userId === null ? null : (string) $userId,
            'request_id' => $this->requestId(),
            'release' => is_string($release) ? $release : null,
        ];
    }

    /**
     * One id per request, minted on first use so every error in the same
     * request shares it.
     */
    private function requestId(): string
    {
        $request = request();

        $id = $request->attributes->get('request-id');

        if (! is_string($id)) {
            $id = (string) Str::uuid();
            $request->attributes->set('request-id', $id);
        }

        return $id;
    }
}
