import { Head, router, usePage } from '@inertiajs/react';
import { Building2 } from 'lucide-react';
import { Button, Card, CardContent, EmptyState } from '@/Components/ui';
import GuestLayout from '@/Layouts/GuestLayout';
import type { SharedProps } from '@/types';

export default function SelectHotel() {
    const { hotels, hotel } = usePage<SharedProps>().props;

    return (
        <GuestLayout>
            <Head title="Choose hotel" />
            <Card>
                <CardContent className="space-y-4 p-6">
                    <div>
                        <h1 className="text-xl font-semibold text-slate-900 dark:text-slate-50">Choose a hotel</h1>
                        <p className="mt-1 text-sm text-slate-500 dark:text-slate-400">
                            Your account is linked to more than one property. You can switch later from the top bar.
                        </p>
                    </div>

                    {hotels.length === 0 ? (
                        <EmptyState title="No hotels available" description="Ask your administrator to add you to a hotel." />
                    ) : (
                        <ul className="space-y-2">
                            {hotels.map((option) => (
                                <li key={option.id}>
                                    <Button
                                        variant={hotel?.id === option.id ? 'primary' : 'outline'}
                                        className="w-full justify-start"
                                        leftIcon={<Building2 className="h-4 w-4" />}
                                        onClick={() => router.post('/switch-hotel', { hotel_id: option.id })}
                                    >
                                        {option.name}
                                    </Button>
                                </li>
                            ))}
                        </ul>
                    )}
                </CardContent>
            </Card>
        </GuestLayout>
    );
}
