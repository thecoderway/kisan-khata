<?php

namespace App\Modules\Shops\Presentation\Controllers\Web;

use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;

class GeographicLookupController
{
    public function states(): JsonResponse
    {
        return response()->json(['data' => DB::table('villages')
            ->select('stateCode as code', 'stateNameEnglish as name', 'stateNameLocal as local_name')
            ->distinct()->orderBy('stateNameEnglish')->get()]);
    }

    public function districts(string $state): JsonResponse
    {
        return response()->json(['data' => DB::table('villages')->where('stateCode', $state)
            ->select('districtCode as code', 'districtNameEnglish as name', 'districtNameLocal as local_name')
            ->distinct()->orderBy('districtNameEnglish')->get()]);
    }

    public function subdistricts(string $state, string $district): JsonResponse
    {
        return response()->json(['data' => DB::table('villages')
            ->where('stateCode', $state)->where('districtCode', $district)
            ->select('subdistrictCode as code', 'subdistrictNameEnglish as name', 'subdistrictNameLocal as local_name')
            ->distinct()->orderBy('subdistrictNameEnglish')->get()]);
    }

    public function villages(string $state, string $district, string $subdistrict): JsonResponse
    {
        return response()->json(['data' => DB::table('villages')
            ->where('stateCode', $state)->where('districtCode', $district)->where('subdistrictCode', $subdistrict)
            ->select('villageCode as code', 'villageNameEnglish as name', 'villageNameLocal as local_name')
            ->orderBy('villageNameEnglish')->get()]);
    }
}
