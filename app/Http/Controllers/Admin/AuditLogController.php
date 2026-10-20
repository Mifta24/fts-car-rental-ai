<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Concerns\ResolvesCurrentRental;
use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\View\View;

class AuditLogController extends Controller
{
    use ResolvesCurrentRental;

    public function index(Request $request): View
    {
        $rental = $this->currentRental($request);

        $area = $request->query('area');

        $logs = $rental->auditLogs()
            ->with('user')
            ->when(is_string($area) && $area !== '', fn ($q) => $q->where('action', 'like', addcslashes($area, '%_\\').'.%'))
            ->latest('id')
            ->paginate(30)
            ->withQueryString();

        return view('admin.audit-log.index', [
            'rental' => $rental,
            'logs' => $logs,
            'area' => $area,
            'areas' => ['booking', 'vehicle', 'inventory', 'maintenance', 'pricing', 'inspection', 'knowledge_item', 'handover', 'team'],
        ]);
    }
}
