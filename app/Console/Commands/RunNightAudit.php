<?php

namespace App\Console\Commands;

use App\Models\Hotel;
use App\Services\NightAuditService;
use Carbon\Carbon;
use Illuminate\Console\Command;

class RunNightAudit extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'hotel:night-audit {--hotel= : Specific hotel ID} {--date= : Specific audit date YYYY-MM-DD}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Automated daily night audit: post room revenue, taxes, and lock transactions.';

    /**
     * Execute the console command.
     */
    public function handle(NightAuditService $nightAuditService): int
    {
        $hotelId = $this->option('hotel');
        $date = $this->option('date') ?: Carbon::yesterday()->toDateString();

        $query = Hotel::query();
        if ($hotelId) {
            $query->where('id', $hotelId);
        }

        $hotels = $query->get();

        if ($hotels->isEmpty()) {
            $this->warn('No hotels found to audit.');

            return self::SUCCESS;
        }

        $this->info("Starting Night Audit for {$hotels->count()} hotel(s) on date: {$date}");

        foreach ($hotels as $hotel) {
            $this->line("Processing [{$hotel->id}] {$hotel->name}...");

            try {
                $log = $nightAuditService->runAudit($hotel, $date);
                $this->info("✓ Hotel [{$hotel->id}] completed. Occupied: {$log->occupied_rooms_count}, Revenue: {$log->total_room_revenue_posted}, Tax: {$log->total_tax_posted}");
            } catch (\Throwable $e) {
                $this->error("✗ Hotel [{$hotel->id}] failed: {$e->getMessage()}");
            }
        }

        $this->info('Night audit run finished.');

        return self::SUCCESS;
    }
}
