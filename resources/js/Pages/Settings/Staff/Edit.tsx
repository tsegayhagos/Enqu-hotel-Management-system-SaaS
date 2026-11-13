import { Link, useForm } from '@inertiajs/react';
import { Save } from 'lucide-react';
import type { FormEvent } from 'react';
import { Button, Card, CardContent, CardFooter, Input, PageHeader, Select } from '@/Components/ui';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import type { RoleOption, StaffMember } from '@/types/models';

interface Props {
    member: StaffMember;
    canEditProfile: boolean;
    roles: RoleOption[];
}

export default function StaffEdit({ member, canEditProfile, roles }: Props) {
    const { data, setData, put, processing, errors } = useForm({
        name: member.name,
        phone: member.phone ?? '',
        role: member.role?.value ?? roles[0]?.value ?? '',
    });

    const submit = (event: FormEvent) => {
        event.preventDefault();
        put(`/settings/staff/${member.id}`);
    };

    return (
        <AuthenticatedLayout title={`Edit ${member.name}`}>
            <PageHeader
                title={`Edit ${member.name}`}
                subtitle={member.email}
                breadcrumbs={[
                    { label: 'Dashboard', href: '/dashboard' },
                    { label: 'Staff', href: '/settings/staff' },
                    { label: 'Edit' },
                ]}
            />

            <form onSubmit={submit} noValidate>
                <Card>
                    <CardContent className="grid gap-4 sm:grid-cols-2">
                        <Input
                            label="Full name"
                            value={data.name}
                            disabled={!canEditProfile}
                            onChange={(e) => setData('name', e.target.value)}
                            error={errors.name}
                            hint={canEditProfile ? undefined : 'This person works at several hotels; only they can change their profile.'}
                        />
                        <Input label="Phone" value={data.phone} disabled={!canEditProfile} onChange={(e) => setData('phone', e.target.value)} error={errors.phone} />
                        <Select
                            label="Role in this hotel"
                            required
                            options={roles}
                            value={data.role}
                            disabled={member.is_self}
                            onChange={(e) => setData('role', e.target.value)}
                            error={errors.role}
                        />
                        {member.is_self && <p className="self-end text-xs text-slate-500">You cannot change your own role.</p>}
                    </CardContent>
                    <CardFooter>
                        <Link href="/settings/staff">
                            <Button variant="ghost">Cancel</Button>
                        </Link>
                        <Button type="submit" isLoading={processing} leftIcon={<Save className="h-4 w-4" />}>
                            Save changes
                        </Button>
                    </CardFooter>
                </Card>
            </form>
        </AuthenticatedLayout>
    );
}
