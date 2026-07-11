<?php

namespace Ibc\Tplink\Service;

final class ImportServiceFactory
{
    public static function create(): ImportService
    {
        $fetcher = new SourceFetcher();
        $iblockId = (new IblockInstaller())->ensureCatalog();

        return new ImportService(
            new ListingParser($fetcher),
            new FullArticleResolver(
                new ProductPageParser($fetcher),
                new SupportPageParser($fetcher),
                $fetcher,
            ),
            new CatalogSyncService($iblockId),
            new ImportLogWriter(),
            new HashCheckService(),
        );
    }
}
