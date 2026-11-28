<?php

namespace App\Modules\Financial\Presentation\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Models\Customer;
use App\Models\InterestPolicy;
use App\Models\Payment;
use App\Models\Sale;
use App\Models\User;
use Illuminate\Support\Facades\Gate;
use Illuminate\View\View;

class FinancialWorkspaceController extends Controller
{
    public function __invoke(string $section): View
    {
        abort_unless(in_array($section, ['sales', 'payments', 'ledger', 'interest', 'reconciliation', 'audit', 'reports'], true), 404);

        if (in_array($section, ['interest', 'reconciliation', 'audit'], true)) {
            abort_unless(auth()->user()->role === User::ROLE_OWNER, 403);
        }

        if ($section === 'reports') {
            Gate::authorize('viewAny', Sale::class);
            Gate::authorize('viewAny', Payment::class);
            Gate::authorize('viewAny', Customer::class);

            return view('financial.reports');
        }

        if ($section === 'sales') {
            Gate::authorize('viewAny', Sale::class);
        } elseif ($section === 'payments') {
            Gate::authorize('viewAny', Payment::class);
        } elseif ($section === 'ledger') {
            Gate::authorize('viewAny', Customer::class);
        } elseif ($section === 'interest') {
            Gate::authorize('viewAny', InterestPolicy::class);
        } elseif ($section === 'reconciliation') {
            Gate::authorize('viewReconciliation');
        } elseif ($section === 'audit') {
            Gate::authorize('viewFinancialAudit');
        }

        return view('financial.workspace', ['section' => $section]);
    }
}
