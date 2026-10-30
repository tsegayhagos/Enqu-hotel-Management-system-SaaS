# Enku Hotel Management SaaS (PMS)

A modern, full-featured Property Management System (PMS) and Hotel SaaS built for hoteliers, front-desk staff, housekeeping, laundry, and property managers.

---

## 🛠️ Tech Stack

* **Backend Framework:** Laravel 12+ (PHP 8.5)
* **Frontend UI:** React 18 + TypeScript + Inertia.js
* **Styling:** Tailwind CSS (with Lucide React icons)
* **Database:** MySQL 8
* **Access Control & Roles:** Spatie Laravel Permission
* **API & Mobile Authentication:** Laravel Sanctum

---

## 🏨 System Modules

### 1. Front Desk & Reception
* **Guest Registration & Profiles (CRM):** National ID / Passport capture, VIP tags, preferences, blacklist alerts, and repeat guest history.
* **Check-In & Check-Out:** Express check-in, keycard assignments, room status changes, and room upgrades.
* **Bill Settlement & Folios:** Consolidated folios for room, restaurant, and services with multi-currency split payments (cash, card, bank transfer).

### 2. Laundry & Valet Services
* **Service Types:** Wash & Fold, Dry Cleaning, Ironing & Pressing, Express Service.
* **Tracking Pipeline:** `Received` $\rightarrow$ `In Wash` $\rightarrow$ `Ironing` $\rightarrow$ `Ready` $\rightarrow$ `Delivered`.
* **Direct Room Folio Charging:** Instant posting of laundry charges to the guest's active room folio or standalone walk-in billing.
* **Linen & Towel Inventory:** Housekeeping linen wash cycles and tracking.

### 3. Room Management & Interactive Calendar
* **Visual Room Grid:** Real-time occupancy status: *Occupied, Vacant Clean, Vacant Dirty, Reserved, Maintenance/Out-of-Order*.
* **Room Categories:** Standard, Deluxe, Executive, Suites with dynamic seasonal pricing.
* **Timeline / Gantt View:** Visual booking timeline across all rooms and dates.

### 4. Housekeeping & Maintenance
* Housekeeping mobile-friendly task dispatch per floor/shift.
* Live status transitions (*Dirty* $\rightarrow$ *Cleaning* $\rightarrow$ *Inspected* $\rightarrow$ *Clean*).
* Maintenance ticketing with automated room blocking for repairs.

### 5. Restaurant / Bar / POS Integration
* Point of sale for dining and room service.
* "Charge to Room" option verified against checked-in guests.

### 6. Billing, Night Audit & Reports
* **Automated Night Audit:** Midnight room rate postings, tax levies, and ledger balancing.
* **Analytics & KPIs:** Occupancy Rate, Average Daily Rate (ADR), Revenue Per Available Room (RevPAR).

### 7. Multi-Tenant SaaS Architecture
* Organization/Property isolation.
* Granular Role-Based Access Control (Super Admin, Property Manager, Front Desk, Housekeeping, Laundry, Cashier).

---

## 🚀 Getting Started

### Prerequisites
* PHP 8.3+ (installed: 8.5)
* Composer 2.x
* Node.js 18+ & npm
* MySQL 8

### Installation

1. **Clone the repository:**
   ```bash
   git clone https://github.com/Enku-Solutions/Enqu-hotel-Management-system-SaaS.git
   cd Enqu-hotel-Management-system-SaaS
   ```

2. **Install PHP and Node dependencies:**
   ```bash
   composer install
   npm install
   ```

3. **Configure Environment:**
   ```bash
   cp .env.example .env
   php artisan key:generate
   ```
   Ensure your MySQL database credentials are configured in `.env`:
   ```env
   DB_CONNECTION=mysql
   DB_HOST=127.0.0.1
   DB_PORT=3306
   DB_DATABASE=enku_hotel_saas
   DB_USERNAME=root
   DB_PASSWORD=your_mysql_password
   ```

4. **Run Database Migrations:**
   ```bash
   php artisan migrate
   ```

5. **Start Development Servers:**
   ```bash
   # Terminal 1: Backend
   php artisan serve

   # Terminal 2: Frontend Vite
   npm run dev
   ```

---

## 🧪 Testing

Run PHP test suite:
```bash
php artisan test
```

Build production assets:
```bash
npm run build
```
