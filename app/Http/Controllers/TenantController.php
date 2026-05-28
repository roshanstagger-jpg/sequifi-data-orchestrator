<?php
declare(strict_types=1);

namespace App\Http\Controllers;

use App\Http\Requests\StoreTenantRequest;
use App\Models\Tenant;
use Illuminate\Support\Str;

class TenantController extends Controller
{
    public function index()
    {
        $tenants = Tenant::withCount('importRuns')->latest()->get();
        return view('tenants.index', compact('tenants'));
    }

    public function create()
    {
        return view('tenants.create');
    }

    public function store(StoreTenantRequest $request)
    {
        $token = Str::random(60);

        $tenant = Tenant::create([
            'name' => $request->name,
            'slug' => Str::slug($request->name) . '-' . Str::random(4),
            'api_token' => hash('sha256', $token),
        ]);

        return redirect()
            ->route('tenants.setup', $tenant)
            ->with('token', $token)
            ->with('success', 'Tenant created. Save the API token shown below — it will not be shown again.');
    }
}
