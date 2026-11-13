import { Link, useForm } from '@inertiajs/react';
import { Save } from 'lucide-react';
import type { FormEvent } from 'react';
import { Button, Card, CardContent, CardFooter, CardHeader, CardTitle, Input, PageHeader, Select } from '@/Components/ui';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import { currencyOptions, timezoneOptions } from '@/lib/options';

export default function HotelsCreate() {
    const { data, setData, post, processing, errors } = useForm({
        name: '',
        email: '',
        phone: '',
        address: '',
        city: 'Addis Ababa',
        country: 'Ethiopia',
        currency: 'ETB',
        timezone: 'Africa/Addis_Ababa',
        check_in_time: '14:00',
        check_out_time: '11:00',
        admin_name: '',
        admin_email: '',
        admin_password: '',
    });

    const submit = (event: FormEvent) => {
        event.preventDefault();
        post('/admin/hotels');
    };

    return (
        <AuthenticatedLayout title="Register hotel">
            <PageHeader
                title="Register hotel"
                subtitle="Onboard a new tenant with its initial General Manager account"
                breadcrumbs={[
                    { label: 'Dashboard', href: '/dashboard' },
                    { label: 'Hotels', href: '/admin/hotels' },
                    { label: 'Register' },
                ]}
            />

            <form onSubmit={submit} className="space-y-6" noValidate>
                <Card>
                    <CardHeader>
                        <CardTitle>Hotel details</CardTitle>
                    </CardHeader>
                    <CardContent className="grid gap-4 sm:grid-cols-2">
                        <Input label="Hotel name" required value={data.name} onChange={(e) => setData('name', e.target.value)} error={errors.name} />
                        <Input label="Contact email" type="email" required value={data.email} onChange={(e) => setData('email', e.target.value)} error={errors.email} />
                        <Input label="Phone" required value={data.phone} onChange={(e) => setData('phone', e.target.value)} error={errors.phone} />
                        <Input label="Address" value={data.address} onChange={(e) => setData('address', e.target.value)} error={errors.address} />
                        <Input label="City" required value={data.city} onChange={(e) => setData('city', e.target.value)} error={errors.city} />
                        <Input label="Country" required value={data.country} onChange={(e) => setData('country', e.target.value)} error={errors.country} />
                        <Select label="Currency" required options={currencyOptions} value={data.currency} onChange={(e) => setData('currency', e.target.value)} error={errors.currency} />
                        <Select label="Timezone" required options={timezoneOptions} value={data.timezone} onChange={(e) => setData('timezone', e.target.value)} error={errors.timezone} />
                        <Input label="Default check-in time" type="time" required value={data.check_in_time} onChange={(e) => setData('check_in_time', e.target.value)} error={errors.check_in_time} />
                        <Input label="Default check-out time" type="time" required value={data.check_out_time} onChange={(e) => setData('check_out_time', e.target.value)} error={errors.check_out_time} />
                    </CardContent>
                </Card>

                <Card>
                    <CardHeader>
                        <CardTitle>General Manager account</CardTitle>
                    </CardHeader>
                    <CardContent className="grid gap-4 sm:grid-cols-2">
                        <Input label="Full name" required value={data.admin_name} onChange={(e) => setData('admin_name', e.target.value)} error={errors.admin_name} />
                        <Input label="Email" type="email" required value={data.admin_email} onChange={(e) => setData('admin_email', e.target.value)} error={errors.admin_email} hint="If this email already has an account, it is attached to the new hotel." />
                        <Input label="Temporary password" type="password" required autoComplete="new-password" value={data.admin_password} onChange={(e) => setData('admin_password', e.target.value)} error={errors.admin_password} hint="At least 8 characters." />
                    </CardContent>
                    <CardFooter>
                        <Link href="/admin/hotels">
                            <Button variant="ghost">Cancel</Button>
                        </Link>
                        <Button type="submit" isLoading={processing} leftIcon={<Save className="h-4 w-4" />}>
                            Register hotel
                        </Button>
                    </CardFooter>
                </Card>
            </form>
        </AuthenticatedLayout>
    );
}
