import { Link, router } from '@inertiajs/react';
import { Building2, Plus } from 'lucide-react';
import {
    Badge,
    Button,
    Card,
    EmptyState,
    PageHeader,
    Table,
    TableBody,
    TableCell,
    TableHead,
    TableHeader,
    TableRow,
} from '@/Components/ui';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import type { Paginated } from '@/types';
import type { HotelRow } from '@/types/models';

interface Props {
    hotels: Paginated<HotelRow>;
}

export default function HotelsIndex({ hotels }: Props) {
    const toggle = (hotel: HotelRow) => {
        const verb = hotel.is_active ? 'suspend' : 'activate';

        if (window.confirm(`Do you want to ${verb} ${hotel.name}?`)) {
            router.patch(`/admin/hotels/${hotel.id}`, {}, { preserveScroll: true });
        }
    };

    return (
        <AuthenticatedLayout title="Hotels">
            <PageHeader
                title="Hotels"
                subtitle="Register hotel tenants and control their subscriptions"
                breadcrumbs={[{ label: 'Dashboard', href: '/dashboard' }, { label: 'Hotels' }]}
                actions={
                    <Link href="/admin/hotels/create">
                        <Button leftIcon={<Plus className="h-4 w-4" />}>Register hotel</Button>
                    </Link>
                }
            />

            <Card>
                {hotels.data.length === 0 ? (
                    <EmptyState
                        icon={<Building2 className="h-6 w-6" />}
                        title="No hotels yet"
                        description="Register the first hotel tenant together with its General Manager."
                        action={
                            <Link href="/admin/hotels/create">
                                <Button leftIcon={<Plus className="h-4 w-4" />}>Register hotel</Button>
                            </Link>
                        }
                    />
                ) : (
                    <>
                        <Table>
                            <TableHeader>
                                <TableRow>
                                    <TableHead>Hotel</TableHead>
                                    <TableHead>Location</TableHead>
                                    <TableHead>Currency</TableHead>
                                    <TableHead>Staff</TableHead>
                                    <TableHead>Status</TableHead>
                                    <TableHead className="text-right">Actions</TableHead>
                                </TableRow>
                            </TableHeader>
                            <TableBody>
                                {hotels.data.map((hotel) => (
                                    <TableRow key={hotel.id}>
                                        <TableCell>
                                            <p className="font-medium text-slate-900 dark:text-slate-100">{hotel.name}</p>
                                            <p className="text-xs text-slate-500">{hotel.slug}</p>
                                        </TableCell>
                                        <TableCell>
                                            {hotel.city}, {hotel.country}
                                        </TableCell>
                                        <TableCell>{hotel.currency}</TableCell>
                                        <TableCell>{hotel.users_count}</TableCell>
                                        <TableCell>
                                            <Badge variant={hotel.is_active ? 'success' : 'danger'} dot>
                                                {hotel.is_active ? 'Active' : 'Suspended'}
                                            </Badge>
                                        </TableCell>
                                        <TableCell className="text-right">
                                            <Button
                                                size="xs"
                                                variant={hotel.is_active ? 'outline' : 'primary'}
                                                onClick={() => toggle(hotel)}
                                            >
                                                {hotel.is_active ? 'Suspend' : 'Activate'}
                                            </Button>
                                        </TableCell>
                                    </TableRow>
                                ))}
                            </TableBody>
                        </Table>

                        {hotels.last_page > 1 && (
                            <nav aria-label="Pagination" className="flex flex-wrap justify-center gap-1 border-t border-slate-200 p-3 dark:border-enquDark-700">
                                {hotels.links.map((link, index) => (
                                    <Button
                                        key={`${link.label}-${index}`}
                                        size="xs"
                                        variant={link.active ? 'primary' : 'ghost'}
                                        disabled={link.url === null}
                                        onClick={() => link.url && router.get(link.url, {}, { preserveScroll: true })}
                                    >
                                        <span dangerouslySetInnerHTML={{ __html: link.label }} />
                                    </Button>
                                ))}
                            </nav>
                        )}
                    </>
                )}
            </Card>
        </AuthenticatedLayout>
    );
}
