# Module 01: Authentication, Multi-Tenancy & Role-Based Access Control (RBAC)

## 1. Overview & Objective
This module establishes the foundational multi-tenant architecture and access control for Enku Hotel Management SaaS. Every hotel is an isolated tenant. System users belong to one or more hotels with distinct operational roles (Front Desk, Housekeeping, Laundry, Manager, SaaS Super Admin).

---

## 2. Roles & Permission Matrix

| Role | Scope | Permissions & Capabilities |
| :--- | :--- | :--- |
| **Super Admin** | Platform / SaaS | Manage hotel subscriptions, register new hotel tenants, view global SaaS metrics. |
| **Hotel Owner / General Manager** | Hotel Tenant | Full control over property settings, room pricing, staff accounts, financial reports. |
| **Front Desk / Receptionist** | Hotel Tenant | Manage guest registrations, room check-in / check-out, issue keys, bill settlements. |
| **Laundry Staff** | Hotel Tenant | Intake laundry orders, update laundry processing stages, post laundry charges. |
| **Housekeeping Supervisor / Staff** | Hotel Tenant | View assigned rooms, update cleaning states (*Clean / Dirty / Inspecting*), report repairs. |
| **Restaurant / POS Cashier** | Hotel Tenant | Process F&B orders and post room-charges to guest folios. |
| **Accountant / Auditor** | Hotel Tenant | View folios, run daily Night Audit, print financial ledger & tax statements. |

---

## 3. Database Schema

### Table: `hotels`
```sql
CREATE TABLE `hotels` (
    `id` BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `name` VARCHAR(255) NOT NULL,
    `slug` VARCHAR(255) NOT NULL UNIQUE,
    `email` VARCHAR(255) NOT NULL,
    `phone` VARCHAR(50) NOT NULL,
    `address` TEXT NULL,
    `city` VARCHAR(100) NOT NULL,
    `country` VARCHAR(100) NOT NULL,
    `currency` VARCHAR(10) DEFAULT 'USD',
    `timezone` VARCHAR(50) DEFAULT 'UTC',
    `check_in_time` TIME DEFAULT '14:00:00',
    `check_out_time` TIME DEFAULT '11:00:00',
    `is_active` BOOLEAN DEFAULT TRUE,
    `created_at` TIMESTAMP NULL,
    `updated_at` TIMESTAMP NULL
);
```

### Table: `hotel_user` (Pivot for Multi-Property Staff)
```sql
CREATE TABLE `hotel_user` (
    `id` BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `hotel_id` BIGINT UNSIGNED NOT NULL,
    `user_id` BIGINT UNSIGNED NOT NULL,
    `is_primary` BOOLEAN DEFAULT TRUE,
    `created_at` TIMESTAMP NULL,
    `updated_at` TIMESTAMP NULL,
    FOREIGN KEY (`hotel_id`) REFERENCES `hotels`(`id`) ON DELETE CASCADE,
    FOREIGN KEY (`user_id`) REFERENCES `users`(`id`) ON DELETE CASCADE,
    UNIQUE KEY `hotel_user_unique` (`hotel_id`, `user_id`)
);
```

### Spatie Permission Configuration:
* Ensure Spatie's `teams` feature or a custom `hotel_id` column is enabled in `config/permission.php` to isolate roles per hotel tenant:
  ```php
  'teams' => true,
  'team_foreign_key' => 'hotel_id',
  ```

---

## 4. Backend Implementation Specification

### Key Models & Scopes:
* `App\Models\Hotel`: `hasMany(User::class)`, `hasMany(Room::class)`, etc.
* `App\Models\User`: Add `currentHotel()` helper and `hotels()` relationship.
* `App\Models\Scopes\HotelScope`: Global Eloquent Scope applying `WHERE hotel_id = ?` to all tenant models.
* `App\Http\Middleware\IdentifyTenant`: Resolves active hotel via session, route parameter, or sub-domain.

### Seeders:
* `RolesAndPermissionsSeeder`: Seeds all 7 system roles with exact granular permissions.
* `DemoHotelSeeder`: Creates a sample hotel ("Grand Enku Resort") with demo staff for testing.

---

## 5. Frontend (React + TypeScript) Specification

### Pages to Build:
* `resources/js/Pages/Auth/Login.tsx`: Multi-tenant login with hotel switch detection.
* `resources/js/Pages/Admin/Hotels/Index.tsx`: Super Admin hotel management list.
* `resources/js/Pages/Admin/Hotels/Create.tsx`: Onboard new hotel with initial admin account.
* `resources/js/Pages/Settings/Staff/Index.tsx`: Hotel Admin staff list with role badges and invite modal.
* `resources/js/Pages/Settings/Staff/Create.tsx`: Invite/Create staff member and assign Spatie role.

---

## 6. Developer Task Checklist

- [ ] **Task 1.1:** Create migration for `hotels` table and `hotel_user` pivot table.
- [ ] **Task 1.2:** Configure `config/permission.php` to support tenant team scopes (`hotel_id`).
- [ ] **Task 1.3:** Create `RolesAndPermissionsSeeder` defining all default permissions (e.g. `room.view`, `checkin.create`, `laundry.manage`, etc.).
- [ ] **Task 1.4:** Build `HotelScope` global query scope and `BelongsToHotel` trait for Eloquent models.
- [ ] **Task 1.5:** Implement `IdentifyTenant` middleware to ensure current session binds active `hotel_id`.
- [ ] **Task 1.6:** Build Staff Management CRUD in React/Inertia (`StaffController` & `Pages/Settings/Staff/*`).
- [ ] **Task 1.7:** Write feature tests for role authorization and tenant data isolation.

---

## 7. QA Acceptance Criteria
1. A receptionist from Hotel A cannot view or access data belonging to Hotel B under any API endpoint.
2. Users without `laundry.manage` permission receive HTTP 403 Forbidden when attempting to access laundry routes.
3. Hotel settings (timezone, currency, check-in/out default times) propagate dynamically to all booking calculations.
