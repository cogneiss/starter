---
paths:
    - "database/migrations/**"
    - "config/database.php"
---

# Database design

These rules bind new and changed tables.
`tests/Unit/Conventions/SchemaConventionTest.php` holds the two index rules,
the UTC pin, the ban on a time zone column type, the ban on a unique rule
that skips soft-deleted rows, the `LowercasesEmail` rule and the check
constraint on `email`. No test covers the rest, so a table being there is not
a reason to copy it.

## The design is agreed as text before a migration exists

Write the tables, columns, types, keys, indexes and delete behaviour as plain
text and get a yes before `php artisan make:migration`. The steps are in the
`database-design` skill.

## Every foreign key column has an index that leads with it

PostgreSQL does not index a foreign key column for you.
`foreignUuid('owner_id')->constrained()` adds the constraint and nothing else,
so a join or a cascading delete scans the whole table. Add `->index()`, or a
composite index whose first column is the foreign key.

## Every organization-owned table has an index that leads with `organization_id`

The fail-closed scope puts `organization_id` in every query on the table
(`wiki/architecture/fail-closed-scoping.md`). Put the columns a list filters or
sorts by after it: `$table->index(['organization_id', 'created_at'])`.

## A unique index on organization-owned data includes `organization_id`

`unique('slug')` lets one organization's value block another's, and the error
tells the second organization that the value exists. Write
`unique(['organization_id', 'slug'])`. Uniqueness across the whole table is for
tables no organization owns, such as `users`.

## A primary key is a UUID

`$table->uuid('id')->primary()`, `HasUuids` on the model, `foreignUuid()` for a
reference to it. A table a package publishes keeps the key the package gave it.

## A foreign key says what a delete does

`cascadeOnDelete()`, `nullOnDelete()` on a nullable column, or
`restrictOnDelete()`, chosen on purpose. Rows an organization owns go when the
organization goes.

## Money is an integer of micros

An amount is an integer in a `bigInteger` column whose name ends `_micros`, as
in `ai_credit_ledger`. It is never a float and never cents. One currency unit
is 1,000,000 micros.

## A fixed set of values is a string column cast to an enum

`$table->string('status')` and a backed enum in the model's casts. Not
`$table->enum()`: that fixes the allowed values in the database, and every new
value then needs a migration.

## A column is `NOT NULL` unless absence means something

Nullable is a decision: say what a null means in the design text. A flag or a
counter has a default, not a null.

## A timestamp column has no time zone and holds UTC

Use `timestamp()`, `timestamps()` and `softDeletes()`. Never a `Tz` column
method and never `timestamptz` in raw SQL: only PostgreSQL has the type, and
MySQL and SQLite build a plain timestamp for it. `config/app.php` keeps the
application in UTC and `config/database.php` pins the PostgreSQL, MySQL and
MariaDB session to UTC, so `now()` and a `useCurrent()` default agree. Change
neither. Eloquent saves a Carbon's wall-clock time and drops its zone, so call
`->utc()` on a Carbon from another zone before you save it. The pin covers the
sessions Laravel opens. A `psql` or `mysql` shell uses the server default.

## A check constraint holds a permanent invariant, never a value set

A set of values, such as a status, is a string column with an enum cast. The
set grows, and a constraint on it then needs a migration on every engine. A
rule that can never change, such as an amount that is not negative, may be a
check constraint. `Blueprint` has no method for one: run `alter table ... add
constraint ... check (...)` through `DB::statement` behind a driver check for
PostgreSQL and MySQL. SQLite cannot add one to an existing table and goes
without. `database/migrations/0001_01_01_000000_create_users_table.php` is the
pattern. Validation still states the rule, because a database error is not a
message for a person.

## A soft-deleted row keeps its unique value

A unique index on a table with `softDeletes()` stays a plain one, never a
partial index that skips deleted rows. A deleted organization still owns its
slug, so a lookup that guards a unique value reads `withTrashed()`, as
`app/Actions/CreateOrganization.php` does. A `Rule::unique` never adds
`withoutTrashed()` or `whereNull('deleted_at')`: the index would refuse the
row that validation let through. To free a value on delete, rewrite it in the
same transaction, as `app/Actions/DeleteAccount.php` does with the email.

## A unique rule for only some rows is a partial unique index with its own test

