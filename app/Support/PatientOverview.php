<?php

namespace App\Support;

use App\Models\Measurement;
use App\Models\Patient;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;

final class PatientOverview
{
    /**
     * @param  Collection<int, Measurement>  $recentUploads
     */
    public function __construct(
        public readonly Patient $patient,
        public readonly Carbon $from,
        public readonly Carbon $to,
        public readonly MeasurementStats $stats,
        public readonly TrendChart $chart,
        public readonly Collection $recentUploads,
    ) {}
}
