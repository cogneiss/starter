export type AiAuditStatus = 'ok' | 'blocked';
export type AiBlockType = 'text' | 'markdown' | 'table' | 'list' | 'metric' | 'form' | 'confirm';
export type AiConfirmBlock = {
type: AiBlockType,
summary: string,
expires_at: string,
token: string,
};
export type AiConfirmToken = {
id: string,
action: string,
summary: string,
expires_at: string,
};
export type AiFormBlock = {
type: AiBlockType,
summary: string,
fields: AiFormField[],
action: string,
};
export type AiFormField = {
name: string,
value: string,
};
export type AiListBlock = {
type: AiBlockType,
items: string[],
ordered: boolean,
};
export type AiMarkdownBlock = {
type: AiBlockType,
html: string,
markdown: string,
};
export type AiMetricBlock = {
type: AiBlockType,
label: string,
value: string,
delta: string | null,
trend: AiMetricTrend | null,
};
export type AiMetricTrend = 'up' | 'down' | 'flat';
export type AiTableBlock = {
type: AiBlockType,
columns: string[],
rows: string[][],
};
export type AiTextBlock = {
type: AiBlockType,
text: string,
};
export type AiUsage = {
since: string,
runs: number,
tokens: number,
cost_micros: number,
blocked: number,
agents: AiUsageRow[],
tiers: AiUsageRow[],
};
export type AiUsageRow = {
name: string,
runs: number,
tokens: number,
cost_micros: number,
};
export type BrowserSession = {
id: string,
device: string,
ip_address: string | null,
last_active_diff: string,
current: boolean,
};
export type FilterType = 'select' | 'multi-select' | 'boolean' | 'range' | 'date-range';
export type Impersonator = {
id: string,
name: string,
};
export type InviteMember = {
email: string,
role: string,
};
export type KnownFeatures = 'ai-briefing-enabled' | 'impersonation-enabled' | 'social-login-enabled';
export type LoginHistoryEntry = {
id: string,
device: string,
ip_address: string | null,
successful: boolean,
created_at_diff: string,
};
export type MembershipStatus = 'active' | 'suspended';
export type Organization = {
id: string,
name: string,
slug: string,
personal: boolean,
require_two_factor: boolean,
};
export type OrganizationInvitation = {
id: string,
email: string,
role: string,
expires_at: string,
};
export type OrganizationMember = {
id: string,
name: string,
email: string,
status: MembershipStatus,
role: string | null,
};
export type Passkey = {
id: number,
name: string,
authenticator: string | null,
created_at_diff: string | null,
last_used_at_diff: string | null,
};
export type ResourceFilter = {
key: string,
label: string,
type: FilterType,
options: ResourceFilterOption[],
value: string | boolean | Record<string, string | number> | Array<string> | null,
};
export type ResourceFilterOption = {
value: string,
label: string,
count: number,
};
export type ResourceList = {
rows: Array<unknown>,
total: number,
pages: number,
query: ResourceQuery,
filters: ResourceFilter[],
};
export type ResourceQuery = {
q: string,
sort: string | null,
dir: 'asc' | 'desc',
page: number,
per: number,
filters: Record<string, string | boolean | Record<string, string | number> | Array<string>>,
};
export type SearchGroup = {
key: string,
label: string,
results: SearchResult[],
};
export type SearchResult = {
label: string,
description: string | null,
url: string,
};
export type User = {
id: string,
name: string,
email: string,
email_verified_at: string | null,
two_factor_enabled: boolean,
created_at: string,
updated_at: string,
};
