---
name: database-design
description: "Use before any schema decision in this starter kit: planning a new table or model, adding or changing a column, key, index or foreign key, writing or reviewing a migration, or deciding how to store money, a status or an organization-owned record. Covers reading the live schema first, showing the design as text and getting a yes before the first migration, the index rules for foreign keys and organization_id, integer micros for money, migrations that run on PostgreSQL, SQLite and MySQL, changing a table that already has rows, and the catalog query that lists index gaps. A wrong table passes every test and gets expensive once it holds rows, so load this at plan time, not after the migration is written."
license: MIT
metadata:
    author: cogneiss
---

# Database design

A wrong table does not fail a test. It goes through review as a diff nobody
read closely, fills with rows, and is expensive to change after that. So the
design is agreed as text first, and the result is checked against the database
catalog, not against what the migration files seem to say.

The rules are in `.ai/rules/database.md`; this skill is the order to work in.
For schema work, both take precedence over `laravel-best-practices`.

## 1. Read the live schema

Start from the database, not from memory and not from the migration files.

- Boost `database-schema` for each table you will change or reference: columns,
  types, nullability, indexes, foreign keys.
- `php artisan migrate:status` to see that nothing is pending. A pending
  migration means the database is behind the files.
- Read the migration and model of the nearest similar table, then hold it
  against the rules before you copy it. Older tables predate the rules.

## 2. Write the design as text and wait for a yes

Post the design in the conversation and get a yes before the first migration.
Do not run `php artisan make:migration` until the person has answered.

One block per table, in this shape:

```text
projects                 organization-owned
  id                uuid        primary key
  organization_id   uuid        not null, references organizations, cascade on delete
  owner_id          uuid        null, references users, set null on delete
  name              string      not null
  status            string      not null, default 'draft', cast to ProjectStatus
  budget_micros     bigInteger  not null, default 0
  created_at, updated_at
  unique  (organization_id, name)     also the index that leads with organization_id
  index   (owner_id)
```

Under it, one line each:

- what reads the table, and by which columns (the indexes exist for these);
- what happens to the rows when a parent row is deleted;
- anything from step 7 the design depends on.

A yes covers the text it answered. Post a changed design again.

## 3. Decide keys, columns and indexes

Take the design through `.ai/rules/database.md` in this order. Each answer
feeds the next one.

- **Owner.** A table an organization owns gets `organization_id`, and its model
  gets `BelongsToOrganization`. Load `org-access` for the scope.
- **Keys.** The primary key, then for every reference what a delete does.
- **Columns.** Type, `NOT NULL` or not, default. Money and a fixed set of
  values have their own rules.
- **Uniqueness.** What must never appear twice, and inside which organization.
- **Indexes, last.** Build them from the reads you listed in step 2. One
  composite index can cover a foreign key and `organization_id` at once.

## 4. Write the migration

- `php artisan make:migration create_projects_table --no-interaction`. One
  change, one new file.
- Use the schema builder. `bin/check-migrations` runs every migration on
  PostgreSQL, SQLite and MySQL, so a statement one engine does not understand
  goes behind the driver check the rule file shows.
- `foreignUuid('organization_id')->constrained()` creates no index on
  PostgreSQL. Add the index in the same migration.
- Write `down()` so that it reverses `up()`.
- Then the model: `HasUuids`, casts, the scope trait, a factory. Load
  `resource-spine` for the rest of the resource.
- Run `php artisan migrate`, then `bin/check-migrations`, then the tests for
  what changed.

## 5. Change a table that has rows

Write a new migration for every change. A table that holds data needs more:

- A new `NOT NULL` column needs a default. Without one, add it nullable,
  backfill it, then tighten it in a later migration.
- A rename or a drop breaks the code that is still running during a deploy.
  Add the new column, write to both, move the readers, drop in a later release.
- Building an index blocks writes to the table until it finishes. Say so in
  the design text and let the person pick the time.
- A narrower type or a new unique index fails on rows that already break it.
  Count those rows first with Boost `database-query` and show the number.

## 6. Before you call it done

Run this with Boost `database-query` after `php artisan migrate`. It reads the
PostgreSQL catalog, so it reports what the database has, whatever the migration
files say.

```sql
SELECT gap, table_name, column_name FROM (
SELECT 'foreign key column has no index that leads with it' AS gap, c.conrelid::regclass::text AS table_name, a.attname::text AS column_name
FROM pg_constraint c
JOIN pg_attribute a ON a.attrelid = c.conrelid AND a.attnum = c.conkey[1]
WHERE c.contype = 'f' AND c.connamespace = 'public'::regnamespace AND array_length(c.conkey, 1) = 1
  AND NOT EXISTS (SELECT 1 FROM pg_index i WHERE i.indrelid = c.conrelid AND i.indkey[0] = c.conkey[1] AND i.indpred IS NULL)
UNION ALL
SELECT 'organization table has no index that leads with organization_id', a.attrelid::regclass::text, a.attname::text
FROM pg_attribute a
JOIN pg_class t ON t.oid = a.attrelid AND t.relkind = 'r' AND t.relnamespace = 'public'::regnamespace
WHERE a.attname = 'organization_id' AND NOT a.attisdropped
  AND NOT EXISTS (SELECT 1 FROM pg_index i WHERE i.indrelid = a.attrelid AND i.indkey[0] = a.attnum AND i.indpred IS NULL)
) g ORDER BY 1, 2, 3
```

The query returns no row. `tests/Unit/Conventions/SchemaConventionTest.php`
runs this block and fails the gate on any row, so a row means a missing index,
not an exception to record.

In this query an index with a WHERE clause does not count as cover, because it
skips rows.

Then tell the person what exists now: the tables, the indexes, and that the
query returns no row for them.

## 7. Stop and ask

`.ai/rules/database.md` ends with the choices the owner has not made. When the
design needs one, stop before the migration and ask one question that names the
table and what each answer costs.

- A time zone on a timestamp: ask whether readers in different zones see the
  value, and say that the other timestamps in the kit carry no zone.
- A check constraint: ask whether the rule belongs in the database or in
  validation, and say that a rule in the database also binds imports and jobs.
- A soft delete: ask whether a deleted row's unique value may be used again.
- A partial unique index: ask first, because it is what the soft delete answer
  usually leads to and the gap query above does not count it.
- An engine other than PostgreSQL, SQLite and MySQL (MariaDB, SQL Server): ask
  who will run the migrations and the suite on it. `bin/check-migrations` does
  not, and the gap query is written for PostgreSQL.
- An existing migration: ask whether any database has already run it. If one
  has, the change is a new migration.
