<?php

declare(strict_types=1);

namespace App\Support\Health\Checks;

use Illuminate\Support\Facades\DB;
use Spatie\Health\Checks\Check;
use Spatie\Health\Checks\Result;

final class DatabaseCheck extends Check
{
    protected ?string $name = 'database';

    /**
     * A query, not a connection. The package's own check stops at `getPdo()`,
     * which a pooler such as PgBouncer or RDS Proxy answers with no database
     * behind it, and which a long-lived worker answers from a handle it opened
     * earlier. The failure message is fixed so a driver error can never carry
     * a host or a credential into the response.
     */
    public function run(): Result
    {
        return rescue(static function (): Result {
            DB::connection()->select('select 1');

            return Result::make()->ok();
        }, Result::make()->failed('The database did not answer a query.'), report: false);
    }
}
