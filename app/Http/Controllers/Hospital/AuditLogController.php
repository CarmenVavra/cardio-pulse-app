<?php

namespace App\Http\Controllers\Hospital;

use App\Enums\UserRole;
use App\Http\Controllers\Controller;
use App\Http\Requests\AuditLogFilterRequest;
use App\Models\User;
use App\Services\AuditLogService;
use App\Support\AuditLogFormatter;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Prüfprotokoll ansehen, filtern und exportieren (nur Admins).
 */
class AuditLogController extends Controller
{
    public function index(AuditLogFilterRequest $request, AuditLogService $audit): View
    {
        $filters = $request->filters();
        $logs = $audit->paginate($filters);
        $groups = AuditLogFormatter::GROUPS;
        $doctors = User::query()
            ->withTrashed()
            ->where('role', UserRole::Staff)
            ->orderBy('name')
            ->get();

        return view('hospital.audit.index', compact('filters', 'logs', 'groups', 'doctors'));
    }

    public function export(AuditLogFilterRequest $request, AuditLogService $audit): StreamedResponse
    {
        return $audit->export($request->filters());
    }
}
