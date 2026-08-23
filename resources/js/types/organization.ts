export type Organization = {
    id: string;
    name: string;
    slug: string;
    personal: boolean;
};

export type OrganizationMember = {
    id: string;
    name: string;
    email: string;
    status: 'active' | 'suspended';
    role: string | null;
};

export type OrganizationInvitation = {
    id: string;
    email: string;
    role: string;
    expires_at: string;
};
