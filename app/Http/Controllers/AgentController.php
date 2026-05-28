<?php
declare(strict_types=1);

namespace App\Http\Controllers;

use App\Models\Tenant;
use App\Services\SequifiApiService;
use Illuminate\Support\Facades\Log;

class AgentController extends Controller
{
    public function __construct(
        private readonly SequifiApiService $apiService,
    ) {}

    /**
     * Display the user ledger for a tenant.
     */
    public function index(Tenant $tenant)
    {
        $agents = $tenant->sequifiAgents()->paginate(50);

        return view('agents.index', compact('tenant', 'agents'));
    }

    /**
     * Pull all users from Sequifi API and upsert into the ledger.
     */
    public function sync(Tenant $tenant)
    {
        if (!$tenant->hasApiConfig()) {
            return back()->withErrors(['sync' => 'Sequifi API credentials are not configured. Complete API setup first.']);
        }

        try {
            $count = $this->apiService->syncAgents($tenant);
        } catch (\Throwable $e) {
            Log::error('Agent sync failed', [
                'tenant_id' => $tenant->id,
                'error'     => $e->getMessage(),
            ]);
            return back()->withErrors(['sync' => 'User sync failed: ' . $e->getMessage()]);
        }

        return redirect()
            ->route('tenants.agents.index', $tenant)
            ->with('success', "Synced {$count} users from Sequifi.");
    }
}
