import { Head, Link, router, usePage } from '@inertiajs/react';
import { Hotel, LogOut, Menu, Moon, Sun, X } from 'lucide-react';
import { useState, type ReactNode } from 'react';
import { Select } from '@/Components/ui';
import { cn } from '@/lib/cn';
import { navigation } from '@/lib/navigation';
import { usePermissions } from '@/lib/permissions';
import { useTheme } from '@/lib/theme';
import type { SharedProps } from '@/types';

interface Props {
    title?: string;
    children: ReactNode;
}

export default function AuthenticatedLayout({ title, children }: Props) {
    const { auth, hotel, hotels, flash } = usePage<SharedProps>().props;
    const { url } = usePage();
    const { can, isSuperAdmin } = usePermissions();
    const { dark, toggle } = useTheme();
    const [menuOpen, setMenuOpen] = useState(false);

    const groups = navigation
        .map((group) => ({
            ...group,
            items: group.items.filter((item) => (item.superAdminOnly ? isSuperAdmin : can(item.permission))),
        }))
        .filter((group) => group.items.length > 0);

    const isActive = (href: string) => url === href || url.startsWith(`${href}/`) || url.startsWith(`${href}?`);

    const switchHotel = (hotelId: string) => {
        if (hotelId) {
            router.post('/switch-hotel', { hotel_id: Number(hotelId) });
        }
    };

    const sidebar = (
        <nav aria-label="Main" className="flex h-full flex-col gap-6 overflow-y-auto px-3 py-5">
            {groups.map((group) => (
                <div key={group.title}>
                    <p className="mb-2 px-3 text-xs font-semibold uppercase tracking-wider text-slate-400">{group.title}</p>
                    <ul className="space-y-1">
                        {group.items.map((item) => (
                            <li key={item.href}>
                                <Link
                                    href={item.href}
                                    onClick={() => setMenuOpen(false)}
                                    className={cn(
                                        'flex items-center gap-3 rounded-lg px-3 py-2 text-sm font-medium transition-colors',
                                        isActive(item.href)
                                            ? 'bg-primary-500/20 text-white'
                                            : 'text-slate-300 hover:bg-white/5 hover:text-white',
                                    )}
                                >
                                    <item.icon className="h-4 w-4" aria-hidden />
                                    {item.label}
                                </Link>
                            </li>
                        ))}
                    </ul>
                </div>
            ))}
        </nav>
    );

    return (
        <div className="min-h-screen bg-slate-50 dark:bg-enquDark-900">
            <Head title={title} />

            {/* Desktop sidebar */}
            <aside className="no-print fixed inset-y-0 left-0 z-30 hidden w-64 flex-col bg-enquDark-900 lg:flex dark:border-r dark:border-enquDark-700">
                <div className="flex h-16 items-center gap-3 border-b border-white/10 px-5">
                    <span className="flex h-9 w-9 items-center justify-center rounded-lg bg-primary-500 text-white">
                        <Hotel className="h-5 w-5" aria-hidden />
                    </span>
                    <span className="truncate text-sm font-semibold text-white">{hotel?.name ?? 'Enku PMS'}</span>
                </div>
                {sidebar}
            </aside>

            {/* Mobile drawer */}
            {menuOpen && (
                <div className="no-print fixed inset-0 z-40 lg:hidden">
                    <div className="absolute inset-0 bg-black/50" onClick={() => setMenuOpen(false)} aria-hidden />
                    <aside className="relative h-full w-64 bg-enquDark-900">
                        <button
                            type="button"
                            onClick={() => setMenuOpen(false)}
                            className="absolute right-3 top-3 rounded-md p-1 text-slate-300 hover:bg-white/10"
                            aria-label="Close menu"
                        >
                            <X className="h-5 w-5" />
                        </button>
                        <div className="pt-12">{sidebar}</div>
                    </aside>
                </div>
            )}

            <div className="lg:pl-64">
                {/* Top header */}
                <header className="no-print sticky top-0 z-20 flex h-16 items-center justify-between border-b border-slate-200 bg-white/90 px-4 backdrop-blur sm:px-6 dark:border-enquDark-700 dark:bg-enquDark-800/90">
                    <div className="flex items-center gap-3">
                        <button
                            type="button"
                            onClick={() => setMenuOpen(true)}
                            className="rounded-lg p-2 text-slate-500 hover:bg-slate-100 lg:hidden dark:text-slate-300 dark:hover:bg-enquDark-700"
                            aria-label="Open navigation"
                        >
                            <Menu className="h-5 w-5" />
                        </button>

                        {hotels && hotels.length > 1 && (
                            <div className="w-56">
                                <Select
                                    label=""
                                    value={hotel?.id ? String(hotel.id) : ''}
                                    onChange={(event) => switchHotel(event.target.value)}
                                    options={hotels.map((h) => ({ value: String(h.id), label: h.name }))}
                                />
                            </div>
                        )}
                    </div>

                    <div className="flex items-center gap-2">
                        <button
                            type="button"
                            onClick={toggle}
                            className="rounded-lg p-2 text-slate-500 hover:bg-slate-100 dark:text-slate-300 dark:hover:bg-enquDark-700"
                            aria-label={dark ? 'Switch to light mode' : 'Switch to dark mode'}
                        >
                            {dark ? <Sun className="h-4 w-4" /> : <Moon className="h-4 w-4" />}
                        </button>

                        <span className="hidden text-sm text-slate-600 sm:inline dark:text-slate-300">
                            {auth.user?.name}
                        </span>

                        <Link
                            href="/logout"
                            method="post"
                            as="button"
                            className="flex items-center gap-1 rounded-lg px-2.5 py-1.5 text-sm text-slate-600 hover:bg-slate-100 hover:text-slate-900 dark:text-slate-300 dark:hover:bg-enquDark-700 dark:hover:text-white"
                        >
                            <LogOut className="h-4 w-4" />
                            <span className="hidden sm:inline">Logout</span>
                        </Link>
                    </div>
                </header>

                {/* Alerts */}
                {flash.success && (
                    <div className="border-b border-emerald-200 bg-emerald-50 px-4 py-2.5 text-sm text-emerald-800 dark:border-emerald-900/50 dark:bg-emerald-950/40 dark:text-emerald-200">
                        {flash.success}
                    </div>
                )}
                {flash.error && (
                    <div className="border-b border-rose-200 bg-rose-50 px-4 py-2.5 text-sm text-rose-800 dark:border-rose-900/50 dark:bg-rose-950/40 dark:text-rose-200">
                        {flash.error}
                    </div>
                )}

                {/* Page body */}
                <main className="p-4 sm:p-6 lg:p-8">{children}</main>
            </div>
        </div>
    );
}
