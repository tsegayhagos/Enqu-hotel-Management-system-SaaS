import { Link, router, useForm } from '@inertiajs/react';
import { Pencil, Trash2, UserPlus, Users } from 'lucide-react';
import type { FormEvent } from 'react';
import { useState } from 'react';
import {
    Badge,
    Button,
    Card,
    EmptyState,
    Input,
    Modal,
    PageHeader,
    Select,
    Table,
    TableBody,
    TableCell,
    TableHead,
    TableHeader,
    TableRow,
} from '@/Components/ui';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import type { RoleOption, StaffMember } from '@/types/models';

interface Props {
    staff: StaffMember[];
    roles: RoleOption[];
}

export default function StaffIndex({ staff, roles }: Props) {
    const [inviteOpen, setInviteOpen] = useState(false);

    const { data, setData, post, processing, errors, reset, clearErrors } = useForm({
        name: '',
        email: '',
        role: roles[0]?.value ?? '',
        password: '',
    });

    const closeInvite = () => {
        setInviteOpen(false);
        reset();
        clearErrors();
    };

    const submit = (event: FormEvent) => {
        event.preventDefault();
        post('/settings/staff', {
            preserveScroll: true,
            onSuccess: () => closeInvite(),
        });
    };

    const remove = (member: StaffMember) => {
        if (window.confirm(`Remove ${member.name} from this hotel? Their account is kept.`)) {
            router.delete(`/settings/staff/${member.id}`, { preserveScroll: true });
        }
    };

    return (
        <AuthenticatedLayout title="Staff">
            <PageHeader
                title="Staff"
                subtitle="Manage who can work in this hotel and what they can do"
                breadcrumbs={[{ label: 'Dashboard', href: '/dashboard' }, { label: 'Staff' }]}
                actions={
                    <>
                        <Button variant="outline" leftIcon={<UserPlus className="h-4 w-4" />} onClick={() => setInviteOpen(true)}>
                            Quick invite
                        </Button>
                        <Link href="/settings/staff/create">
                            <Button leftIcon={<UserPlus className="h-4 w-4" />}>Add staff</Button>
                        </Link>
                    </>
                }
            />

            <Card>
                {staff.length === 0 ? (
                    <EmptyState
                        icon={<Users className="h-6 w-6" />}
                        title="No staff yet"
                        description="Add your first team member and assign a role."
                    />
                ) : (
                    <Table>
                        <TableHeader>
                            <TableRow>
                                <TableHead>Name</TableHead>
                                <TableHead>Email</TableHead>
                                <TableHead>Phone</TableHead>
                                <TableHead>Role</TableHead>
                                <TableHead className="text-right">Actions</TableHead>
                            </TableRow>
                        </TableHeader>
                        <TableBody>
                            {staff.map((member) => (
                                <TableRow key={member.id}>
                                    <TableCell className="font-medium text-slate-900 dark:text-slate-100">
                                        {member.name}
                                        {member.is_self && <span className="ml-2 text-xs text-slate-500">(you)</span>}
                                    </TableCell>
                                    <TableCell>{member.email}</TableCell>
                                    <TableCell>{member.phone ?? '-'}</TableCell>
                                    <TableCell>
                                        {member.role ? (
                                            <Badge variant={member.role.variant} dot>
                                                {member.role.label}
                                            </Badge>
                                        ) : (
                                            <Badge variant="neutral">No role</Badge>
                                        )}
                                    </TableCell>
                                    <TableCell className="text-right">
                                        <div className="inline-flex gap-1">
                                            <Link href={`/settings/staff/${member.id}/edit`}>
                                                <Button size="xs" variant="outline" leftIcon={<Pencil className="h-3 w-3" />}>
                                                    Edit
                                                </Button>
                                            </Link>
                                            <Button
                                                size="xs"
                                                variant="danger"
                                                disabled={member.is_self}
                                                leftIcon={<Trash2 className="h-3 w-3" />}
                                                onClick={() => remove(member)}
                                            >
                                                Remove
                                            </Button>
                                        </div>
                                    </TableCell>
                                </TableRow>
                            ))}
                        </TableBody>
                    </Table>
                )}
            </Card>

            <Modal open={inviteOpen} title="Quick invite" onClose={closeInvite}>
                <form onSubmit={submit} className="space-y-4" noValidate>
                    <Input label="Full name" required value={data.name} onChange={(e) => setData('name', e.target.value)} error={errors.name} />
                    <Input label="Email" type="email" required value={data.email} onChange={(e) => setData('email', e.target.value)} error={errors.email} />
                    <Select label="Role" required options={roles} value={data.role} onChange={(e) => setData('role', e.target.value)} error={errors.role} />
                    <Input
                        label="Temporary password"
                        type="password"
                        autoComplete="new-password"
                        value={data.password}
                        onChange={(e) => setData('password', e.target.value)}
                        error={errors.password}
                        hint="Required for new accounts. Leave empty to attach an existing account."
                    />
                    <div className="flex justify-end gap-2">
                        <Button variant="ghost" onClick={closeInvite}>
                            Cancel
                        </Button>
                        <Button type="submit" isLoading={processing}>
                            Send invite
                        </Button>
                    </div>
                </form>
            </Modal>
        </AuthenticatedLayout>
    );
}
