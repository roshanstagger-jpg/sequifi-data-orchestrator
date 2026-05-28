<?php
declare(strict_types=1);

namespace App\Services;

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
        $baseUrl = rtrim($tenant->sequifi_api_url ?? 'https://api.sequifi.com', '/');
        $dateTo  = now()->format('Y-m-d');

        try {
            $response = Http::withHeaders([
                'Authorization' => 'Bearer ' . $tenant->sequifi_bearer_token,
                'Accept'        => 'application/json',
            ])->get("{$baseUrl}/v1/sales", [
                'page'      => $page,
                'per_page'  => 200,
                'date_from' => $dateFrom,
                'date_to'   => $dateTo,
            ]);
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

        $body = $response->json();

        // Standard Marketplace API shape: { status, message, data: { Sales, current_page, ... } }
        if (isset($body['data']) && is_array($body['data'])) {
            return $body['data'];
        }

        // Some tenant endpoints return Sales/pagination at the root level
        if (isset($body['Sales']) || isset($body['current_page'])) {
            return $body;
        }

        // Neither shape matched — surface the raw response so the admin can diagnose
        $preview = substr(json_encode($body) ?: $response->body(), 0, 400);
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
    private function normalizeSale(array $sale): array
    {
        $row = [];

        $detailPrefixes = ['closer1_detail', 'closer2_detail', 'setter1_detail', 'setter2_detail'];

        foreach ($sale as $key => $value) {
            // Skip internal ID
            if ($key === 'id') {
                continue;
            }

            // Handle known nested detail objects
            if (in_array($key, $detailPrefixes, true)) {
                if (is_array($value) && $value !== null) {
                    $prefix = str_replace('_detail', '', $key);
                    $firstName = $value['first_name'] ?? '';
                    $lastName  = $value['last_name'] ?? '';
                    $row["{$prefix}_name"]  = trim("{$firstName} {$lastName}");
                    $row["{$prefix}_email"] = (string) ($value['email'] ?? '');
                }
                continue;
            }

            // Handle milestone_dates array
            if ($key === 'milestone_dates') {
                if (is_array($value)) {
                    foreach ($value as $milestone) {
                        if (!is_array($milestone)) {
                            continue;
                        }
                        $triggerName = $milestone['trigger_name'] ?? null;
                        $date        = $milestone['date'] ?? '';
                        if ($triggerName === null) {
                            continue;
                        }
                        // spaces → underscores, lowercased
                        $fieldName           = 'milestone_' . strtolower(str_replace(' ', '_', (string) $triggerName));
                        $row[$fieldName]     = (string) $date;
                    }
                }
                continue;
            }

            // Handle custom_field_values object
            if ($key === 'custom_field_values') {
                if (is_array($value)) {
                    foreach ($value as $fieldName => $fieldValue) {
                        if (is_array($fieldValue)) {
                            // skip nested arrays we don't know how to handle
                            Log::debug('SequifiApiService: skipping nested array in custom_field_values', ['key' => $fieldName]);
                            continue;
                        }
                        $row['custom_' . $fieldName] = (string) ($fieldValue ?? '');
                    }
                }
                continue;
            }

            // Skip arrays we don't know how to handle
            if (is_array($value)) {
                Log::debug('SequifiApiService: skipping unknown array field', ['key' => $key]);
                continue;
            }

            // Flat scalar field
            $row[$key] = (string) ($value ?? '');
        }

        return $row;
    }
}
