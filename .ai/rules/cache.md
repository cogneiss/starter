---
paths:
    - "config/cache.php"
    - "config/database.php"
    - "config/queue.php"
    - "config/session.php"
    - "routes/console.php"
---

# Cache and Redis

The default cache store is `database` and `redis` is one `.env` line away, so every rule here holds on both. `tests/Unit/Conventions/CacheConventionTest.php` checks the ones a parser can see.

## Every cache write names a finite expiry
Pass the expiry to `Cache::put`, `Cache::add` and `Cache::remember`. Never `forever`, `rememberForever`, `sear` or a `put` with no expiry. On Redis that is a key with no time to live, and it stays until someone deletes it. A value that has to outlive a deploy belongs in a table.

## An organization's data is cached under `$organization->cacheKey('name')`
`App\Models\Organization::cacheKey()` puts the organization id in the key. A key that joins an organization id inside the cache call fails the convention test. The test does not follow a key built on an earlier line, and it cannot see a key with no organization id at all, which serves one organization's value to the next one. Check both by eye in review. A key that is not organization data (`magic-link:<token>`, `health:schedule`) is a fixed prefix plus its own id.

The API rate limiter keys (`api:org:`, `api:token:`) are written in `app/Http/Middleware/ApplyRateTier.php` and read in `app/Actions/SummarizeApiUsage.php`. Change both or neither.

## A cached value can vanish at any time
Read through `remember`, or handle the miss. A miss must fail closed: a missing magic-link token refuses the login. The row is the record and the cache is a copy, so Redis never holds the only copy of something the business needs.

## No cache tags
`Cache::tags()` throws on the `database` store, which is the default. Put the scope in the key.

## Cache, locks and queues use three Redis databases
`php artisan cache:clear` runs `FLUSHDB` on the cache connection, and `php artisan cache:clear --locks` runs it on the lock connection. `FLUSHDB` empties the whole logical database and ignores the key prefix. So the cache store uses the `cache` connection (database 1), locks use `locks` (database 2), queues use `default` (database 0), and nothing else writes to the first two. The store names both connections in `config/cache.php`, and `config/database.php` defines them. The convention test fails when two of the three share a database.

## Prefixes are set before the first deploy
`REDIS_PREFIX` and `CACHE_PREFIX` default to a slug of `APP_NAME`. Two applications, or two environments of one application, that share a Redis server need different prefixes and different database numbers. With a shared database number, one `cache:clear` empties both. Changing a prefix later orphans every cached value and queued job stored under the old one.

## No `KEYS`, `FLUSHALL`, `FLUSHDB` or `Cache::flush()` from application code
`KEYS` walks the whole keyspace in one blocking call. `Cache::flush()` and `Artisan::call('cache:clear')` are `FLUSHDB` under another name. Delete by exact key, or let the expiry do it.

## The eviction policy belongs to the whole instance
`maxmemory-policy` is one setting for a Redis instance, not one per database. An `allkeys-*` policy may evict any key, and a rate-limit counter or a lock that disappears fails open. Keep `noeviction` on an instance that holds locks, queues or sessions. Give the cache its own instance before choosing an evicting policy.
