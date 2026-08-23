export type BrowserSession = {
    id: string;
    device: string;
    ip_address: string | null;
    last_active_diff: string;
    current: boolean;
};
export type Impersonator = {
    id: string;
    name: string;
};
export type KnownFeatures = 'impersonation-enabled' | 'social-login-enabled';
export type LoginHistoryEntry = {
    id: string;
    device: string;
    ip_address: string | null;
    successful: boolean;
    created_at_diff: string;
};
export type MembershipStatus = 'active' | 'suspended';
export type Organization = {
    id: string;
    name: string;
    slug: string;
    personal: boolean;
    require_two_factor: boolean;
};
export type OrganizationInvitation = {
    id: string;
    email: string;
    role: string;
    expires_at: string;
};
export type OrganizationMember = {
    id: string;
    name: string;
    email: string;
    status: MembershipStatus;
    role: string | null;
};
export type Passkey = {
    id: number;
    name: string;
    authenticator: string | null;
    created_at_diff: string | null;
    last_used_at_diff: string | null;
};
export type User = {
    id: string;
    name: string;
    email: string;
    email_verified_at: string | null;
    two_factor_enabled: boolean;
    created_at: string;
    updated_at: string;
};
