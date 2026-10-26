<?php

namespace App\Services\Inpatient;

use App\Models\Admission;
use App\Models\AdmissionCharge;
use App\Models\Bed;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class RoomChargeService
{
    private const DEFAULT_ROOM_RATE = 1500.00;

    /**
     * Generate daily room charges from last charged date up to $upToDate.
     */
    public function generateDailyCharges(Admission $admission, User $actor, ?string $upToDate = null): int
    {
        if (! $admission->bed_id) {
            return 0;
        }

        $upTo = $upToDate ? Carbon::parse($upToDate) : now();
        $count = 0;

        // Find last charged date
        $lastCharge = AdmissionCharge::query()
            ->where('admission_id', $admission->id)
            ->where('category', AdmissionCharge::CATEGORY_ROOM)
            ->where('status', '!=', AdmissionCharge::STATUS_VOIDED)
            ->orderByDesc('charge_date')
            ->first();

        $startDate = $lastCharge
            ? Carbon::parse($lastCharge->charge_date)->addDay()
            : Carbon::parse($admission->admitted_at)->startOfDay();

        // If already charged up to yesterday/today appropriately
        if ($startDate->greaterThan($upTo->startOfDay())) {
            return 0;
        }

        // Don't charge beyond discharge date if discharged
        if ($admission->status === Admission::STATUS_DISCHARGED && $admission->discharged_at) {
            $dischargeDate = Carbon::parse($admission->discharged_at)->startOfDay();
            if ($upTo->startOfDay()->greaterThan($dischargeDate)) {
                $upTo = $dischargeDate;
            }
        }

        if ($startDate->greaterThan($upTo->startOfDay())) {
            return 0;
        }

        $rate = $this->getRoomRate($admission);
        $bed = Bed::find($admission->bed_id);
        $ward = $admission->ward;

        $current = $startDate->copy();
        while ($current->lessThanOrEqualTo($upTo->startOfDay())) {
            $sourceKey = 'room_daily:'.$admission->id.':'.$current->toDateString();
            $sourceId = crc32($sourceKey);

            // Check if already exists
            $exists = AdmissionCharge::query()
                ->where('admission_id', $admission->id)
                ->where('category', AdmissionCharge::CATEGORY_ROOM)
                ->where('charge_date', $current->toDateString())
                ->where('status', '!=', AdmissionCharge::STATUS_VOIDED)
                ->exists();

            if (! $exists) {
                try {
                    AdmissionCharge::create([
                        'admission_id' => $admission->id,
                        'patient_id' => $admission->patient_id,
                        'charge_date' => $current->toDateString(),
                        'charge_time' => '00:00:00',
                        'category' => AdmissionCharge::CATEGORY_ROOM,
                        'description' => 'Room & Bed Charge - '.($ward?->name ?? 'Ward').($bed ? ' - Bed '.$bed->label : ''),
                        'billable_service_id' => null,
                        'medicine_id' => null,
                        'source_type' => 'room_daily',
                        'source_id' => $sourceId,
                        'quantity' => 1,
                        'unit_price' => $rate,
                        'discount_amount' => 0,
                        'subtotal' => $rate,
                        'total' => $rate,
                        'status' => AdmissionCharge::STATUS_UNBILLED,
                        'bed_id' => $admission->bed_id,
                        'ward_id' => $admission->ward_id,
                        'created_by' => $actor->id,
                    ]);
                    $count++;
                } catch (\Throwable $e) {
                    // Skip on duplicate (unique constraint)
                    continue;
                }
            }

            $current->addDay();
        }

        return $count;
    }

    private function getRoomRate(Admission $admission): float
    {
        try {
            if (Schema::hasTable('inpatient_billing_settings')) {
                $setting = DB::table('inpatient_billing_settings')->where('key', 'room_rate_default')->first();
                if ($setting && $setting->value) {
                    $val = json_decode($setting->value, true);
                    if (isset($val['amount'])) {
                        return (float) $val['amount'];
                    }
                }
            }
        } catch (\Throwable $e) {
            // ignore
        }

        return self::DEFAULT_ROOM_RATE;
    }
}
