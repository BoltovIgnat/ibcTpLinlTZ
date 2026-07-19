<?php

namespace Ibc\Tplink\Service;

use Bitrix\Main\Config\Option;
use Ibc\Tplink\Dto\ProductVariantDto;
use Ibc\Tplink\Dto\SourceCardDto;
use Ibc\Tplink\Helper\ImportLog;

final class ImportService
{
    public function __construct(
        private readonly ListingParser $listingParser,
        private readonly FullArticleResolver $resolver,
        private readonly CatalogSyncService $sync,
        private readonly ImportLogWriter $logWriter,
        private readonly HashCheckService $hashCheck,
    ) {
    }

    /** @return array<string, mixed> */
    public function run(bool $dryRun = false, ?string $csvPath = null): array
    {
        $startedAt = date('c');
        $sourceUrl = (string)Option::get(
            'ibc.tplink',
            'source_list_url',
            'https://www.tp-link.com/kz/home-networking/wifi-router/'
        );

        ImportLog::info('Import started', ['dry_run' => $dryRun, 'source_url' => $sourceUrl]);

        $cards = $this->listingParser->collect($sourceUrl);
        ImportLog::info('Listing collected', ['card_count' => count($cards)]);
        $counters = ['new' => 0, 'updated' => 0, 'unchanged' => 0, 'missing' => 0, 'errors' => 0, 'needs_review' => 0];
        $items = [];
        $errors = [];
        $seenElementIds = [];
        $fullArticles = [];

        foreach ($cards as $card) {
            try {
                $variants = $this->resolver->resolve($card);
            } catch (\Throwable $e) {
                ++$counters['errors'];
                $errors[] = ['message' => $e->getMessage(), 'url' => $card->sourceUrl, 'context' => ['slug' => $card->slug]];
                ImportLog::error('Card resolve failed', ['slug' => $card->slug, 'url' => $card->sourceUrl, 'error' => $e->getMessage()]);
                continue;
            }

            foreach ($variants as $variant) {
                // Hash check must cover ALL FULL_ARTICLE including needs_review (A.6).
                $fullArticles[] = $variant->fullArticle;
                if ($variant->needsReview) {
                    ++$counters['needs_review'];
                }

                if ($dryRun) {
                    $items[] = $this->logItem($variant, 'dry_run');
                    continue;
                }

                try {
                    $result = $this->sync->sync($variant, date('c'));
                    ++$counters[$result->status];
                    if ($result->elementId !== null && $result->elementId > 0) {
                        $seenElementIds[] = $result->elementId;
                    }
                    $items[] = $this->logItem($variant, $result->status, $result->changedFields);
                } catch (\Throwable $e) {
                    ++$counters['errors'];
                    $errors[] = ['message' => $e->getMessage(), 'url' => $variant->sourceUrl, 'context' => ['full_article' => $variant->fullArticle]];
                    $items[] = $this->logItem($variant, 'error');
                    ImportLog::error('Sync failed', [
                        'full_article' => $variant->fullArticle,
                        'url' => $variant->sourceUrl,
                        'error' => $e->getMessage(),
                    ]);
                }
            }
        }

        if (!$dryRun) {
            foreach ($this->sync->markMissing($seenElementIds) as $missing) {
                ++$counters['missing'];
                $items[] = [
                    'article' => $missing['article'],
                    'status' => 'missing',
                    'url' => '',
                    'changed_fields' => ['MISSING_AT_SOURCE'],
                ];
            }
        }

        $finishedAt = date('c');
        $payload = [
            'started_at' => $startedAt,
            'finished_at' => $finishedAt,
            'source_url' => $sourceUrl,
            'counters' => $counters,
            'items' => $items,
            'errors' => $errors,
            'hash_check' => [
                'sorted_full_articles_sha256' => $this->hashCheck->sha256Sorted($fullArticles),
                'card_count' => count($cards),
                'element_count' => count(array_filter($items, static fn ($i) => ($i['status'] ?? '') !== 'missing')),
            ],
        ];

        $logPath = $this->logWriter->write($payload);
        if ($csvPath !== null && $csvPath !== '') {
            $this->logWriter->writeCsv($csvPath, $items);
        }

        $payload['log_path'] = $logPath;

        ImportLog::info('Import finished', [
            'dry_run' => $dryRun,
            'counters' => $counters,
            'log_path' => $logPath,
            'hash_sha256' => $payload['hash_check']['sorted_full_articles_sha256'] ?? '',
        ]);

        return $payload;
    }

    /** @param list<string> $changed @return array<string, mixed> */
    private function logItem(ProductVariantDto $variant, string $status, array $changed = []): array
    {
        return [
            'article' => $variant->needsReview ? ($variant->fallbackKey ?? $variant->fullArticle) : $variant->fullArticle,
            'full_article' => $variant->fullArticle,
            'name' => $variant->name,
            'category' => (string)($variant->fields['CATEGORY'] ?? ''),
            'status' => $status,
            'url' => $variant->sourceUrl,
            'changed_fields' => $changed,
            'needs_review' => $variant->needsReview,
        ];
    }
}
