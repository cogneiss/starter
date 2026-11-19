<?php

declare(strict_types=1);

use PhpParser\Node;
use PhpParser\Node\Arg;
use PhpParser\Node\Expr;
use PhpParser\Node\Expr\BinaryOp\Concat;
use PhpParser\Node\Expr\ClassConstFetch;
use PhpParser\Node\Expr\ConstFetch;
use PhpParser\Node\Expr\FuncCall;
use PhpParser\Node\Expr\MethodCall;
use PhpParser\Node\Expr\StaticCall;
use PhpParser\Node\Identifier;
use PhpParser\Node\InterpolatedStringPart;
use PhpParser\Node\Name;
use PhpParser\Node\Scalar\Int_;
use PhpParser\Node\Scalar\InterpolatedString;
use PhpParser\Node\Scalar\String_;
use PhpParser\NodeFinder;
use PhpParser\ParserFactory;
use Symfony\Component\Finder\Finder;

/**
 * Raw SQL methods take the query text as their first argument. Text built from
 * a variable by concatenation, interpolation or sprintf puts that variable in
 * the query, so it has to travel as a binding instead: `whereRaw('a = ?', [$a])`.
 * A bare variable is not flagged, because it may be a fixed expression chosen in
 * code (ResourceQuery builds one that way); only building text from parts is.
 *
 * Limit: the check reads the argument itself, so a string assembled on an
 * earlier line and passed in by name is not followed.
 */
const RAW_SQL_FACADE_METHODS = ['select', 'selectOne', 'scalar', 'statement', 'insert', 'update', 'delete', 'unprepared', 'raw', 'affectingStatement'];

const RAW_SQL_BUILDER_METHODS = ['whereRaw', 'orWhereRaw', 'selectRaw', 'havingRaw', 'orHavingRaw', 'orderByRaw', 'groupByRaw', 'fromRaw', 'joinRaw'];

/**
 * Whether the expression is a fixed string or number, however it is written.
 */
function isFixedSqlText(Expr $expr): bool
{
    return match (true) {
        $expr instanceof String_, $expr instanceof Int_, $expr instanceof ConstFetch, $expr instanceof ClassConstFetch => true,
        $expr instanceof Concat => isFixedSqlText($expr->left) && isFixedSqlText($expr->right),
        $expr instanceof InterpolatedString => collect($expr->parts)->every(fn (Node $part): bool => $part instanceof InterpolatedStringPart),
        default => false,
    };
}

/**
 * Whether the expression builds query text out of something that is not fixed.
 */
function buildsSqlFromParts(Expr $expr): bool
{
    if (isFixedSqlText($expr)) {
        return false;
    }

    if ($expr instanceof Concat || $expr instanceof InterpolatedString) {
        return true;
    }

    return $expr instanceof FuncCall
        && $expr->name instanceof Name
        && in_array($expr->name->toLowerString(), ['sprintf', 'vsprintf'], true)
        && collect($expr->getArgs())->skip(1)->contains(fn (Arg $arg): bool => ! isFixedSqlText($arg->value));
}

/**
 * @return list<string> one `label:line` per raw SQL call that builds its text from parts
 */
function rawSqlBuiltFromParts(string $label, string $source): array
{
    $statements = (new ParserFactory)->createForNewestSupportedVersion()->parse($source) ?? [];

    $calls = (new NodeFinder)->find($statements, function (Node $node): bool {
        if ($node instanceof StaticCall) {
            return $node->class instanceof Name
                && $node->class->getLast() === 'DB'
                && $node->name instanceof Identifier
                && in_array($node->name->toString(), RAW_SQL_FACADE_METHODS, true);
        }

        return $node instanceof MethodCall
            && $node->name instanceof Identifier
            && in_array($node->name->toString(), RAW_SQL_BUILDER_METHODS, true);
    });

    return collect($calls)
        ->filter(fn (StaticCall|MethodCall $call): bool => isset($call->getArgs()[0]) && buildsSqlFromParts($call->getArgs()[0]->value))
        ->map(fn (StaticCall|MethodCall $call): string => $label.':'.$call->getStartLine())
        ->values()
        ->all();
}

it('passes bindings and fixed query text', function (): void {
    expect(rawSqlBuiltFromParts('safe', <<<'PHP'
    <?php
    DB::select('select * from users where name = ?', [$name]);
    DB::select("select 1 where a = 'b' and c = ".self::LIMIT);
    $query->whereRaw('lower(name) = ?', [$name]);
    $query->whereRaw($this->clause($column), [$bound]);
    $query->selectRaw(sprintf('count(*) as %s', 'total'));
    PHP))->toBe([]);
});

it('flags query text built from variables', function (): void {
    $source = (string) file_get_contents(base_path('tests/Fixtures/RawSql/injectable-queries.php.txt'));

    expect(rawSqlBuiltFromParts('fixture', $source))
        ->toBe(['fixture:7', 'fixture:8', 'fixture:9', 'fixture:10', 'fixture:11']);
});

it('builds no raw SQL from request data anywhere in the application', function (): void {
    $files = Finder::create()->files()->name('*.php')->in(array_map(base_path(...), ['app', 'bootstrap', 'config', 'database', 'routes']));

    $violations = [];

    foreach ($files as $file) {
        $violations = [...$violations, ...rawSqlBuiltFromParts(str_replace(base_path().'/', '', $file->getPathname()), $file->getContents())];
    }

    expect($files->count())->toBeGreaterThan(100)
        ->and($violations)->toBe([], 'These raw SQL calls build their text from non-literal parts; pass the values as bindings instead: '.implode(', ', $violations));
});
