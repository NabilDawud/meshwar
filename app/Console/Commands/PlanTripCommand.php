<?php

namespace App\Console\Commands;

use App\Services\TripPlannerService;
use Illuminate\Console\Command;

class PlanTripCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'meshwar:plan-trip {from : Origin landmark or city} {to : Destination landmark or city} {--time= : Preferred departure time (HH:MM)}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Plan a transit trip across Gaza corridors using deterministic graph search';

    /**
     * Execute the console command.
     */
    public function handle(TripPlannerService $planner): int
    {
        $from = (string) $this->argument('from');
        $to = (string) $this->argument('to');
        $time = (string) $this->option('time');

        $this->info("🔍 التخطيط لرحلة من: [{$from}] إلى: [{$to}]...");

        $result = $planner->plan($from, $to, $time ?: null);

        if (! $result['found']) {
            $this->warn('❌ '.$result['message']);

            return 0;
        }

        $this->info("✅ تم العثور على {$result['count']} خيار للرحلة:\n");

        foreach ($result['itineraries'] as $idx => $itin) {
            $num = $idx + 1;
            if ($itin['transfers'] === 0) {
                $this->line("<options=bold>الخيار #{$num}: رحلة مباشرة (بدون تبديل)</>");
                $this->line("  🚗 نوع المركبة: {$itin['vehicle']['type_arabic']} ({$itin['vehicle']['seat_count']} مقاعد) - السائق: {$itin['vehicle']['driver_name']}");
                $this->line("  ⏰ وقت الانطلاق: {$itin['departure_time']}");
                $this->line("  💰 إجمالي الأجرة التقديرية: {$itin['total_fare_ils']} ₪");
                $this->line("  📍 مسار الخط: {$itin['route_name']}");
            } else {
                $this->line("<options=bold>الخيار #{$num}: رحلة بمركبتين (تبديل واحد)</>");
                $this->line("  🔄 نقطة التبديل: <fg=yellow>{$itin['transfer_junction']}</>");
                $this->line("  ⏳ مدة الانتظار المقدرة للتبديل: {$itin['transfer_wait_minutes']} دقيقة");
                $this->line("  💰 إجمالي الأجرة لجميع المراحل: <fg=green>{$itin['total_fare_ils']} ₪</>");
                $this->line("    1️⃣ المرحلة الأولى: {$itin['leg_1']['from']} ➔ {$itin['leg_1']['to']} ({$itin['leg_1']['vehicle']['type_arabic']}) - {$itin['leg_1']['fare_ils']} ₪");
                $this->line("    2️⃣ المرحلة الثانية: {$itin['leg_2']['from']} ➔ {$itin['leg_2']['to']} ({$itin['leg_2']['vehicle']['type_arabic']}) - {$itin['leg_2']['fare_ils']} ₪");
            }
            $this->newLine();
        }

        if (! empty($result['ai_advice']['tip'])) {
            $provider = $result['ai_advice']['provider'] === 'anthropic_claude' ? 'الذكاء الاصطناعي (Claude)' : 'نصائح مشوار المعتمدة';
            $this->line("<fg=cyan>💡 نصيحة السفر [{$provider}]:</> ".$result['ai_advice']['tip']);
        }

        return 0;
    }
}
