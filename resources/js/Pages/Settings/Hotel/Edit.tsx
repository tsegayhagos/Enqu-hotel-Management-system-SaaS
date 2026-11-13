import { useForm } from '@inertiajs/react';
import { Save } from 'lucide-react';
import type { FormEvent } from 'react';
import { Button, Card, CardContent, CardFooter, CardHeader, CardTitle, Input, PageHeader, Select } from '@/Components/ui';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import { currencyOptions, timezoneOptions } from '@/lib/options';
import type { HotelSettings } from '@/types/models';

interface Props {
    settings: HotelSettings;
}

export default function HotelSettingsEdit({ settings }: Props) {
    const { data, setData, put, processing, errors, recentlySuccessful } = useForm<HotelSettings>({ ...settings });

    const submit = (event: FormEvent) => {
        event.preventDefault();
        put('/settings/hotel', { preserveScroll: true });
    };

    return (
        <AuthenticatedLayout title="Hotel settings">
            <PageHeader
                title="Hotel settings"
                subtitle="Timezone, currency and default check-in/out times apply to every booking calculation"
                breadcrumbs={[{ label: 'Dashboard', href: '/dashboard' }, { label: 'Hotel settings' }]}
            />

            <form onSubmit={submit} noValidate>
                <Card>
                    <CardHeader>
                        <CardTitle>Property</CardTitle>
                    </CardHeader>
                    <CardContent className="grid gap-4 sm:grid-cols-2">
                        <Input label="Hotel name" required value={data.name} onChange={(e) => setData('name', e.target.value)} error={errors.name} />
                        <Input label="Contact email" type="email" required value={data.email} onChange={(e) => setData('email', e.target.value)} error={errors.email} />
                        <Input label="Phone" required value={data.phone} onChange={(e) => setData('phone', e.target.value)} error={errors.phone} />
                        <Input label="Address" value={data.address} onChange={(e) => setData('address', e.target.value)} error={errors.address} />
                        <Input label="City" required value={data.city} onChange={(e) => setData('city', e.target.value)} error={errors.city} />
                        <Input label="Country" required value={data.country} onChange={(e) => setData('country', e.target.value)} error={errors.country} />
                    </CardContent>

                    <CardHeader className="border-t">
                        <CardTitle>Operations</CardTitle>
                    </CardHeader>
                    <CardContent className="grid gap-4 sm:grid-cols-2">
                        <Select label="Currency" required options={currencyOptions} value={data.currency} onChange={(e) => setData('currency', e.target.value)} error={errors.currency} />
                        <Select label="Timezone" required options={timezoneOptions} value={data.timezone} onChange={(e) => setData('timezone', e.target.value)} error={errors.timezone} />
                        <Input label="Default check-in time" type="time" required value={data.check_in_time} onChange={(e) => setData('check_in_time', e.target.value)} error={errors.check_in_time} />
                        <Input label="Default check-out time" type="time" required value={data.check_out_time} onChange={(e) => setData('check_out_time', e.target.value)} error={errors.check_out_time} />
                    </CardContent>

                    <CardFooter>
                        {recentlySuccessful && <span className="text-sm text-emerald-600 dark:text-emerald-400">Saved</span>}
                        <Button type="submit" isLoading={processing} leftIcon={<Save className="h-4 w-4" />}>
                            Save settings
                        </Button>
                    </CardFooter>
                </Card>
            </form>
        </AuthenticatedLayout>
    );
}
