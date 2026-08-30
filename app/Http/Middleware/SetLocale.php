<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use App\Models\User;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\App;
use Symfony\Component\HttpFoundation\Response;

/**
 * Decides which language this request is answered in.
 *
 * Four sources, in the order a person would expect them: what they chose, what
 * this session was already using, what their browser asked for, and the
 * application default. Every one of them is filtered through the supported
 * list, so a stored preference from a locale that has since been dropped, or an
 * Accept-Language header naming anything at all, lands on the default rather
 * than on a half-translated screen.
 */
final readonly class SetLocale
{
    /**
     * @param  Closure(Request): (Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        $locale = $this->supported($user instanceof User ? $user->locale : null)
            ?? $this->supported($request->session()->get('locale'))
            ?? $this->firstSupported($request->getLanguages())
            ?? config()->string('app.locale');

        App::setLocale($locale);

        return $next($request);
    }

    /**
     * The first language the browser asked for that this application serves.
     *
     * In the browser's own order of preference, and no further: a header naming
     * only languages this application has no words for answers nothing, so the
     * default decides rather than the header.
     *
     * @param  array<array-key, string>  $languages
     */
    private function firstSupported(array $languages): ?string
    {
        foreach ($languages as $language) {
            $locale = $this->supported($language);

            if ($locale !== null) {
                return $locale;
            }
        }

        return null;
    }

    /**
     * The candidate, or null when it is not a locale this application serves.
     */
    private function supported(mixed $locale): ?string
    {
        return is_string($locale) && in_array($locale, $this->locales(), true)
            ? $locale
            : null;
    }

    /**
     * @return list<string>
     */
    private function locales(): array
    {
        /** @var list<string> $locales */
        $locales = config()->array('app.supported_locales');

        return $locales;
    }
}
