<?php

declare(strict_types=1);

namespace App\Providers;

use Spatie\LaravelTypeScriptTransformer\TypeScriptTransformerApplicationServiceProvider as BaseTypeScriptTransformerServiceProvider;
use Spatie\TypeScriptTransformer\Transformers\AttributedClassTransformer;
use Spatie\TypeScriptTransformer\Transformers\EnumTransformer;
use Spatie\TypeScriptTransformer\TypeScriptTransformerConfigFactory;
use Spatie\TypeScriptTransformer\Writers\FlatModuleWriter;

/**
 * Turns every `#[TypeScript]` class in `app/` into a TypeScript type.
 *
 * The output is a single module written to `resources/js/types/generated.d.ts`
 * and re-exported from `resources/js/types/index.ts`. It is committed on
 * purpose so drift shows up in review rather than only in CI.
 */
final class TypeScriptTransformerServiceProvider extends BaseTypeScriptTransformerServiceProvider
{
    public function configure(TypeScriptTransformerConfigFactory $config): void
    {
        $config
            ->transformer(AttributedClassTransformer::class)
            ->transformer(EnumTransformer::class)
            ->transformDirectories(app_path())
            ->outputDirectory(resource_path('js/types'))
            ->writer(new FlatModuleWriter('generated.d.ts'));
    }
}
