import { Link, usePage } from '@inertiajs/react';
import { Clock, Coins, Globe, Users } from 'lucide-react';
import { Card, CardContent, CardHeader, CardTitle, EmptyState, PageHeader, StatCard } from '@/Components/ui';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import type { SharedProps } from '@/types';

interface Props {
    staffCount: number;
}

export default function Dashboard({ staffCount }: Props) {
    const { hotel, auth } = usePage<SharedProps>().props;

    return (
        <AuthenticatedLayout title="Dashboard">
            <PageHeader
                title={hotel ? hotel.name : 'Dashboard'}
                subtitle={`Welcome back, ${auth.user?.name ?? ''}`}
                breadcrumbs={[{ label: 'Dashboard' }]}
            />

            {hotel ? (
                <>
                    <div className="grid gap-4 sm:grid-cols-2 xl:grid-cols-4">
                        <StatCard title="Staff members" value={staffCount} icon={<Users className="h-5 w-5" />} />
                        <StatCard title="Currency" value={hotel.currency} icon={<Coins className="h-5 w-5" />} />
                        <StatCard title="Timezone" value={hotel.timezone} icon={<Globe className="h-5 w-5" />} />
                        <StatCard
                            title="Check-in / Check-out"
                            value={`${hotel.check_in_time} / ${hotel.check_out_time}`}
                            icon={<Clock className="h-5 w-5" />}
                        />
                    </div>

                    <Card className="mt-6">
                        <CardHeader>
                            <CardTitle>Operations</CardTitle>
                        </CardHeader>
                        <CardContent>
                            <p className="text-sm text-slate-600 dark:text-slate-300">
                                Rooms, front desk, laundry, housekeeping, billing and reports appear in the sidebar as each
                                module is enabled.
                            </p>
                        </CardContent>
                    </Card>
                </>
            ) : (
                <Card>
                    <EmptyState
                        title="No hotel selected"
                        description="Pick a hotel from the top bar, or register one from the Hotels page."
                        action={
                            auth.user?.is_super_admin ? (
                                <Link href="/admin/hotels" className="text-sm font-medium text-primary-800 hover:underline dark:text-primary-300">
                                    Go to Hotels
                                </Link>
                            ) : undefined
                        }
                    />
                </Card>
            )}
        </AuthenticatedLayout>
    );
}
