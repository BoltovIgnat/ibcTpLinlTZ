<?php

namespace Ibc\Tplink\Service;

use Bitrix\Main\Config\Option;
use Ibc\Tplink\Dto\SourceCardDto;

final class ListingParser
{
    public function __construct(
        private readonly SourceFetcher $fetcher,
    ) {
    }

    /** @return list<SourceCardDto> */
    public function collect(string $listUrl): array
    {
        $html = $this->fetcher->get($listUrl);
        $cards = $this->parseListingHtml($html, $listUrl);

        if (Option::get('ibc.tplink', 'use_sitemap', 'Y') === 'Y') {
            $sitemapUrl = (string)Option::get(
                'ibc.tplink',
                'source_sitemap_url',
                'https://www.tp-link.com/kz/sitemap.xml'
            );
            try {
                $cards = $this->mergeCards($cards, $this->parseSitemap($sitemapUrl));
            } catch (\Throwable) {
                // listing-only fallback
            }
        }

        usort($cards, static fn (SourceCardDto $a, SourceCardDto $b) => strcmp($a->slug, $b->slug));

        return $cards;
    }

    /** @return list<SourceCardDto> */
    private function parseListingHtml(string $html, string $listUrl): array
    {
        $cards = [];
        $basePath = parse_url($listUrl, PHP_URL_PATH) ?: '/kz/home-networking/wifi-router/';

        if (preg_match_all(
            '#<a[^>]+href="([^"]*' . preg_quote(trim($basePath, '/'), '#') . '/([a-z0-9-]+)/?)"[^>]*>(.*?)</a>#is',
            $html,
            $matches,
            PREG_SET_ORDER
        )) {
            foreach ($matches as $m) {
                $slug = strtolower($m[2]);
                if ($slug === '' || in_array($slug, ['wifi-router', 'home-networking'], true)) {
                    continue;
                }
                $name = $this->cleanText(strip_tags($m[3]));
                if ($name === '' || strlen($name) > 120) {
                    $name = $this->slugToName($slug);
                }
                $cards[$slug] = new SourceCardDto($slug, $name, $this->fetcher->absoluteUrl($m[1]));
            }
        }

        if ($cards === []) {
            foreach ($this->extractSlugs($html) as $slug) {
                $cards[$slug] = new SourceCardDto(
                    $slug,
                    $this->slugToName($slug),
                    $this->fetcher->absoluteUrl('/kz/home-networking/wifi-router/' . $slug . '/')
                );
            }
        }

        return array_values($cards);
    }

    /** @return list<SourceCardDto> */
    private function parseSitemap(string $sitemapUrl): array
    {
        $xml = $this->fetcher->get($sitemapUrl);
        $cards = [];
        if (preg_match_all(
            '#<loc>(https://www\.tp-link\.com/kz/home-networking/wifi-router/([a-z0-9-]+)/?)</loc>#i',
            $xml,
            $matches,
            PREG_SET_ORDER
        )) {
            foreach ($matches as $m) {
                $slug = strtolower($m[2]);
                $cards[$slug] = new SourceCardDto(
                    $slug,
                    $this->slugToName($slug),
                    rtrim($m[1], '/') . '/'
                );
            }
        }

        return array_values($cards);
    }

    /** @param list<SourceCardDto> $primary @param list<SourceCardDto> $extra @return list<SourceCardDto> */
    private function mergeCards(array $primary, array $extra): array
    {
        $map = [];
        foreach ($primary as $card) {
            $map[$card->slug] = $card;
        }
        foreach ($extra as $card) {
            if (!isset($map[$card->slug])) {
                $map[$card->slug] = $card;
            }
        }

        return array_values($map);
    }

    /** @return list<string> */
    private function extractSlugs(string $html): array
    {
        preg_match_all('#/home-networking/wifi-router/([a-z0-9-]+)/?#i', $html, $m);

        return array_values(array_unique(array_map('strtolower', $m[1] ?? [])));
    }

    private function slugToName(string $slug): string
    {
        $parts = explode('-', $slug);
        $parts = array_map(static fn (string $p) => ctype_digit($p) ? $p : ucfirst($p), $parts);

        return implode(' ', $parts);
    }

    private function cleanText(string $text): string
    {
        $text = html_entity_decode($text, ENT_QUOTES | ENT_HTML5, 'UTF-8');
        $text = preg_replace('/\s+/u', ' ', trim($text)) ?? '';

        return $text;
    }
}
