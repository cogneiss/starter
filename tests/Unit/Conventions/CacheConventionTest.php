<?php

declare(strict_types=1);

use PhpParser\Node;
use PhpParser\Node\Expr;
use PhpParser\Node\Expr\Array_;
use PhpParser\Node\Expr\BinaryOp\Concat;
use PhpParser\Node\Expr\ConstFetch;
use PhpParser\Node\Expr\FuncCall;
use PhpParser\Node\Expr\MethodCall;
use PhpParser\Node\Expr\PropertyFetch;
use PhpParser\Node\Expr\StaticCall;
use PhpParser\Node\Expr\Variable;
use PhpParser\Node\Identifier;
use PhpParser\Node\Name;
use PhpParser\Node\Scalar\InterpolatedString;
use PhpParser\Node\Scalar\String_;
use PhpParser\NodeFinder;
use PhpParser\ParserFactory;
use Symfony\Component\Finder\Finder;

/**
 * The cache rules in `.ai/rules/cache.md` that a parser can see: a cache write
 * names a finite expiry, application code never flushes the cache or scans
 * Redis keys, and an organization's cache key comes from `cacheKey()` rather
 * than from its id spelled out by hand.
 *
 * Limit: the check reads each call where it is written. A facade held in a
 * variable (`$store = Cache::store('redis'); $store->forever(...)`), an expiry
 * passed as a variable that happens to be null, and a key assembled on an
 * earlier line and passed in by name are not followed. A key that leaves out
 * the organization id altogether cannot be seen and is a review item.
 */
const CACHE_BANNED_METHODS = ['forever', 'rememberforever', 'sear', 'tags', 'flush'];

const CACHE_WRITES_NEEDING_EXPIRY = ['put', 'set', 'add'];

const REDIS_BANNED_METHODS = ['keys', 'flushall', 'flushdb'];

/**
 * Which service a call belongs to, looking through a chain to its root:
 * `Cache::store('redis')->put()` is a cache call, `Redis::connection()->keys()`
 * a Redis call, `$collection->keys()` neither.
 */
function cacheCallTarget(Node $call): ?string
{
    $root = $call;

    while ($root instanceof MethodCall) {
        $root = $root->var;
    }

    if ($root instanceof StaticCall && $root->class instanceof Name) {
        return match ($root->class->getLast()) {
            'Cache' => 'cache',
            'Redis' => 'redis',
            default => null,
        };
    }

    return $root instanceof FuncCall && $root->name instanceof Name && $root->name->toLowerString() === 'cache' ? 'cache' : null;
}

function isNullLiteral(Expr $expr): bool
{
    return $expr instanceof ConstFetch && $expr->name->toLowerString() === 'null';
}

/**
 * Whether the expression names an organization id: `->organization_id`,
 * `->organization->id`, `$organization->id` or `$organizationId`.
 */
function mentionsOrganizationId(Node $node): bool
{
    return (new NodeFinder)->findFirst($node, function (Node $inner): bool {
        if ($inner instanceof Variable) {
            return $inner->name === 'organizationId';
        }

        if (! $inner instanceof PropertyFetch || ! $inner->name instanceof Identifier) {
            return false;
        }

        $property = $inner->name->toString();

        return $property === 'organization_id'
            || ($property === 'id' && (
                ($inner->var instanceof Variable && $inner->var->name === 'organization')
                || ($inner->var instanceof PropertyFetch && $inner->var->name instanceof Identifier && $inner->var->name->toString() === 'organization')
            ));
    }) instanceof Node;
}

/**
 * @return list<string> one `label:line: reason` per call that breaks a cache rule
 */
function cacheRuleViolations(string $label, string $source): array
{
    $statements = (new ParserFactory)->createForNewestSupportedVersion()->parse($source) ?? [];
    $violations = [];

    $report = function (Node $node, string $reason) use ($label, &$violations): void {
        $violations[] = $label.':'.$node->getStartLine().': '.$reason;
    };

    foreach ((new NodeFinder)->find($statements, fn (Node $node): bool => $node instanceof StaticCall || $node instanceof MethodCall || $node instanceof FuncCall) as $call) {
        if ($call->isFirstClassCallable()) {
            continue;
        }

        $args = $call->getArgs();

        if ($call instanceof FuncCall) {
            if ($call->name instanceof Name && $call->name->toLowerString() === 'cache' && isset($args[0]) && $args[0]->value instanceof Array_ && ! isset($args[1])) {
                $report($call, 'cache([...]) without an expiry');
            }

            continue;
        }

        if (! $call->name instanceof Identifier) {
            continue;
        }

        $method = $call->name->toLowerString();

        if ($call instanceof StaticCall && $call->class instanceof Name && $call->class->getLast() === 'Artisan' && $method === 'call') {
            if (isset($args[0]) && $args[0]->value instanceof String_ && str_starts_with($args[0]->value->value, 'cache:clear')) {
                $report($call, 'cache:clear from code');
            }

            continue;
        }

        $target = cacheCallTarget($call);

        if ($target === 'redis' && in_array($method, REDIS_BANNED_METHODS, true)) {
            $report($call, "Redis {$method}");
        }

        if ($target !== 'cache') {
            continue;
        }

        if (in_array($method, CACHE_BANNED_METHODS, true)) {
            $report($call, "cache {$method}");
        }

        if (in_array($method, CACHE_WRITES_NEEDING_EXPIRY, true) && (! isset($args[2]) || isNullLiteral($args[2]->value))) {
            $report($call, "cache {$method} without a finite expiry");
        }

        if ($method === 'remember' && isset($args[1]) && isNullLiteral($args[1]->value)) {
            $report($call, 'cache remember with a null expiry');
        }

        if (isset($args[0]) && ($args[0]->value instanceof Concat || $args[0]->value instanceof InterpolatedString) && mentionsOrganizationId($args[0]->value)) {
            $report($call, 'organization id spelled out in a cache key; use cacheKey()');
        }
    }

    return $violations;
}

