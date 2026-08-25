# AI layer guidelines

- The AI layer lives in `app/Ai` (agents, tools, blocks, confirmable actions, middleware) and `app/Mcp`. Read `.ai/rules/ai.md` and load the `ai-layer` skill before editing any of it.
- Tools read; they never write. No `DB` facade, no `ConsumeConfirmToken`. `App\Ai\Tools\ProposeAction` is the one exemption and it writes only the proposal. `tests/Unit/ArchTest.php` fails the build otherwise.
- Every tool calls `authorizeFor()` on the acting user before returning anything. A tool is a new caller of the existing policies, not an exemption from them.
- Writes go through propose-then-confirm: register the action in `app/Ai/ConfirmableActions.php`, return a proposal, and let a human spend the single-use token at `POST ai/confirm/{token}`. `ConsumeConfirmToken` stamps `consumed_at` in the same transaction as the write, so a replayed token is refused.
- Fence untrusted input with `App\Support\UntrustedContent::fence()` before it reaches a model. Never concatenate a record's field into an instruction string.
- Never write a model-name literal in an agent. Pick a tier — `cheap` or `smart` — and let `config/ai.php` map it to a provider and model.
- No real provider call in the blocking suite. `tests/Pest.php` fakes every agent under `app/Ai/Agents` with `preventStrayPrompts()`; `tests/Evals/` is the only exception and it is excluded from `composer test`.
- Agents implement `App\Ai\Contracts\OrganizationScoped` and `Laravel\Ai\Contracts\HasMiddleware`, and never write.
- `laravel/mcp` arrives vendored through `laravel/boost` at 0.9.4. Do not require it and do not bump it.
- Never print, log or commit a provider key. `.env.example` keeps them blank.
