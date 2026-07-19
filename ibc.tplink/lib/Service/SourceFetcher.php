<?php

namespace Ibc\Tplink\Service;

use Bitrix\Main\Config\Option;
use Ibc\Tplink\Exception\FetchException;
use Ibc\Tplink\Helper\ImportLog;

class SourceFetcher
{
    public function get(string $url): string
    {
        $retries = max(1, (int)Option::get('ibc.tplink', 'http_retries', '3'));
        $timeout = max(5, (int)Option::get('ibc.tplink', 'http_timeout', '60'));
        $ua = (string)Option::get('ibc.tplink', 'user_agent', 'ibc-tplink-import/1.0');
        $delayMs = max(0, (int)Option::get('ibc.tplink', 'request_delay_ms', '200'));

        $lastError = '';
        for ($attempt = 1; $attempt <= $retries; ++$attempt) {
            if ($attempt > 1) {
                usleep(250000 * $attempt);
            }
            if ($delayMs > 0) {
                usleep($delayMs * 1000);
            }

            $ch = curl_init($url);
            curl_setopt_array($ch, [
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_FOLLOWLOCATION => true,
                CURLOPT_MAXREDIRS => 5,
                CURLOPT_CONNECTTIMEOUT => 15,
                CURLOPT_TIMEOUT => $timeout,
                CURLOPT_USERAGENT => $ua,
                CURLOPT_HTTPHEADER => ['Accept: text/html,application/xhtml+xml,*/*'],
                CURLOPT_ENCODING => '',
            ]);
            $body = curl_exec($ch);
            $code = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE);
            $err = curl_error($ch);
            curl_close($ch);

            if ($body !== false && $code >= 200 && $code < 300) {
                return (string)$body;
            }

            $lastError = $err !== '' ? $err : ('HTTP ' . $code);
            if ($attempt < $retries) {
                ImportLog::warning('HTTP fetch retry', ['url' => $url, 'attempt' => $attempt, 'error' => $lastError]);
            }
        }

        ImportLog::error('HTTP fetch failed', ['url' => $url, 'error' => $lastError]);
        throw new FetchException('Failed to fetch ' . $url . ': ' . $lastError);
    }

    public function absoluteUrl(string $pathOrUrl): string
    {
        if (preg_match('#^https?://#i', $pathOrUrl)) {
            return $pathOrUrl;
        }
        $base = rtrim((string)Option::get('ibc.tplink', 'site_base_url', 'https://www.tp-link.com'), '/');
        if ($pathOrUrl === '' || $pathOrUrl[0] !== '/') {
            $pathOrUrl = '/' . $pathOrUrl;
        }

        return $base . $pathOrUrl;
    }
}
