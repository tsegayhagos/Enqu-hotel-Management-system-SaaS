import type { SelectOption } from '@/types';

export const currencyOptions: SelectOption[] = [
    { value: 'ETB', label: 'ETB - Ethiopian Birr' },
    { value: 'USD', label: 'USD - US Dollar' },
    { value: 'EUR', label: 'EUR - Euro' },
    { value: 'GBP', label: 'GBP - British Pound' },
    { value: 'KES', label: 'KES - Kenyan Shilling' },
    { value: 'AED', label: 'AED - UAE Dirham' },
];

export const timezoneOptions: SelectOption[] = [
    { value: 'Africa/Addis_Ababa', label: 'Africa/Addis Ababa (EAT)' },
    { value: 'Africa/Nairobi', label: 'Africa/Nairobi (EAT)' },
    { value: 'Africa/Cairo', label: 'Africa/Cairo' },
    { value: 'Africa/Johannesburg', label: 'Africa/Johannesburg' },
    { value: 'Europe/London', label: 'Europe/London' },
    { value: 'Asia/Dubai', label: 'Asia/Dubai' },
    { value: 'America/New_York', label: 'America/New York' },
    { value: 'UTC', label: 'UTC' },
];
