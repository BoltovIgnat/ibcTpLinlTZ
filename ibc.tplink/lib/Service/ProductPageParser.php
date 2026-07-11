<?php

namespace Ibc\Tplink\Service;

use Ibc\Tplink\Exception\ParseException;

final class ProductPageParser
{
    public function __construct(
        private readonly SourceFetcher $fetcher,
    ) {
    }

    /** @return array<string, scalar|null> */
    public function parse(string $sourceUrl, string $fallbackName): array
    {
        $html = $this->fetcher->get($sourceUrl);

        $name = $this->extractModelName($html) ?: $fallbackName;
        $article = $this->normalizeArticle($name);

        return [
            'NAME' => $name,
            'ARTICLE' => $article,
            'CATEGORY' => $this->extractCategory($html),
            'WIFI_STANDARD' => $this->extractSpec($html, ['Wi-Fi standard', 'Стандарт Wi-Fi', 'Wi-Fi 6', 'Wi-Fi 7', '802.11']),
            'WIFI_SPEED' => $this->extractSpec($html, ['Wi-Fi Speed', 'Скорость Wi-Fi', 'Скорость беспроводной']),
            'WAN_SPEED' => $this->extractSpec($html, ['WAN', 'WAN Speed', 'Скорость WAN']),
            'LAN_PORTS' => $this->extractLanPorts($html),
        ];
    }

    private function extractModelName(string $html): string
    {
        if (preg_match('#id="model-title-name"[^>]*>([^<]+)</#i', $html, $m)) {
            return $this->clean($m[1]);
        }
        if (preg_match('#<h1[^>]*>([^<]+)</h1>#i', $html, $m)) {
            $h1 = $this->clean($m[1]);
            if (preg_match('#^(Archer|TL-|Deco|Tapo)\s+[A-Z0-9-]+#i', $h1, $mm)) {
                return $this->normalizeArticle($mm[0]);
            }
        }
        if (preg_match('#<title>([^|<]+)#i', $html, $m)) {
            $title = $this->clean($m[1]);
            if (preg_match('#(Archer|TL-|Deco)\s+[A-Z0-9-]+#i', $title, $mm)) {
                return $this->normalizeArticle($mm[0]);
            }
        }

        return '';
    }

    private function extractCategory(string $html): string
    {
        if (preg_match('#breadcrumb[^>]*>.*?wifi-router.*?</#is', $html)) {
            return 'Роутеры Wi-Fi';
        }
        if (preg_match('#>([^<]*(?:Wi-Fi|WiFi)[^<]*(?:Router|роут)[^<]*)<#iu', $html, $m)) {
            return $this->clean($m[1]);
        }

        return 'Роутеры Wi-Fi';
    }

    /** @param list<string> $labels */
    private function extractSpec(string $html, array $labels): ?string
    {
        foreach ($labels as $label) {
            $pattern = '#>' . preg_quote($label, '#') . '\s*</[^>]+>\s*<[^>]+>\s*([^<]+)#iu';
            if (preg_match($pattern, $html, $m)) {
                return $this->clean($m[1]);
            }
        }
        if (preg_match('#Wi-Fi\s*6[^<]{0,40}802\.11ax#i', $html, $m)) {
            return 'Wi-Fi 6 (802.11ax)';
        }
        if (preg_match('#Wi-Fi\s*7#i', $html)) {
            return 'Wi-Fi 7';
        }

        return null;
    }

    private function extractLanPorts(string $html): ?int
    {
        if (preg_match('#(?:LAN|ЛВС)[^0-9]{0,20}(\d+)\s*(?:Port|порт)#iu', $html, $m)) {
            return (int)$m[1];
        }
        if (preg_match('#(\d+)\s*(?:×|x)\s*(?:LAN|Gigabit)#iu', $html, $m)) {
            return (int)$m[1];
        }

        return null;
    }

    private function normalizeArticle(string $name): string
    {
        $name = $this->clean($name);
        if (preg_match('#^(Archer|TL-|Deco|Tapo)\s+[A-Z0-9-]+#i', $name, $m)) {
            return preg_replace('/\s+/u', ' ', $m[0]) ?? $name;
        }

        return $name;
    }

    private function clean(string $text): string
    {
        $text = html_entity_decode(strip_tags($text), ENT_QUOTES | ENT_HTML5, 'UTF-8');
        $text = preg_replace('/\s+/u', ' ', trim($text)) ?? '';

        return $text;
    }
}
