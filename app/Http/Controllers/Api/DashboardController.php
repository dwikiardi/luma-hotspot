<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\VisitorProfile;
use App\Services\AnalyticsEngine;
use App\Services\ComplaintReducer;
use App\Services\ROICalculator;
use Illuminate\Http\Request;

class DashboardController extends Controller
{
    public function __construct(
        private AnalyticsEngine $analytics,
        private ComplaintReducer $complaintReducer,
        private ROICalculator $roiCalculator
    ) {}

    public function summary(Request $request, int $tenantId)
    {
        $period = $request->query('period', '7days');

        return response()->json($this->analytics->getDashboardSummary($tenantId, $period));
    }

    public function complaints(Request $request, int $tenantId)
    {
        $period = $request->query('period', '7days');

        return response()->json($this->complaintReducer->getComplaintReport($tenantId, $period));
    }

    public function roi(Request $request, int $tenantId)
    {
        $assumptions = $request->only([
            'data_value_per_contact',
            'complaint_handling_cost',
            'happy_guest_spend_multiplier',
        ]);

        return response()->json($this->roiCalculator->calculate($tenantId, $assumptions));
    }

    public function visitors(Request $request, int $tenantId)
    {
        $query = VisitorProfile::where('tenant_id', $tenantId)
            ->with('user');

        if ($request->has('visitor_type')) {
            $query->where('visitor_type', $request->input('visitor_type'));
        }

        if ($request->has('identity_type')) {
            $query->whereHas('user', function ($q) use ($request) {
                $q->where('identity_type', $request->input('identity_type'));
            });
        }

        $perPage = $request->input('per_page', 15);
        $visitors = $query->orderByDesc('last_visit_at')->paginate($perPage);

        return response()->json($visitors);
    }
}
