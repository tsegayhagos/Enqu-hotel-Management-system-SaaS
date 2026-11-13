import { Link, useForm } from '@inertiajs/react';
import { Save } from 'lucide-react';
import type { FormEvent } from 'react';
import { Button, Card, CardContent, CardFooter, Input, PageHeader, Select } from '@/Components/ui';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import type { RoleOption } from '@/types/models';

interface Props {
    roles: RoleOption[];
}

export default function StaffCreate({ roles }: Props) {
    const { data, setData, post, processing, errors } = useForm({
        name: '',
        email: '',
        phone: '',
        role: roles[0]?.value ?? '',
        password: '',
    });

    const submit = (event: FormEvent) => {
        event.preventDefault();
        post('/settings/staff');
    };

    return (
        <AuthenticatedLayout title="Add staff">
            <PageHeader
                title="Add staff member"
                subtitle="Create an account or attach an existing one, then assign a role"
                breadcrumbs={[
                    { label: 'Dashboard', href: '/dashboard' },
                    { label: 'Staff', href: '/settings/staff' },
                    { label: 'Add' },
                ]}
            />

            <form onSubmit={submit} noValidate>
                <Card>
                    <CardContent className="grid gap-4 sm:grid-cols-2">
                        <Input label="Full name" required value={data.name} onChange={(e) => setData('name', e.target.value)} error={errors.name} />
                        <Input label="Email" type="email" required value={data.email} onChange={(e) => setData('email', e.target.value)} error={errors.email} />
                        <Input label="Phone" value={data.phone} onChange={(e) => setData('phone', e.target.value)} error={errors.phone} />
                        <Select label="Role" required options={roles} value={data.role} onChange={(e) => setData('role', e.target.value)} error={errors.role} />
                        <Input
                            label="Temporary password"
                            type="password"
                            autoComplete="new-password"
                            value={data.password}
                            onChange={(e) => setData('password', e.target.value)}
                            error={errors.password}
                            hint="Required for new accounts. Leave empty when the person already has an account at another hotel."
                        />
                    </CardContent>
                    <CardFooter>
                        <Link href="/settings/staff">
                            <Button variant="ghost">Cancel</Button>
                        </Link>
                        <Button type="submit" isLoading={processing} leftIcon={<Save className="h-4 w-4" />}>
                            Add staff member
                        </Button>
                    </CardFooter>
                </Card>
            </form>
        </AuthenticatedLayout>
    );
}
