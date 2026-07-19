<?php

namespace Ibc\Tplink\Service;

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
        $specHtml = $this->extractSpecificationsBlock($html);

        $name = $this->extractModelName($html) ?: $this->normalizeArticle($fallbackName);
        $article = $this->normalizeArticle($name);

        return [
            'NAME' => $name,
            'ARTICLE' => $article,
            'CATEGORY' => $this->extractCategory($html, $sourceUrl),
            'WIFI_STANDARD' => $this->extractWifiStandard($specHtml, $html),
            'WIFI_SPEED' => $this->extractWifiSpeed($specHtml, $html),
            'WAN_SPEED' => $this->extractWanSpeed($specHtml, $html),
            'LAN_PORTS' => $this->extractLanPorts($specHtml, $html),
        ];
    }

    private function extractSpecificationsBlock(string $html): string
    {
        // Live pages use id="div_specifications" (class="specifications").
        if (preg_match(
            '#id=["\'](?:div_)?specifications["\'][^>]*>(.*?)(?:id=["\'](?:div_)?support["\']|id=["\']support["\']|</html>)#is',
            $html,
            $m
        )) {
            return $m[1];
        }
        if (preg_match('#class=["\'][^"\']*specifications[^"\']*["\'][^>]*>(.*?)(?:class=["\'][^"\']*support|</html>)#is', $html, $m)) {
            return $m[1];
        }
        if (preg_match('#Технические характеристики(.*?)(?:Поддержка|</html>)#isu', $html, $m)) {
            return $m[1];
        }

        return $html;
    }

    private function extractModelName(string $html): string
    {
        if (preg_match('#id=["\']ga-product-name["\'][^>]*data-name=["\']([^"\']+)["\']#i', $html, $m)) {
            return $this->normalizeArticle($this->clean($m[1]));
        }
        if (preg_match('#id=["\']ga-product-name["\'][^>]*>([^<]+)</#i', $html, $m)) {
            $name = $this->clean($m[1]);
            $name = preg_replace('#\s+V\d+(?:\.\d+)?$#i', '', $name) ?? $name;

            return $this->normalizeArticle($name);
        }
        if (preg_match('#class=["\'][^"\']*product-name[^"\']*["\'][^>]*>([^<]+)</#i', $html, $m)) {
            return $this->normalizeArticle($this->clean($m[1]));
        }
        if (preg_match('#"@type"\s*:\s*"Product"[^}]*?"name"\s*:\s*"([^"|]+)#is', $html, $m)) {
            $name = $this->clean($m[1]);
            if (preg_match('#((?:Archer|Deco|Tapo)\s+[A-Z0-9-]+|TL-[A-Z0-9-]+)#i', $name, $mm)) {
                return $this->normalizeArticle($mm[1]);
            }
        }
        if (preg_match('#<title>\s*((?:Archer|Deco|Tapo)\s+[A-Z0-9-]+|TL-[A-Z0-9-]+)#i', $html, $m)) {
            return $this->normalizeArticle($m[1]);
        }

        return '';
    }

    private function extractCategory(string $html, string $sourceUrl): string
    {
        // JSON-LD BreadcrumbList: item with position=2 (category under Home).
        if (preg_match_all(
            '#"@type"\s*:\s*"ListItem"[^}]*?"position"\s*:\s*(\d+)[^}]*?"name"\s*:\s*"([^"]+)"#is',
            $html,
            $matches,
            PREG_SET_ORDER
        )) {
            foreach ($matches as $m) {
                if ((int)$m[1] === 2) {
                    $name = $this->clean($m[2]);
                    if ($this->isValidCategory($name)) {
                        return $name;
                    }
                }
            }
            foreach ($matches as $m) {
                $name = $this->clean($m[2]);
                if ($this->isValidCategory($name)) {
                    return $name;
                }
            }
        }

        // Product menu breadcrumb link.
        if (preg_match(
            '#Product-Menu_Category_[^"\']*["\'][^>]*href=["\'][^"\']*wifi-router/?["\'][^>]*>([^<]+)</a>#i',
            $html,
            $m
        )) {
            $name = $this->clean($m[1]);
            if ($this->isValidCategory($name)) {
                return $name;
            }
        }

        if (preg_match('#/home-networking/wifi-router/#i', $sourceUrl)) {
            return 'Все маршрутизаторы Wi-Fi';
        }

        return 'Роутеры Wi-Fi';
    }

    private function isValidCategory(string $name): bool
    {
        if ($name === '' || strlen($name) > 80) {
            return false;
        }
        if (str_starts_with($name, '{') || str_contains($name, '"@context"')) {
            return false;
        }
        if (preg_match('#^(Archer|TL-|Deco|Tapo)\b#i', $name)) {
            return false;
        }
        if (!preg_match('#(маршрутизатор|роутер|Wi-?Fi|router)#iu', $name)) {
            return false;
        }

        return true;
    }

    /**
     * Wi-Fi generation from specifications table («Стандарты» / «Wi-Fi 6»), not nav chrome.
     */
    private function extractWifiStandard(string $specHtml, string $fullHtml): ?string
    {
        $html = $specHtml !== '' ? $specHtml : $fullHtml;

        if (preg_match(
            '#<th[^>]*>\s*(?:Стандарты|Wi-?Fi|Стандарт Wi-?Fi)\s*</th>\s*<td[^>]*>\s*<b>\s*(Wi-?Fi\s*[0-9]+(?:E)?)\s*</b>#isu',
            $html,
            $m
        )) {
            return $this->normalizeWifiStandard($m[1]);
        }
        if (preg_match(
            '#<th[^>]*>\s*(?:Стандарты|Wi-?Fi|Стандарт Wi-?Fi)\s*</th>\s*<td[^>]*>\s*(Wi-?Fi\s*[0-9]+(?:E)?)#isu',
            $html,
            $m
        )) {
            return $this->normalizeWifiStandard($m[1]);
        }
        // Spec bold chip near IEEE line inside specifications only.
        if (preg_match('#<b>\s*(Wi-?Fi\s*[0-9]+(?:E)?)\s*</b>\s*<br[^>]*>\s*IEEE\s*802\.11#iu', $html, $m)) {
            return $this->normalizeWifiStandard($m[1]);
        }
        // BE550-style: no «Стандарты» row — infer from IEEE in «Скорость Wi-Fi» cell.
        if (preg_match(
            '#<th[^>]*>\s*Скорость Wi-?Fi\s*</th>\s*<td[^>]*>(.*?)</td>#isu',
            $html,
            $m
        )) {
            $fromIeee = $this->wifiStandardFromIeee($m[1]);
            if ($fromIeee !== null) {
                return $fromIeee;
            }
        }

        return null;
    }

    private function wifiStandardFromIeee(string $cellHtml): ?string
    {
        if (preg_match('#802\.11be#i', $cellHtml)) {
            return 'Wi-Fi 7';
        }
        if (preg_match('#802\.11ax#i', $cellHtml)) {
            return 'Wi-Fi 6';
        }
        if (preg_match('#802\.11ac#i', $cellHtml)) {
            return 'Wi-Fi 5';
        }
        if (preg_match('#802\.11n#i', $cellHtml)) {
            return 'Wi-Fi 4';
        }

        return null;
    }

    /**
     * Class speed AX5400 / AC1200 / BE7200 / N300 from «Скорость Wi-Fi» row.
     */
    private function extractWifiSpeed(string $specHtml, string $fullHtml): ?string
    {
        $html = $specHtml !== '' ? $specHtml : $fullHtml;

        if (preg_match(
            '#<th[^>]*>\s*Скорость Wi-?Fi\s*</th>\s*<td[^>]*>\s*<b>\s*([A-Z]{1,3}\d{3,5})\s*</b>#isu',
            $html,
            $m
        )) {
            return strtoupper($m[1]);
        }
        if (preg_match(
            '#<th[^>]*>\s*(?:Wi-?Fi Speed|Wireless Speed)\s*</th>\s*<td[^>]*>\s*<b>\s*([A-Z]{1,3}\d{3,5})\s*</b>#isu',
            $html,
            $m
        )) {
            return strtoupper($m[1]);
        }
        // Title / H1 class marker as last resort (AX5400, BE7200…).
        if (preg_match('#<(?:title|h1)[^>]*>\s*([A-Z]{1,3}\d{3,5})\b#i', $fullHtml, $m)) {
            return strtoupper($m[1]);
        }

        return null;
    }

    private function extractWanSpeed(string $specHtml, string $fullHtml): ?string
    {
        $html = $specHtml !== '' ? $specHtml : $fullHtml;

        if (preg_match(
            '#(?:Порты Ethernet|Ethernet Ports)\s*</th>\s*<td[^>]*>(.*?)</td>#isu',
            $html,
            $m
        )) {
            $cell = $this->clean($m[1]);
            if (preg_match('#(?:порт\s+)?WAN\s*([^<\n;]+)#iu', $cell, $wm)) {
                return $this->cleanWan($wm[1]);
            }
            if (preg_match('#WAN[^0-9]*([0-9][^<\n;]*)#iu', $cell, $wm)) {
                return $this->cleanWan($wm[1]);
            }
        }
        if (preg_match('#(\d+\s*[×x]\s*)?порт\s+WAN\s+([0-9][^<\n,<]+)#iu', $html, $m)) {
            return $this->cleanWan($m[2]);
        }

        return null;
    }

    private function extractLanPorts(string $specHtml, string $fullHtml): ?int
    {
        $html = $specHtml !== '' ? $specHtml : $fullHtml;

        if (preg_match(
            '#(?:Порты Ethernet|Ethernet Ports)\s*</th>\s*<td[^>]*>(.*?)</td>#isu',
            $html,
            $m
        )) {
            $cell = html_entity_decode(strip_tags(str_replace(['<br>', '<br/>', '<br />'], "\n", $m[1])), ENT_QUOTES | ENT_HTML5, 'UTF-8');
            if (preg_match('#(\d+)\s*[×x]\s*порт(?:а|ов)?\s+LAN#iu', $cell, $lm)) {
                return (int)$lm[1];
            }
            if (preg_match('#(\d+)\s*[×x]\s*LAN#iu', $cell, $lm)) {
                return (int)$lm[1];
            }
        }
        if (preg_match('#(\d+)\s*[×x]\s*порт(?:а|ов)?\s+LAN#iu', $html, $m)) {
            return (int)$m[1];
        }
        if (preg_match('#(\d+)\s+гигабитн(?:ых|ый)\s+порт(?:а|ов)?\s+LAN#iu', $html, $m)) {
            return (int)$m[1];
        }
        if (preg_match('#Порты LAN:\s*(\d+)#iu', $html, $m)) {
            return (int)$m[1];
        }

        return null;
    }

    private function normalizeWifiStandard(string $value): string
    {
        $value = $this->clean($value);
        if (preg_match('#Wi-?Fi\s*([0-9]+(?:E)?)#i', $value, $m)) {
            return 'Wi-Fi ' . strtoupper($m[1]);
        }

        return $value;
    }

    private function cleanWan(string $value): string
    {
        $value = $this->clean($value);
        $value = preg_replace('#^[•\-\s]+#u', '', $value) ?? $value;
        // Drop trailing LAN leftovers if cell was joined.
        $value = preg_replace('#\s*\d+\s*[×x].*$#u', '', $value) ?? $value;

        return trim($value, " \t\n\r\0\x0B•");
    }

    private function normalizeArticle(string $name): string
    {
        $name = $this->clean($name);
        if (preg_match('#((?:Archer|Deco|Tapo)\s+[A-Z0-9-]+|TL-[A-Z0-9-]+)#i', $name, $m)) {
            $model = preg_replace('/\s+/u', ' ', $m[1]) ?? $m[1];
            if (preg_match('#^tl-#i', $model)) {
                return strtoupper($model);
            }

            return preg_replace_callback(
                '#^(Archer|Deco|Tapo)(\s+)([A-Z0-9-]+)$#i',
                static fn (array $mm) => ucfirst(strtolower($mm[1])) . $mm[2] . strtoupper($mm[3]),
                $model
            ) ?? $model;
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
