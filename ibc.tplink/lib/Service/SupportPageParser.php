<?php

namespace Ibc\Tplink\Service;

final class SupportPageParser
{
    public function __construct(
        private readonly SourceFetcher $fetcher,
    ) {
    }

    public function supportUrlForSlug(string $slug): string
    {
        return $this->fetcher->absoluteUrl('/kz/support/download/' . $slug . '/');
    }

    /** @return list<string> relative or absolute version page URLs */
    public function versionPageUrls(string $slug): array
    {
        $html = $this->fetcher->get($this->supportUrlForSlug($slug));
        $urls = [];
        if (preg_match_all(
            '#id="version-list"[^>]*>(.*?)</ul>#is',
            $html,
            $blocks
        )) {
            foreach ($blocks[1] as $block) {
                if (preg_match_all('#href="([^"]+/support/download/' . preg_quote($slug, '#') . '/[^"]+)"#i', $block, $m)) {
                    foreach ($m[1] as $href) {
                        $urls[] = $this->fetcher->absoluteUrl($href);
                    }
                }
            }
        }
        if ($urls === []) {
            $urls[] = $this->supportUrlForSlug($slug);
        }

        return array_values(array_unique($urls));
    }

    /**
     * @return list<array{region: ?string, hw: string}>
     */
    public function parseRegionalHardware(string $html, string $article): array
    {
        $withRegion = [];
        $withoutRegion = [];
        $articleRe = preg_quote($article, '#');
        // Allow spaces around article in firmware filenames ("Archer AX72(EU)_V1_").
        $articleFlex = preg_replace('#\s+#', '\s*', $articleRe) ?? $articleRe;

        // Prefer explicit Article(REGION)_VHW patterns (docs / firmware titles).
        $regionalPatterns = [
            '#' . $articleFlex . '\(([A-Z]{2,3})\)_V(\d+(?:\.\d+)?)(?:_|[\s<"\'])#iu',
            '#' . $articleFlex . '\(([A-Z]{2,3})\)_V(\d+(?:\.\d+)?)#iu',
        ];
        foreach ($regionalPatterns as $pattern) {
            if (!preg_match_all($pattern, $html, $matches, PREG_SET_ORDER)) {
                continue;
            }
            foreach ($matches as $m) {
                $region = strtoupper($m[1]);
                $hw = $this->normalizeHw($m[2]);
                $withRegion[$region . '|' . $hw] = ['region' => $region, 'hw' => $hw];
            }
        }

        // Filenames without region: Article_V1_… — only keep if no regional pair for this HW.
        // Do not treat version-list data-value alone as catalog pairs (causes region=null noise).
        if (preg_match_all('#' . $articleFlex . '_V(\d+(?:\.\d+)?)(?:_|[\s<"\'])#iu', $html, $matches, PREG_SET_ORDER)) {
            foreach ($matches as $m) {
                $hw = $this->normalizeHw($m[1]);
                $hasRegional = false;
                foreach ($withRegion as $pair) {
                    if ($pair['hw'] === $hw) {
                        $hasRegional = true;
                        break;
                    }
                }
                if (!$hasRegional) {
                    $withoutRegion['|' . $hw] = ['region' => null, 'hw' => $hw];
                }
            }
        }

        $pairs = $withRegion;
        foreach ($withoutRegion as $key => $pair) {
            $hw = $pair['hw'];
            $hasRegional = false;
            foreach ($withRegion as $regional) {
                if ($regional['hw'] === $hw) {
                    $hasRegional = true;
                    break;
                }
            }
            if (!$hasRegional && !isset($pairs[$key])) {
                $pairs[$key] = $pair;
            }
        }

        if ($pairs === []) {
            return [];
        }

        return array_values($pairs);
    }

    /**
     * Canonical HW: V6, V6.20, V2.50.
     * "6" → V6; "6.2" / "V6.2" → V6.20; "6.20" → V6.20.
     */
    private function normalizeHw(string $hw): string
    {
        $hw = trim($hw);
        if (preg_match('#^V?(\d+)(?:\.(\d+))?$#i', $hw, $m)) {
            $major = $m[1];
            if (!isset($m[2]) || $m[2] === '') {
                return 'V' . $major;
            }
            $minor = $m[2];
            // One-digit minor (6.2) → two digits (6.20) to match TP-Link version-list.
            if (strlen($minor) === 1) {
                $minor .= '0';
            }

            return 'V' . $major . '.' . $minor;
        }
        if (preg_match('#V(\d+(?:\.\d+)?)#i', $hw, $m)) {
            return $this->normalizeHw($m[1]);
        }

        return strtoupper($hw);
    }
}
