# Enku Hotel Management SaaS — Developer Documentation & Implementation Guide

Welcome to the **Enku Hotel Management SaaS (PMS)** developer documentation. This guide and module directory is structured so that engineering teams can independently develop, test, and integrate their respective modules.

---

## 🏗️ System Architecture & Technology Stack

* **Backend:** Laravel 12+ (PHP 8.5)
* **Frontend:** React 18 + TypeScript + Inertia.js v2
* **Styling:** Tailwind CSS + Lucide React Icons
* **Database:** MySQL 8.0+
* **Authentication & RBAC:** Spatie Laravel Permission + Laravel Sanctum (for API/mobile access)
* **Testing:** PHPUnit (Feature & Unit tests)

---

## 📁 Documentation & Module Directory

> [!IMPORTANT]
> **All developers must start by reading [00-coding-standards-and-git-workflow.md](./00-coding-standards-and-git-workflow.md)** for our Git branching model, PR rules, Enqu brand color palette, and reusable `@/Components/ui` library.

Each document below is a self-contained module specification containing **database schemas, backend endpoints, React/TypeScript page structures, actionable developer task lists (`[ ]`), edge cases, and QA acceptance criteria**:

| File | Module Name | Primary Stakeholders |
| :--- | :--- | :--- |
| [**`00-coding-standards-and-git-workflow.md`**](./00-coding-standards-and-git-workflow.md) | **Coding Standards, Git & Design System** | **All Engineering Teams** |
| [**`01-auth-multitenancy-rbac.md`**](./01-auth-multitenancy-rbac.md) | Authentication, Multi-Tenancy & RBAC | SaaS Admin, Hotel General Manager |
| [**`02-property-rooms-rates.md`**](./02-property-rooms-rates.md) | Property, Rooms & Rate Management | Hotel Manager, Front Desk Supervisor |
| [**`03-guest-crm-frontdesk.md`**](./03-guest-crm-frontdesk.md) | Guest CRM & Front Desk (Check-In/Out) | Front Desk Receptionist, Concierge |
| [**`04-laundry-valet-service.md`**](./04-laundry-valet-service.md) | Laundry & Valet Services | Laundry Supervisor, Front Desk, Housekeeping |
| [**`05-housekeeping-maintenance.md`**](./05-housekeeping-maintenance.md) | Housekeeping & Maintenance Operations | Housekeeping Staff, Maintenance Technicians |
| [**`06-billing-folios-nightaudit.md`**](./06-billing-folios-nightaudit.md) | Billing, Folios, POS & Automated Night Audit | Night Auditor, Cashier, Financial Accountant |
| [**`07-reporting-analytics.md`**](./07-reporting-analytics.md) | Hotel Reports, KPIs & SaaS Analytics | General Manager, Hotel Owner, SaaS Admin |

---

## 🛠️ General Developer Guidelines & Conventions

### 1. Database & Migrations
* All table names must be snake_case plural (e.g. `guest_profiles`, `laundry_orders`).
* Every tenant-scoped table **must** include `hotel_id` as an unsigned big integer foreign key indexed for performance.
* Currency amounts must be stored as integer cents or `DECIMAL(10, 2)` (never float).
* Use database enums or string columns with Model PHP Enums for status lifecycles.

### 2. Backend (Laravel)
* Follow Single Responsibility Principle: Use **Form Requests** for validation, **Action classes/Services** for business logic, and lightweight **Controllers** returning `Inertia::render()`.
* Strict return types and parameter type hints on all PHP methods.
* Authorization: Always protect controller actions using Laravel Policies or Spatie Permission middleware:
  ```php
  $this->authorize('check-in', $reservation);
  ```

### 3. Frontend (React + TypeScript + Inertia)
* Pages live in `resources/js/Pages/{ModuleName}/{Action}.tsx`.
* Reusable components live in `resources/js/Components/`.
* All Inertia page props must have explicit TypeScript interface definitions in `resources/js/types/`.
* Use Tailwind CSS for responsive, accessible styling with dark mode support.
* Use `@inertiajs/react` form helpers (`useForm`, `<Link>`, `<Form>`).

### 4. Git Workflow
* Branch naming: `feature/{module-number}-{short-title}` (e.g. `feature/04-laundry-order-intake`).
* Pull requests must reference the task number from the corresponding module doc.
* Run code formatter and test suite before submitting PR:
  ```bash
  vendor/bin/pint --format agent
  php artisan test
  npm run build
  ```
