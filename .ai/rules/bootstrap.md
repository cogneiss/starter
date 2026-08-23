---
paths:
  - 'bootstrap/**'
---

# Bootstrap

## ResolveOrganization must run before route model binding
`ResolveOrganization` is prepended to the middleware priority list in `bootstrap/app.php`. Route model binding queries organization-scoped models, so if the organization is not bound before substitution runs, every bound model lookup throws `OrganizationContextMissing`. Adding middleware to the `web` group is fine; reordering or removing that priority entry is not.