"One pending invitation per email and organization" binds the pending rows
only. That is a partial unique index on PostgreSQL and SQLite and an
expression key part on MySQL, written per driver in the migration. The gap
query does not count a partial index, so the rule gets its own test.
`tests/Unit/Models/OrganizationInvitationTest.php` is the pattern.

## An email address is stored in lower case

PostgreSQL and SQLite compare text by case. MySQL's default collation,
`utf8mb4_unicode_ci`, does not. Stored as typed, `Ada@Example.com` and
`ada@example.com` are two rows on two engines and a unique violation on the
third. So a model whose table has an `email` column uses the trait in
`app/Concerns/LowercasesEmail.php`, and the unique index on the column stays a
plain one. `tests/Unit/Conventions/SchemaConventionTest.php` fails for a model
that leaves `LowercasesEmail` out.

The trait covers an Eloquent write only. A query builder update or a
`DB::table()` write goes past it, so lower-case the value before either. The
side that compares an address is in `.ai/rules/app.md`.

A check constraint named `<table>_email_lowercase` backs the trait on
PostgreSQL and MySQL. It refuses a value that holds an ASCII capital, so a
write that goes past the model fails there. SQLite cannot add a constraint to
an existing table and has none. PostgreSQL states the rule as
`email !~ '[A-Z]'`. MySQL needs `not regexp_like(email, '[A-Z]', 'c')`:
without the `'c'` flag the default collation matches a lower-case letter too,
and `email = lower(email)` is true for every value there. Only ASCII is
covered: `É@example.com` passes the constraint, and the forms refuse a
non-ASCII address through `app/Rules/ValidEmail.php`.

A new table with an `email` column gets the same constraint in its create
migration. `database/migrations/0001_01_01_000000_create_users_table.php` is
the pattern, and `tests/Unit/Conventions/SchemaConventionTest.php` fails on
PostgreSQL for a table that has the column and no such constraint. Nothing in
the gate proves that MySQL refuses a capital: `bin/check-migrations` proves
only that the statement runs there.

The column stays a `string`: no `citext`, no index on `lower(email)` and no
collation change, because each of those ties the rule to one engine.

Rows written before this rule are not converted. On PostgreSQL and SQLite a
row that still holds a capital stops matching a sign-in until it is
lower-cased. `select count(*) from users where email <> lower(email)` counts
them. The check constraint is created with the table, so a database migrated
before it existed has none until it is rebuilt.

## Every migration runs on PostgreSQL, SQLite and MySQL

`bin/check-migrations` runs every migration up, down and up again on all
three, and `composer verify` runs it. It skips MySQL when no server answers,
and `--strict` fails instead. The test suite runs on PostgreSQL,
`composer test:sqlite` reruns it on SQLite, and nothing runs it on MySQL. So
use the schema builder. A statement one engine does not understand goes behind
a driver check, the way
`database/migrations/2026_08_25_090000_create_ai_documents_table.php` uses
`getDriverName() === 'pgsql'`. MariaDB and SQL Server are not checked.

Where the three differ:

- MySQL indexes a foreign key column itself. PostgreSQL and SQLite do not, so
  declare the index in the migration.
- In `down()`, drop a foreign key before the index or column it sits on. MySQL
  refuses the other order.
- Name an index yourself when the generated name passes 63 characters.
  PostgreSQL silently cuts the name to 63 and MySQL refuses one over 64.
- A unique rule for only some rows is a partial unique index on PostgreSQL and
  SQLite and an expression key part on MySQL.
  `database/migrations/2026_08_23_102612_create_organization_invitations_table.php`
  has both.
- A column type change PostgreSQL cannot cast by itself names the cast with
  `->using()`.
- PostgreSQL runs a migration in a transaction. MySQL and SQLite do not, so a
  migration that fails halfway there stays half applied.

## A migration has a `down()` that reverses its `up()`

A rollback is how a bad deploy is undone. If a change cannot be reversed, say
so in the design text before you write it.

## Stop and ask before any of these

The owner has not decided these. Do not settle one inside a migration: ask,
and name what each answer costs.

- An engine other than PostgreSQL, SQLite and MySQL, such as MariaDB or SQL
  Server.
- A change to an existing migration. A change is a new migration.
