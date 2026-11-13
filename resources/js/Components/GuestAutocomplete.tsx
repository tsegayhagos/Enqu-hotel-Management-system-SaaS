import React, { useEffect, useRef, useState } from 'react';
import { AlertCircle, Check, Loader2, Search, User } from 'lucide-react';
import { Badge } from '@/Components/ui/Badge';
import type { GuestRow } from '@/types/models';

interface GuestAutocompleteProps {
    onSelectGuest: (guest: GuestRow | null) => void;
    selectedGuest?: GuestRow | null;
    placeholder?: string;
}

export function GuestAutocomplete({
    onSelectGuest,
    selectedGuest,
    placeholder = 'Search returning guest by name, phone, or ID number...',
}: GuestAutocompleteProps) {
    const [query, setQuery] = useState('');
    const [results, setResults] = useState<GuestRow[]>([]);
    const [isLoading, setIsLoading] = useState(false);
    const [isOpen, setIsOpen] = useState(false);
    const [highlightIndex, setHighlightIndex] = useState(-1);
    const wrapperRef = useRef<HTMLDivElement>(null);

    useEffect(() => {
        if (!query.trim()) {
            setResults([]);
            setIsOpen(false);
            return;
        }

        const timer = setTimeout(async () => {
            setIsLoading(true);
            try {
                const response = await fetch(`/guests/search?query=${encodeURIComponent(query)}`);
                if (response.ok) {
                    const data = await response.json();
                    setResults(data);
                    setIsOpen(true);
                    setHighlightIndex(data.length > 0 ? 0 : -1);
                }
            } catch (err) {
                console.error('Failed to search guests', err);
            } finally {
                setIsLoading(false);
            }
        }, 300);

        return () => clearTimeout(timer);
    }, [query]);

    useEffect(() => {
        function handleClickOutside(event: MouseEvent) {
            if (wrapperRef.current && !wrapperRef.current.contains(event.target as Node)) {
                setIsOpen(false);
            }
        }
        document.addEventListener('mousedown', handleClickOutside);
        return () => document.removeEventListener('mousedown', handleClickOutside);
    }, []);

    const handleKeyDown = (e: React.KeyboardEvent<HTMLInputElement>) => {
        if (!isOpen || results.length === 0) return;

        if (e.key === 'ArrowDown') {
            e.preventDefault();
            setHighlightIndex((prev) => (prev < results.length - 1 ? prev + 1 : 0));
        } else if (e.key === 'ArrowUp') {
            e.preventDefault();
            setHighlightIndex((prev) => (prev > 0 ? prev - 1 : results.length - 1));
        } else if (e.key === 'Enter') {
            e.preventDefault();
            if (highlightIndex >= 0 && highlightIndex < results.length) {
                selectGuest(results[highlightIndex]);
            }
        } else if (e.key === 'Escape') {
            setIsOpen(false);
        }
    };

    const selectGuest = (guest: GuestRow) => {
        onSelectGuest(guest);
        setQuery(`${guest.first_name} ${guest.last_name}`);
        setIsOpen(false);
    };

    const clearSelection = () => {
        onSelectGuest(null);
        setQuery('');
        setResults([]);
    };

    return (
        <div ref={wrapperRef} className="relative w-full">
            <div className="relative">
                <Search className="absolute left-3 top-2.5 h-4 w-4 text-slate-400" />
                <input
                    type="text"
                    value={query}
                    onChange={(e) => {
                        setQuery(e.target.value);
                        if (selectedGuest) onSelectGuest(null);
                    }}
                    onFocus={() => {
                        if (results.length > 0) setIsOpen(true);
                    }}
                    onKeyDown={handleKeyDown}
                    placeholder={placeholder}
                    className="w-full rounded-md border border-slate-300 bg-white py-2 pl-9 pr-10 text-sm shadow-sm placeholder:text-slate-400 focus:border-amber-500 focus:outline-none focus:ring-1 focus:ring-amber-500 dark:border-slate-700 dark:bg-slate-900 dark:text-slate-100"
                />
                {isLoading && (
                    <div className="absolute right-3 top-2.5">
                        <Loader2 className="h-4 w-4 animate-spin text-slate-400" />
                    </div>
                )}
                {selectedGuest && !isLoading && (
                    <button
                        type="button"
                        onClick={clearSelection}
                        className="absolute right-3 top-2 text-xs font-semibold text-slate-400 hover:text-slate-600 dark:hover:text-slate-200"
                    >
                        ✕
                    </button>
                )}
            </div>

            {isOpen && (
                <ul className="absolute z-50 mt-1 max-h-60 w-full overflow-auto rounded-md border border-slate-200 bg-white py-1 shadow-lg dark:border-slate-800 dark:bg-slate-900">
                    {results.length === 0 ? (
                        <li className="px-4 py-3 text-sm text-slate-500 dark:text-slate-400">
                            No matching guest found. Enter new guest profile details below.
                        </li>
                    ) : (
                        results.map((guest, index) => {
                            const isHighlighted = index === highlightIndex;
                            return (
                                <li
                                    key={guest.id}
                                    onClick={() => selectGuest(guest)}
                                    onMouseEnter={() => setHighlightIndex(index)}
                                    className={`flex cursor-pointer items-center justify-between px-3 py-2 text-sm ${
                                        isHighlighted
                                            ? 'bg-amber-50 dark:bg-amber-950/40'
                                            : 'hover:bg-slate-50 dark:hover:bg-slate-800/50'
                                    }`}
                                >
                                    <div className="flex items-center gap-2">
                                        <div className="flex h-7 w-7 items-center justify-center rounded-full bg-slate-100 dark:bg-slate-800">
                                            <User className="h-4 w-4 text-slate-500" />
                                        </div>
                                        <div>
                                            <div className="flex items-center gap-2">
                                                <span className="font-medium text-slate-900 dark:text-slate-100">
                                                    {guest.first_name} {guest.last_name}
                                                </span>
                                                {guest.is_blacklisted && (
                                                    <Badge variant="danger" className="text-xs">
                                                        Blacklisted
                                                    </Badge>
                                                )}
                                                {guest.vip_level !== 'standard' && (
                                                    <Badge variant="warning" className="text-xs uppercase">
                                                        {guest.vip_level}
                                                    </Badge>
                                                )}
                                            </div>
                                            <p className="text-xs text-slate-500 dark:text-slate-400">
                                                {guest.phone} {guest.email ? `• ${guest.email}` : ''} • ID: {guest.id_number}
                                            </p>
                                        </div>
                                    </div>
                                    <span className="text-xs text-slate-400">
                                        {guest.total_stays_count} {guest.total_stays_count === 1 ? 'stay' : 'stays'}
                                    </span>
                                </li>
                            );
                        })
                    )}
                </ul>
            )}
        </div>
    );
}
