---
paths:
    - "database/migrations/**"
    - "config/database.php"
---

# Database design

These rules bind new and changed tables.
`tests/Unit/Conventions/SchemaConventionTest.php` holds every single-column
foreign key and every `organization_id` column to the two index rules. No test
covers the other rules, so a table being there is not a reason to copy it.

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

- A timestamp that stores a time zone (`timestampTz`, `timestampsTz`).
- A check constraint.
- A soft delete on a table that has a unique index.
- A partial unique index.
- An engine other than PostgreSQL, SQLite and MySQL, such as MariaDB or SQL
  Server.
- A change to an existing migration. A change is a new migration.
