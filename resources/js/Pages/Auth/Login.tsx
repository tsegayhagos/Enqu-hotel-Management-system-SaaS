import { Head, useForm } from '@inertiajs/react';
import { LogIn } from 'lucide-react';
import type { FormEvent } from 'react';
import { Badge, Button, Card, CardContent, Input } from '@/Components/ui';
import GuestLayout from '@/Layouts/GuestLayout';

interface Props {
    detectedHotel: { name: string; slug: string } | null;
}

export default function Login({ detectedHotel }: Props) {
    const { data, setData, post, processing, errors, reset } = useForm({
        email: '',
        password: '',
        remember: false,
    });

    const submit = (event: FormEvent) => {
        event.preventDefault();
        post('/login', { onFinish: () => reset('password') });
    };

    return (
        <GuestLayout>
            <Head title="Sign in" />
            <Card>
                <CardContent className="space-y-5 p-6">
                    <div>
                        <h1 className="text-xl font-semibold text-slate-900 dark:text-slate-50">Sign in</h1>
                        {detectedHotel ? (
                            <p className="mt-2 flex items-center gap-2 text-sm text-slate-500 dark:text-slate-400">
                                Signing in to <Badge variant="primary">{detectedHotel.name}</Badge>
                            </p>
                        ) : (
                            <p className="mt-1 text-sm text-slate-500 dark:text-slate-400">
                                Use your staff account. If you work at several hotels you can choose one next.
                            </p>
                        )}
                    </div>

                    <form onSubmit={submit} className="space-y-4" noValidate>
                        <Input
                            label="Email"
                            type="email"
                            autoComplete="username"
                            required
                            autoFocus
                            value={data.email}
                            onChange={(event) => setData('email', event.target.value)}
                            error={errors.email}
                        />
                        <Input
                            label="Password"
                            type="password"
                            autoComplete="current-password"
                            required
                            value={data.password}
                            onChange={(event) => setData('password', event.target.value)}
                            error={errors.password}
                        />
                        <label className="flex items-center gap-2 text-sm text-slate-600 dark:text-slate-300">
                            <input
                                type="checkbox"
                                checked={data.remember}
                                onChange={(event) => setData('remember', event.target.checked)}
                                className="h-4 w-4 rounded border-slate-300 text-primary-800 focus:ring-primary-500"
                            />
                            Remember me
                        </label>
                        <Button type="submit" className="w-full" isLoading={processing} leftIcon={<LogIn className="h-4 w-4" />}>
                            Sign in
                        </Button>
                    </form>
                </CardContent>
            </Card>
        </GuestLayout>
    );
}
