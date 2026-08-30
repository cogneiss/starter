---
title: Interface primitives
status: current
supersedes: []
code_refs:
    - resources/js/components/empty-state.tsx
    - resources/js/lib/empty-state-copy.ts
    - resources/js/components/confirm-dialog.tsx
    - resources/js/components/detail-drawer.tsx
    - resources/js/components/alert-error.tsx
    - resources/js/lib/http-errors.ts
    - resources/js/hooks/use-pending-patch.ts
    - app/Support/UserFriendlyExceptionRegistrar.php
    - app/Data/RecordPeekData.php
    - tests/Feature/FriendlyErrorTest.php
    - tests/Mutations/phase7-peek-scope.patch
updated: 2026-08-31
---

# Interface primitives

Four things every screen needs and every screen otherwise invents its own
version of: nothing-here, are-you-sure, something-broke, and a look at one
record without leaving the list.

## Empty states

`<EmptyState>` is the one way this application says there is nothing here, and
the copy is keyed rather than passed in at the call site
(`resources/js/lib/empty-state-copy.ts`). Two consequences worth having: the
same list reads the same way everywhere it appears, and a screen nobody wrote
copy for still says something useful instead of showing a blank panel.

Where there is one obvious next thing to do, the copy carries it as an action.
Where there is not, it says so and offers nothing.

## Confirmation

`useConfirm()` opens `<ConfirmDialog>` and resolves to the answer. Callers pass
an intent — what kind of thing is being confirmed — and not a colour, so the
dialog decides how a destructive answer looks and no call site has to remember
which button variant means "this cannot be undone".

## Errors people can read

Two halves, one wording. `App\Support\UserFriendlyExceptionRegistrar` writes the
sentence for failures the server can answer with a page;
`resources/js/lib/http-errors.ts` holds the same table for failures it cannot —
an Inertia visit that came back as something other than a page, and the plain
XHR the palette and the streaming views make. A 403 reads the same whichever
half of the application noticed it, and a 419 offers to retry rather than
blaming the reader (`tests/Browser/CsrfRetryTest.php`).

## The detail drawer

`<DetailDrawer>` is opened by the address bar — `?peek=<id>` — so a row can be
linked to, shared and reopened. It renders the `peek` page prop and nothing
else: it never fetches a record itself. Whatever scope the controller looked the
record up in is the only way one reaches the screen, so an id from another
organization is a 404 rather than a drawer with someone else's details in it.
`tests/Mutations/phase7-peek-scope.patch` is the proof that the scope, and not
the component, is what is doing the work:

```bash
bin/prove-control.sh phase7-peek-scope DetailDrawer
```

Escape closes the drawer and hands the keyboard back to the row that opened it —
see [[domains/ux-motion-and-a11y]] for the rest of the focus rules.

## Optimistic inline edits

`usePendingPatch()` keys pending state by record rather than holding one flag,
because two rows can be edited at once: each keeps its own spinner and its own
optimistic value. A refused patch drops the optimistic value, so the row returns
to whatever the server last said it was, and the reason arrives as the flash
toast the server sent — the same way every other outcome in this application is
announced.

## Related

- [[domains/ux-list-kit]] — where the drawer and the bulk confirmations are used
- [[domains/ux-motion-and-a11y]] — motion, focus and announcements