dataset('cache calls that break the rules', [
    'bad: forever' => "<?php Cache::forever('a', 1);",
    'bad: rememberForever' => "<?php Cache::rememberForever('a', fn () => 1);",
    'bad: sear' => "<?php Cache::sear('a', fn () => 1);",
    'bad: cache() helper forever' => "<?php cache()->forever('a', 1);",
    'bad: put without expiry' => "<?php Cache::put('a', 1);",
    'bad: set without expiry' => "<?php Cache::set('a', 1);",
    'bad: add without expiry' => "<?php Cache::add('a', 1);",
    'bad: put with null expiry' => "<?php Cache::put('a', 1, null);",
    'bad: remember with null expiry' => "<?php Cache::remember('a', null, fn () => 1);",
    'bad: cache() array without expiry' => "<?php cache(['a' => 1]);",
    'bad: store chain put without expiry' => "<?php Cache::store('redis')->put('a', 1);",
    'bad: tags' => "<?php Cache::tags(['a'])->get('a');",
    'bad: flush' => '<?php Cache::flush();',
    'bad: cache:clear from code' => "<?php Artisan::call('cache:clear --locks');",
    'bad: Redis keys' => "<?php Redis::keys('*');",
    'bad: Redis flushall' => '<?php Redis::flushAll();',
    'bad: Redis flushdb' => '<?php Redis::FLUSHDB();',
    'bad: Redis connection chain flushdb' => "<?php Redis::connection('cache')->flushdb();",
    'bad: organization id concatenated' => "<?php Cache::remember('x:'.\$user->organization_id, 60, fn () => 1);",
    'bad: organization id interpolated' => '<?php Cache::remember("x:{$organization->id}", 60, fn () => 1);',
    'bad: organizationId variable' => "<?php Cache::get('x:'.\$organizationId);",
]);

dataset('cache calls that follow the rules', [
    'good: put with expiry' => "<?php Cache::put('a', 1, now()->addMinute()); cache()->add('b', 1, 60);",
    'good: Storage put' => "<?php Storage::put('a', 'b');",
    'good: collection keys' => '<?php $collection->keys();',
    'good: cacheKey helper' => "<?php Cache::remember(\$organization->cacheKey('x'), now()->addHour(), fn () => 1);",
]);

it('flags a cache call that breaks a rule', function (string $source): void {
    expect(cacheRuleViolations('sample', $source))->not->toBeEmpty();
})->with('cache calls that break the rules');

it('passes a call that follows the cache rules', function (string $source): void {
    expect(cacheRuleViolations('sample', $source))->toBe([]);
})->with('cache calls that follow the rules');

it('application code follows the cache rules', function (): void {
    $files = Finder::create()->files()->name('*.php')->in(array_map(base_path(...), ['app', 'routes']));

    $violations = [];

    foreach ($files as $file) {
        $violations = [...$violations, ...cacheRuleViolations(str_replace(base_path().'/', '', $file->getPathname()), $file->getContents())];
    }

    expect($files->count())->toBeGreaterThan(100)
        ->and($violations)->toBe([], 'These calls break the cache rules in .ai/rules/cache.md: '.implode('; ', $violations));
});

it('cache, locks and queues use three Redis databases', function (): void {
    $database = fn (string $connection): string => (string) config("database.redis.{$connection}.database");

    $databases = [
        'cache' => $database((string) config('cache.stores.redis.connection')),
        'locks' => $database((string) config('cache.stores.redis.lock_connection')),
        'queue' => $database((string) config('queue.connections.redis.connection')),
    ];

    expect(array_unique($databases))->toHaveCount(3, 'The cache, its locks and the queue need their own Redis database, because cache:clear empties a whole one: '.json_encode($databases));
});
