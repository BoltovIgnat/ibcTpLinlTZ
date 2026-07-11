<?php

/**
 * Standalone source probe (no Bitrix): php local/modules/ibc.tplink/tools/probe_source.php
 */

if (PHP_SAPI !== 'cli') {
    exit(1);
}

$listUrl = 'https://www.tp-link.com/kz/home-networking/wifi-router/';
$sitemapUrl = 'https://www.tp-link.com/kz/sitemap.xml';

function fetch(string $url): string
{
    $ch = curl_init($url);
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_FOLLOWLOCATION => true,
        CURLOPT_TIMEOUT => 60,
        CURLOPT_USERAGENT => 'ibc-tplink-import/1.0',
        CURLOPT_SSL_VERIFYPEER => false,
        CURLOPT_SSL_VERIFYHOST => 0,
    ]);
    $body = curl_exec($ch);
    $code = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    if ($body === false || $code < 200 || $code >= 300) {
        throw new RuntimeException("HTTP $code for $url");
    }

    return (string)$body;
}

$html = fetch($listUrl);
preg_match_all('#/home-networking/wifi-router/([a-z0-9-]+)/?#i', $html, $listing);
$listingSlugs = array_values(array_unique(array_map('strtolower', $listing[1] ?? [])));

$xml = fetch($sitemapUrl);
preg_match_all(
    '#<loc>https://www\.tp-link\.com/kz/home-networking/wifi-router/([a-z0-9-]+)/?</loc>#i',
    $xml,
    $sitemap
);
$sitemapSlugs = array_values(array_unique(array_map('strtolower', $sitemap[1] ?? [])));

$merged = array_values(array_unique(array_merge($listingSlugs, $sitemapSlugs)));
sort($merged);

echo 'Listing slugs: ' . count($listingSlugs) . PHP_EOL;
echo 'Sitemap slugs: ' . count($sitemapSlugs) . PHP_EOL;
echo 'Merged slugs: ' . count($merged) . PHP_EOL;

$sample = 'archer-ax55';
$support = fetch("https://www.tp-link.com/kz/support/download/$sample/");
preg_match_all('#id="version-list"[^>]*>(.*?)</ul>#is', $support, $blocks);
$versionUrls = [];
foreach ($blocks[1] ?? [] as $block) {
    if (preg_match_all('#href="([^"]+/support/download/' . preg_quote($sample, '#') . '/[^"]+)"#i', $block, $m)) {
        $versionUrls = array_merge($versionUrls, $m[1]);
    }
}
if ($versionUrls === []) {
    echo "Sample $sample: no version-list, using main support page\n";
    $versionUrls = ["/kz/support/download/$sample/"];
}

echo 'Sample version pages: ' . count(array_unique($versionUrls)) . PHP_EOL;

$article = 'Archer AX55';
$totalPairs = 0;
foreach (array_slice(array_unique($versionUrls), 0, 3) as $rel) {
    $url = str_starts_with($rel, 'http') ? $rel : 'https://www.tp-link.com' . $rel;
    $vh = fetch($url);
    $re = preg_quote($article, '#');
    preg_match_all('#' . $re . '\(([A-Z0-9]+)\)_V(\d+)#iu', $vh, $pairs, PREG_SET_ORDER);
    $totalPairs += count($pairs);
    echo "  $url -> " . count($pairs) . " region/hw pairs\n";
}
echo "Sample pairs (first 3 version pages): $totalPairs\n";
