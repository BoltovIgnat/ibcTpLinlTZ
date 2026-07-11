<?php

namespace Ibc\Tplink\Helper;

use Bitrix\Main\Config\Option;
use Bitrix\Main\Web\HttpClient;

/**
 * Загрузка логов с удалённого сервера (prod) по HTTP API.
 */
final class RemoteLogFetcher
{
    public const DEFAULT_PRODUCTION_BASE = 'https://ibcmoney.store/ibc/tplink/api/tplink/v1';

    private const MODULE_ID = 'ibc.tplink';

    private const OPTION_BASE = 'log_remote_api_base';

    /**
     * @return array<string, mixed>|null
     */
    public function fetchChannel(
        string $channel,
        int $tail = 200,
        ?string $date = null,
        ?string $level = null,
        ?string $search = null
    ): ?array {
        $base = rtrim($this->getApiBase(), '/');
        $channel = preg_replace('/[^a-z0-9_-]+/i', '', $channel) ?: 'import';
        $url = $base . '/admin/logs/' . $channel;

        $query = ['tail' => (string)max(1, min(2000, $tail))];
        if ($date !== null && $date !== '') {
            $query['date'] = $date;
        }
        if ($level !== null && $level !== '') {
            $query['level'] = $level;
        }
        if ($search !== null && $search !== '') {
            $query['search'] = $search;
        }

        return $this->request($url, $query);
    }

    /**
     * @return array<string, mixed>|null
     */
    public function fetchRuns(int $limit = 50): ?array
    {
        $base = rtrim($this->getApiBase(), '/');
        $url = $base . '/admin/logs/runs';

        return $this->request($url, ['limit' => (string)max(1, min(200, $limit))]);
    }

    /**
     * @return array<string, mixed>|null
     */
    public function fetchRun(string $file, ?int $tailItems = null): ?array
    {
        $base = rtrim($this->getApiBase(), '/');
        $url = $base . '/admin/logs/run';
        $query = ['file' => $file];
        if ($tailItems !== null) {
            $query['tail_items'] = (string)max(1, min(5000, $tailItems));
        }

        return $this->request($url, $query);
    }

    public function getApiBase(): string
    {
        $configured = trim((string)Option::get(self::MODULE_ID, self::OPTION_BASE, ''));
        if ($configured !== '') {
            return rtrim($configured, '/');
        }

        return self::DEFAULT_PRODUCTION_BASE;
    }

    /** @param array<string, string> $query @return array<string, mixed>|null */
    private function request(string $url, array $query): ?array
    {
        $token = trim((string)Option::get(self::MODULE_ID, 'api_log_read_token', ''));
        if ($token === '') {
            ImportLog::warning('Remote log fetch: api_log_read_token is empty');

            return null;
        }

        $query['token'] = $token;
        $url .= '?' . http_build_query($query);

        $client = new HttpClient([
            'socketTimeout' => 20,
            'streamTimeout' => 20,
            'disableSslVerification' => false,
        ]);
        $client->setHeader('Accept', 'application/json');
        $client->setHeader('X-Tplink-Log-Token', $token);

        $body = $client->get($url);
        $status = (int)$client->getStatus();
        if ($body === false || $status < 200 || $status >= 300) {
            ImportLog::error('Remote log fetch failed', [
                'url' => preg_replace('/([?&]token=)[^&]+/', '$1***', $url) ?? $url,
                'status' => $status,
                'error' => $client->getError(),
            ]);

            return null;
        }

        $decoded = json_decode($body, true);
        if (!is_array($decoded) || !($decoded['success'] ?? false)) {
            ImportLog::error('Remote log fetch: invalid response', [
                'status' => $status,
                'body_preview' => mb_substr((string)$body, 0, 500),
            ]);

            return null;
        }

        $data = $decoded['data'] ?? [];
        if (!is_array($data)) {
            return null;
        }

        $data['_remote'] = true;
        $data['_fetched_from'] = rtrim($this->getApiBase(), '/');

        return $data;
    }
}
