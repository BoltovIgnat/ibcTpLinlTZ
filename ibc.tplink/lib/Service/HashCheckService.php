<?php

namespace Ibc\Tplink\Service;

final class HashCheckService
{
    /** @param list<string> $fullArticles */
    public function sha256Sorted(array $fullArticles): string
    {
        $articles = array_values(array_unique(array_map('trim', $fullArticles)));
        sort($articles, SORT_STRING);

        return hash('sha256', implode("\n", $articles));
    }
}
