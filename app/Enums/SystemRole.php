<?php

namespace App\Enums;

/**
 * The 7 system roles from Module 01 (permission matrix).
 * SuperAdmin is platform-level (users.is_super_admin); the other six are
 * provisioned per hotel (Spatie teams, team key = hotel_id).
 */
enum SystemRole: string
{
    case SuperAdmin = 'super-admin';
    case HotelManager = 'hotel-manager';
    case FrontDesk = 'front-desk';
    case LaundryStaff = 'laundry-staff';
    case Housekeeping = 'housekeeping';
    case PosCashier = 'pos-cashier';
    case Accountant = 'accountant';

    public function label(): string
    {
        return match ($this) {
            self::SuperAdmin => 'Super Admin',
            self::HotelManager => 'Hotel Owner / General Manager',
            self::FrontDesk => 'Front Desk / Receptionist',
            self::LaundryStaff => 'Laundry Staff',
            self::Housekeeping => 'Housekeeping',
            self::PosCashier => 'Restaurant / POS Cashier',
            self::Accountant => 'Accountant / Auditor',
        };
    }

    public function badgeVariant(): string
    {
        return match ($this) {
            self::SuperAdmin => 'danger',
            self::HotelManager => 'primary',
            self::FrontDesk => 'info',
            self::LaundryStaff => 'accent',
            self::Housekeeping => 'success',
            self::PosCashier => 'warning',
            self::Accountant => 'neutral',
        };
    }

    /** Roles that exist inside a hotel tenant (everything except SuperAdmin). */
    public static function hotelRoles(): array
    {
        return array_values(array_filter(self::cases(), fn (self $role): bool => $role !== self::SuperAdmin));
    }

    /** @return list<string> */
    public function permissions(): array
    {
        return match ($this) {
            self::SuperAdmin => [],
            self::HotelManager => self::allPermissions(),
            self::FrontDesk => [
                'room.view', 'guest.view', 'guest.manage', 'reservation.manage',
                'checkin.create', 'checkout.create', 'folio.view', 'folio.manage',
                'payment.process', 'laundry.view', 'housekeeping.view',
                'maintenance.report', 'cashier.report',
            ],
            self::LaundryStaff => ['room.view', 'guest.view', 'laundry.view', 'laundry.manage'],
            self::Housekeeping => [
                'room.view', 'housekeeping.view', 'housekeeping.perform', 'maintenance.report',
            ],
            self::PosCashier => [
                'room.view', 'guest.view', 'folio.view', 'pos.charge', 'cashier.report',
            ],
            self::Accountant => [
                'folio.view', 'nightaudit.run', 'report.view', 'report.export', 'cashier.report',
            ],
        };
    }

    /**
     * Every permission in the system, grouped by module (used by the seeder).
     *
     * @return list<string>
     */
    public static function allPermissions(): array
    {
        return [
            // Module 01
            'hotel.manage', 'staff.manage',
            // Module 02
            'room.view', 'room.manage', 'rate.manage',
            // Module 03
            'guest.view', 'guest.manage', 'reservation.manage', 'checkin.create', 'checkout.create',
            'checkin.override', 'checkout.override',
            // Module 04
            'laundry.view', 'laundry.manage',
            // Module 05
            'housekeeping.view', 'housekeeping.manage', 'housekeeping.perform',
            'maintenance.report', 'maintenance.manage',
            // Module 06
            'folio.view', 'folio.manage', 'payment.process', 'pos.charge', 'nightaudit.run',
            // Module 07
            'report.view', 'report.export', 'cashier.report',
        ];
    }
}
