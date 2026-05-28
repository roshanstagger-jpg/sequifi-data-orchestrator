/**
 * Thin Node.js proxy for Sequifi API calls.
 *
 * Vercel's PHP Lambda ships with OpenSSL 1.0.2k-fips which cannot negotiate
 * TLS with marketplace-api.sequifi.com (requires TLS 1.2+ cipher suites that
 * the old OpenSSL rejects). Node.js uses a modern TLS stack (OpenSSL 3.x) so
 * this proxy handles the HTTPS leg while PHP stays in charge of auth/logic.
 *
 * POST /sequifi-proxy
 * Body: { endpoint: string, token: string, params: object }
 * Returns: the raw JSON response from Sequifi (same status code)
 */
export default async function handler(req, res) {
    if (req.method !== 'POST') {
        return res.status(405).json({ error: 'Method not allowed' });
    }

    const { endpoint, token, params } = req.body ?? {};

    if (!endpoint || !token) {
        return res.status(400).json({ error: 'Missing endpoint or token' });
    }

    // SSRF guard — only allow requests to *.sequifi.com
    let url;
    try {
        url = new URL(endpoint);
    } catch {
        return res.status(400).json({ error: 'Invalid endpoint URL' });
    }

    if (!url.hostname.endsWith('.sequifi.com')) {
        return res.status(400).json({ error: 'Endpoint must be a *.sequifi.com domain' });
    }

    // Append query params
    if (params && typeof params === 'object') {
        Object.entries(params).forEach(([k, v]) => {
            if (v !== undefined && v !== null) {
                url.searchParams.set(String(k), String(v));
            }
        });
    }

    try {
        const response = await fetch(url.toString(), {
            method: 'GET',
            headers: {
                Authorization: `Bearer ${token}`,
                Accept: 'application/json',
            },
        });

        const text = await response.text();
        let data;
        try {
            data = JSON.parse(text);
        } catch {
            // Return raw text wrapped so PHP can inspect it
            data = { _raw: text, _status: response.status };
        }

        return res.status(response.status).json(data);
    } catch (error) {
        return res.status(500).json({ error: `Proxy fetch failed: ${error.message}` });
    }
}
