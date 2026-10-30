# Module 07: Hotel Reports, KPIs & SaaS Analytics

## 1. Overview & Objective
This module provides property managers, financial controllers, and SaaS administrators with real-time operational insights and industry-standard hotel performance metrics.

---

## 2. Hospitality Core Formulas & KPIs

| KPI Metric | Formula | Business Purpose |
| :--- | :--- | :--- |
| **Occupancy Rate (%)** | $\frac{\text{Occupied Rooms}}{\text{Total Available Rooms}} \times 100$ | Measures capacity utilization over a given date range. |
| **ADR (Average Daily Rate)** | $\frac{\text{Total Room Revenue}}{\text{Number of Rooms Sold}}$ | Measures average rental revenue earned per occupied room. |
| **RevPAR (Revenue Per Available Room)** | $\frac{\text{Total Room Revenue}}{\text{Total Available Rooms}}$ or $\text{ADR} \times \text{Occupancy Rate}$ | Evaluates hotel performance and pricing effectiveness. |
| **Cashier Shift Balance** | $\sum \text{Payments Collected (Cash, Card, Mobile)}$ per Shift | Daily cash drawer audit and shift handover reconciliation. |
| **Departmental Revenue Share** | $\frac{\text{Service Revenue (e.g. Laundry, F\&B)}}{\text{Total Gross Hotel Revenue}} \times 100$ | Analyzes revenue contribution from non-room ancillary services. |

---

## 3. Database & Query Design

Instead of bloating the database with redundant summary tables, analytics are computed dynamically through indexed aggregates and materialized daily snapshots:

### Table: `daily_hotel_stats` (Performance Snapshot Cache)
```sql
CREATE TABLE `daily_hotel_stats` (
    `id` BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `hotel_id` BIGINT UNSIGNED NOT NULL,
    `date` DATE NOT NULL,
    `total_rooms` INT UNSIGNED NOT NULL,
    `occupied_rooms` INT UNSIGNED NOT NULL,
    `out_of_order_rooms` INT UNSIGNED DEFAULT 0,
    `occupancy_rate` DECIMAL(5, 2) NOT NULL, -- e.g. 84.50%
    `room_revenue` DECIMAL(12, 2) NOT NULL,
    `laundry_revenue` DECIMAL(12, 2) NOT NULL DEFAULT 0.00,
    `pos_revenue` DECIMAL(12, 2) NOT NULL DEFAULT 0.00,
    `total_revenue` DECIMAL(12, 2) NOT NULL,
    `adr` DECIMAL(10, 2) NOT NULL,
    `revpar` DECIMAL(10, 2) NOT NULL,
    `created_at` TIMESTAMP NULL,
    `updated_at` TIMESTAMP NULL,
    FOREIGN KEY (`hotel_id`) REFERENCES `hotels`(`id`) ON DELETE CASCADE,
    UNIQUE KEY `hotel_daily_stat_unique` (`hotel_id`, `date`)
);
```

---

## 4. Backend Implementation Specification

### Service Class: `App\Services\AnalyticsService`:
* `getKpiSummary(Hotel $hotel, Carbon $startDate, Carbon $endDate): array`: Computes Occupancy %, ADR, RevPAR, and total revenue.
* `getDailyRevenueTrend(Hotel $hotel, int $days = 30): array`: Generates time-series data for frontend charts.
* `getCashierHandoverReport(Hotel $hotel, int $userId, Carbon $shiftStart, Carbon $shiftEnd): array`: Aggregates transactions processed by staff member.
* `exportReportCsv(string $reportType, array $params): StreamedResponse`: High-performance streaming CSV export.

---

## 5. Frontend (React + TypeScript) Specification

### Pages & Views:
* `resources/js/Pages/Reports/Index.tsx`: Executive Analytics Hub:
  * Top KPI Metric Cards (Occupancy %, RevPAR, ADR, Gross Revenue) with percentage change compared to previous period.
  * Date range selector (Today, Yesterday, Last 7 Days, This Month, Custom Range).
  * Line Chart: Daily Occupancy & Revenue Trend.
  * Donut Chart: Revenue Breakdown (Rooms vs Laundry vs Restaurant).
* `resources/js/Pages/Reports/CashierHandover.tsx`: Shift closing and payment collection printout.
* `resources/js/Pages/Reports/OccupancyCalendar.tsx`: Heatmap view of historical and projected occupancy.

---

## 6. Developer Task Checklist

- [ ] **Task 7.1:** Create migration for `daily_hotel_stats` cache table.
- [ ] **Task 7.2:** Implement `AnalyticsService` with optimized queries for Occupancy %, ADR, and RevPAR.
- [ ] **Task 7.3:** Integrate daily stats snapshot calculation into the Night Audit command.
- [ ] **Task 7.4:** Build Executive Analytics dashboard in React with Tailwind metric cards and interactive date pickers.
- [ ] **Task 7.5:** Integrate lightweight charts (e.g. Chart.js or Recharts) for revenue and occupancy trends.
- [ ] **Task 7.6:** Build Cashier Shift Handover report view with one-click print button.
- [ ] **Task 7.7:** Implement CSV export endpoint for financial and reservation records.
- [ ] **Task 7.8:** Write unit and feature tests validating mathematical accuracy of ADR, RevPAR, and Occupancy calculations.

---

## 7. QA Acceptance Criteria
1. Occupancy rate calculation accurately accounts for out-of-order rooms in available room denominator.
2. Analytics reports execute in under 300ms for date ranges up to 365 days.
3. Cashier handover amounts exactly match the sum of individual folio transactions marked with the staff user's ID.
