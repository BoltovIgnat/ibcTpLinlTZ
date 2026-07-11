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
        $pairs = [];
        $articleRe = preg_quote($article, '#');
        $patterns = [
            '#' . $articleRe . '\(([A-Z0-9]+)\)_V(\d+(?:\.\d+)?)_#iu',
            '#' . $articleRe . '\(([A-Z0-9]+)\)_V(\d+)#iu',
            '#' . $articleRe . '_V(\d+)_#iu',
        ];

        foreach ($patterns as $pattern) {
            if (!preg_match_all($pattern, $html, $matches, PREG_SET_ORDER)) {
                continue;
            }
            foreach ($matches as $m) {
                if (count($m) === 3) {
                    $pairs[$m[1] . '|' . $this->normalizeHw($m[2])] = [
                        'region' => strtoupper($m[1]),
                        'hw' => $this->normalizeHw($m[2]),
                    ];
                } elseif (count($m) === 2) {
                    $pairs['|' . $this->normalizeHw($m[1])] = [
                        'region' => null,
                        'hw' => $this->normalizeHw($m[1]),
                    ];
                }
            }
        }

        if ($pairs === [] && preg_match_all('#data-value="(V\d+)"#i', $html, $vm)) {
            foreach ($vm[1] as $hw) {
                $pairs[$hw] = ['region' => null, 'hw' => $this->normalizeHw($hw)];
            }
        }

        return array_values($pairs);
    }

    private function normalizeHw(string $hw): string
    {
        if (preg_match('#V(\d+)#i', $hw, $m)) {
            return 'V' . $m[1];
        }

        return strtoupper($hw);
    }
}
