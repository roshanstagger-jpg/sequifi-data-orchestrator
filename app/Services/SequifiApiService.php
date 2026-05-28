<?php
declare(strict_types=1);

namespace App\Services;

use App\Models\SequifiAgent;
use App\Models\Tenant;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class SequifiApiService
{
    /**
     * Test the API connection using the tenant's credentials.
     * Fetches one page and returns ['columns' => [...], 'total' => N].
     *
     * @throws \RuntimeException on auth failure, server error, or network error
     */
    public function testConnection(Tenant $tenant): array
    {
        $dateFrom = now()->subDays($tenant->api_lookback_days ?? 90)->format('Y-m-d');
        $data = $this->fetchPage($tenant, $dateFrom, 1);

        $sales = $data['Sales'] ?? [];
        $total = (int) ($data['total'] ?? 0);

        $columns = [];
        if (!empty($sales)) {
            $normalized = $this->normalizeSale($sales[0]);
            $columns = array_keys($normalized);
        }

        return [
            'columns' => $columns,
            'total'   => $total,
        ];
    }

    /**
     * Fetch all sales pages and return a flat array of normalized rows.
     *
     * @throws \RuntimeException on failure
     */
    public function fetchAllSales(Tenant $tenant, ?int $lookbackDays = null): array
    {
        $days     = $lookbackDays ?? $tenant->api_lookback_days ?? 90;
        $dateFrom = now()->subDays($days)->format('Y-m-d');

        $firstPage = $this->fetchPage($tenant, $dateFrom, 1);
        $lastPage  = (int) ($firstPage['last_page'] ?? 1);
        $rows      = array_map(fn($sale) => $this->normalizeSale($sale), $firstPage['Sales'] ?? []);

        for ($page = 2; $page <= $lastPage; $page++) {
            $pageData = $this->fetchPage($tenant, $dateFrom, $page);
            foreach ($pageData['Sales'] ?? [] as $sale) {
                $rows[] = $this->normalizeSale($sale);
            }
        }

        return $rows;
    }

    /**
     * Fetch a single page from the Sequifi API.
     *
     * @throws \RuntimeException on HTTP error or network failure
     */
    private function fetchPage(Tenant $tenant, string $dateFrom, int $page): array
    {
        $baseUrl  = rtrim($tenant->sequifi_api_url ?? 'https://marketplace-api.sequifi.com', '/');
        $dateTo   = now()->format('Y-m-d');
        $params   = ['page' => $page, 'per_page' => 200, 'date_from' => $dateFrom, 'date_to' => $dateTo];

        try {
            // Vercel's PHP Lambda uses OpenSSL 1.0.2k-fips which cannot negotiate TLS
            // with marketplace-api.sequifi.com.  When VERCEL_URL is present we route
            // through api/sequifi.js (Node.js, modern TLS) in the same deployment.
            $proxyUrl = $this->proxyUrl();

            if ($proxyUrl) {
                $response = Http::post($proxyUrl, [
                    'endpoint' => "{$baseUrl}/v1/sales",
                    'token'    => $tenant->sequifi_bearer_token,
                    'params'   => $params,
                ]);
            } else {
                $response = Http::withHeaders([
                    'Authorization' => 'Bearer ' . $tenant->sequifi_bearer_token,
                    'Accept'        => 'application/json',
                ])->get("{$baseUrl}/v1/sales", $params);
            }
        } catch (\Throwable $e) {
            throw new \RuntimeException('Network error contacting Sequifi API: ' . $e->getMessage(), 0, $e);
        }

        if ($response->status() === 401 || $response->status() === 403) {
            throw new \RuntimeException('Authentication failed. Please check your Bearer token.');
        }

        if ($response->status() === 400) {
            $body    = $response->json() ?? [];
            $invalid = $body['data']['invalid_params'] ?? [];
            $allowed = $body['data']['allowed_params'] ?? [];
            $detail  = '';
            if ($invalid) {
                $detail = ' Invalid params: ' . implode(', ', (array) $invalid);
            }
            if ($allowed) {
                $detail .= ' Allowed: ' . implode(', ', (array) $allowed);
            }
            throw new \RuntimeException('Bad request to Sequifi API.' . $detail);
        }

        if (!$response->successful()) {
            $body    = $response->json() ?? [];
            $message = $body['message'] ?? ('API request failed with status ' . $response->status());
            throw new \RuntimeException($message);
        }

        $rawBody = $response->body();
        $body    = $response->json();

        // If body isn't JSON at all, surface it directly (HTML redirect, plain text, etc.)
        if ($body === null) {
            $preview = substr($rawBody, 0, 500);
            throw new \RuntimeException(
                'Sequifi API returned a non-JSON response (HTTP ' . $response->status() . '). '
                . 'This usually means the base URL is wrong or the token is invalid for this domain. '
                . 'Raw: ' . $preview
            );
        }

        // Standard Marketplace API shape: { status, message, data: { Sales, current_page, ... } }
        if (isset($body['data']) && is_array($body['data'])) {
            return $body['data'];
        }

        // Some tenant endpoints return Sales/pagination at the root level
        if (isset($body['Sales']) || isset($body['current_page'])) {
            return $body;
        }

        // Neither shape matched — surface the raw response so the admin can diagnose
        $preview = substr(json_encode($body), 0, 400);
        throw new \RuntimeException(
            'Unexpected response format from Sequifi API. Raw response: ' . $preview
        );
    }

    /**
     * Normalize a single sale record into a flat string key→value map.
     * - Flat scalar fields: cast to string
     * - closer1_detail/closer2_detail/setter1_detail/setter2_detail: {prefix}_name, {prefix}_email
     * - milestone_dates array: milestone_{trigger_name} = date
     * - custom_field_values object: custom_{key} = value
     * - 'id' is skipped (internal DB id)
     */
    /**
     * Normalize a single sale record into a flat string key→value map.
     *
     * Scalar fields are cast to string as-is.  Structured fields are flattened:
     *
     * closer1_detail / closer2_detail / setter1_detail / setter2_detail
     *   → {prefix}_id, {prefix}_name (first+last), {prefix}_dismiss,
     *     {prefix}_terminate, {prefix}_contract_ended, {prefix}_stop_payroll
     *
     * last_milestone  (object: name/trigger/value/date)
     *   → last_milestone_name, last_milestone_trigger,
     *     last_milestone_value, last_milestone_date
     *
     * all_milestone   (array of: name/trigger/value/date/is_projected)
     *   → milestone_{snake_name}          (date string)
     *   → milestone_{snake_name}_value    (commission amount)
     *   → milestone_{snake_name}_trigger  (trigger label, e.g. "Final Payment")
     *   → milestone_{snake_name}_projected (0 or 1)
     *   e.g. "M1 Date" → milestone_m1_date, milestone_m1_date_value …
     *
     * milestone_dates (legacy swagger shape: [{trigger_name, date}])
     *   → milestone_{snake_trigger_name}
     *
     * custom_field_values (object)
     *   → custom_{key}
     *
     * Internal 'id' is skipped.
     */
    private function normalizeSale(array $sale): array
    {
        $row = [];

        $detailKeys = ['closer1_detail', 'closer2_detail', 'setter1_detail', 'setter2_detail'];

        foreach ($sale as $key => $value) {
            // ── skip internal DB id ────────────────────────────────────────────
            if ($key === 'id') {
                continue;
            }

            // ── person detail objects ──────────────────────────────────────────
            if (in_array($key, $detailKeys, true)) {
                if (is_array($value)) {
                    $prefix = str_replace('_detail', '', $key);
                    $row["{$prefix}_id"]              = (string) ($value['id'] ?? '');
                    $row["{$prefix}_name"]             = trim(($value['first_name'] ?? '') . ' ' . ($value['last_name'] ?? ''));
                    $row["{$prefix}_dismiss"]          = (string) ($value['dismiss'] ?? '');
                    $row["{$prefix}_terminate"]        = (string) ($value['terminate'] ?? '');
                    $row["{$prefix}_contract_ended"]   = (string) ($value['contract_ended'] ?? '');
                    $row["{$prefix}_stop_payroll"]     = (string) ($value['stop_payroll'] ?? '');
                }
                continue;
            }

            // ── last_milestone object ──────────────────────────────────────────
            if ($key === 'last_milestone') {
                if (is_array($value)) {
                    $row['last_milestone_name']    = (string) ($value['name']    ?? '');
                    $row['last_milestone_trigger'] = (string) ($value['trigger'] ?? '');
                    $row['last_milestone_value']   = (string) ($value['value']   ?? '');
                    $row['last_milestone_date']    = (string) ($value['date']    ?? '');
                }
                continue;
            }

            // ── all_milestone array ────────────────────────────────────────────
            if ($key === 'all_milestone') {
                if (is_array($value)) {
                    foreach ($value as $ms) {
                        if (!is_array($ms) || !isset($ms['name'])) {
                            continue;
                        }
                        // "M1 Date" → "m1_date", "Final Payment" → "final_payment"
                        $slug = strtolower(str_replace([' ', '-'], '_', (string) $ms['name']));
                        $row["milestone_{$slug}"]           = (string) ($ms['date']         ?? '');
                        $row["milestone_{$slug}_value"]     = (string) ($ms['value']        ?? '');
                        $row["milestone_{$slug}_trigger"]   = (string) ($ms['trigger']      ?? '');
                        $row["milestone_{$slug}_projected"] = (string) ($ms['is_projected'] ?? '0');
                    }
                }
                continue;
            }

            // ── milestone_dates (legacy swagger shape) ─────────────────────────
            if ($key === 'milestone_dates') {
                if (is_array($value)) {
                    foreach ($value as $ms) {
                        if (!is_array($ms)) {
                            continue;
                        }
                        $trigger   = $ms['trigger_name'] ?? ($ms['trigger'] ?? null);
                        if ($trigger === null) {
                            continue;
                        }
                        $slug              = strtolower(str_replace([' ', '-'], '_', (string) $trigger));
                        $row["milestone_{$slug}"] = (string) ($ms['date'] ?? '');
                    }
                }
                continue;
            }

            // ── custom_field_values object ─────────────────────────────────────
            if ($key === 'custom_field_values') {
                if (is_array($value)) {
                    foreach ($value as $cfKey => $cfVal) {
                        if (is_array($cfVal)) {
                            Log::debug('SequifiApiService: skipping nested array in custom_field_values', ['key' => $cfKey]);
                            continue;
                        }
                        $row['custom_' . $cfKey] = (string) ($cfVal ?? '');
                    }
                }
                continue;
            }

            // ── skip any remaining unknown nested arrays ───────────────────────
            if (is_array($value)) {
                Log::debug('SequifiApiService: skipping unknown array field', ['key' => $key]);
                continue;
            }

            // ── flat scalar ────────────────────────────────────────────────────
            $row[$key] = (string) ($value ?? '');
        }

        return $row;
    }

    // ─────────────────────────────────────────────────────────────────────────
    // User / Agent ledger
    // ─────────────────────────────────────────────────────────────────────────

    /**
     * Fetch all users from GET /v1/users (paginated) and return raw rows.
     *
     * @throws \RuntimeException on failure
     */
    public function fetchAllUsers(Tenant $tenant): array
    {
        $rows  = [];
        $page  = 1;

        do {
            $data      = $this->fetchUsersPage($tenant, $page);
            $users     = $data['users'] ?? $data['Users'] ?? $data['data'] ?? [];
            $lastPage  = (int) ($data['last_page'] ?? $data['lastPage'] ?? 1);

            foreach ($users as $user) {
                if (is_array($user)) {
                    $rows[] = $user;
                }
            }

            $page++;
        } while ($page <= $lastPage);

        return $rows;
    }

    /**
     * Sync all Sequifi users into the sequifi_agents table for this tenant.
     * Uses upsert keyed on (tenant_id, sequifi_id).
     *
     * Returns the number of records upserted.
     *
     * @throws \RuntimeException on failure
     */
    public function syncAgents(Tenant $tenant): int
    {
        $users = $this->fetchAllUsers($tenant);
        $now   = now();
        $rows  = [];

        foreach ($users as $user) {
            $firstName = (string) ($user['first_name'] ?? '');
            $lastName  = (string) ($user['last_name']  ?? '');
            $fullName  = trim($firstName . ' ' . $lastName) ?: null;

            $rows[] = [
                'tenant_id'   => $tenant->id,
                'sequifi_id'  => (string) ($user['id'] ?? ''),
                'first_name'  => $firstName ?: null,
                'last_name'   => $lastName  ?: null,
                'full_name'   => $fullName,
                'email'       => ($user['email']   ?? null) ?: null,
                'phone'       => ($user['phone']   ?? $user['phone_number'] ?? null) ?: null,
                'worker_type' => ($user['worker_type'] ?? $user['workerType'] ?? null) ?: null,
                'position'    => ($user['position'] ?? $user['job_title'] ?? null) ?: null,
                'location'    => ($user['location'] ?? $user['office'] ?? $user['location_name'] ?? null) ?: null,
                'status'      => ($user['status']   ?? null) ?: null,
                'raw_data'    => json_encode($user),
                'synced_at'   => $now,
                'created_at'  => $now,
                'updated_at'  => $now,
            ];
        }

        if (empty($rows)) {
            return 0;
        }

        // Upsert: update everything except tenant_id/sequifi_id on conflict
        SequifiAgent::upsert(
            $rows,
            ['tenant_id', 'sequifi_id'],
            ['first_name', 'last_name', 'full_name', 'email', 'phone',
             'worker_type', 'position', 'location', 'status', 'raw_data',
             'synced_at', 'updated_at']
        );

        return count($rows);
    }

    /**
     * Fetch a single page from GET /v1/users.
     *
     * @throws \RuntimeException on HTTP error or network failure
     */
    private function fetchUsersPage(Tenant $tenant, int $page): array
    {
        $baseUrl = rtrim($tenant->sequifi_api_url ?? 'https://marketplace-api.sequifi.com', '/');
        $params  = ['page' => $page, 'per_page' => 100];

        try {
            $proxyUrl = $this->proxyUrl();

            if ($proxyUrl) {
                $response = Http::post($proxyUrl, [
                    'endpoint' => "{$baseUrl}/v1/users",
                    'token'    => $tenant->sequifi_bearer_token,
                    'params'   => $params,
                ]);
            } else {
                $response = Http::withHeaders([
                    'Authorization' => 'Bearer ' . $tenant->sequifi_bearer_token,
                    'Accept'        => 'application/json',
                ])->get("{$baseUrl}/v1/users", $params);
            }
        } catch (\Throwable $e) {
            throw new \RuntimeException('Network error contacting Sequifi API (users): ' . $e->getMessage(), 0, $e);
        }

        if ($response->status() === 401 || $response->status() === 403) {
            throw new \RuntimeException('Authentication failed fetching users. Please check your Bearer token.');
        }

        if (!$response->successful()) {
            $body    = $response->json() ?? [];
            $message = $body['message'] ?? ('Users API request failed with status ' . $response->status());
            throw new \RuntimeException($message);
        }

        $body = $response->json();

        if ($body === null) {
            $preview = substr($response->body(), 0, 300);
            throw new \RuntimeException('Non-JSON response from Sequifi users endpoint. Raw: ' . $preview);
        }

        // Standard envelope: { status, message, data: { users, current_page, last_page, ... } }
        if (isset($body['data']) && is_array($body['data'])) {
            return $body['data'];
        }

        // Flat: { users: [...], current_page, last_page }
        if (isset($body['users']) || isset($body['Users'])) {
            return $body;
        }

        // Direct array of users
        if (isset($body[0]) || empty($body)) {
            return ['users' => $body, 'last_page' => 1];
        }

        $preview = substr(json_encode($body), 0, 300);
        throw new \RuntimeException('Unexpected response format from Sequifi users endpoint. Raw: ' . $preview);
    }

    /**
     * Return the internal proxy URL when running on Vercel, null otherwise.
     * VERCEL_URL is automatically injected by Vercel for every deployment.
     */
    private function proxyUrl(): ?string
    {
        $vercelUrl = env('VERCEL_URL');
        if (!$vercelUrl) {
            return null;
        }

        $base = str_starts_with($vercelUrl, 'http') ? $vercelUrl : 'https://' . $vercelUrl;
        return rtrim($base, '/') . '/sequifi-proxy';
    }
}
