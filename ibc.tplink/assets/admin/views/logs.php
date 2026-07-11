<?php
/** @var string $tab */
/** @var int $tail */
/** @var string|null $date */
/** @var string|null $level */
/** @var string|null $search */
/** @var list<string> $channels */
/** @var array<string, mixed>|null $channelData */
/** @var list<array<string, mixed>> $runs */
/** @var array<string, mixed>|null $runPayload */
if (!defined('B_PROLOG_INCLUDED') || B_PROLOG_INCLUDED !== true) {
    die();
}

$adminBase = \Ibc\Tplink\Helper\AdminPath::adminBase();
$tab = $tab ?? 'import';

include __DIR__ . '/_layout_start.php';
?>

<section class="tplink-hero tplink-reveal">
    <span class="tplink-eyebrow">Diagnostics</span>
    <h1 class="tplink-h1">Логи импорта</h1>
    <p class="tplink-lead">Файловые каналы <code>import</code> / <code>api</code> и JSON-прогоны синхронизации.</p>
</section>

<div class="tplink-tabs tplink-reveal" style="--delay:60ms">
    <a class="tplink-tab <?= $tab === 'import' ? 'is-active' : '' ?>" href="<?= htmlspecialcharsbx($adminBase) ?>/logs.php?tab=import">import</a>
    <a class="tplink-tab <?= $tab === 'api' ? 'is-active' : '' ?>" href="<?= htmlspecialcharsbx($adminBase) ?>/logs.php?tab=api">api</a>
    <a class="tplink-tab <?= $tab === 'runs' ? 'is-active' : '' ?>" href="<?= htmlspecialcharsbx($adminBase) ?>/logs.php?tab=runs">прогоны JSON</a>
</div>

<?php if ($tab !== 'runs'): ?>
<section class="tplink-section tplink-reveal" style="--delay:100ms">
    <form class="tplink-filters" method="get">
        <input type="hidden" name="tab" value="<?= htmlspecialcharsbx($tab) ?>">
        <div class="tplink-bezel tplink-filter-bezel">
            <div class="tplink-bezel-inner tplink-filter-row">
                <label>
                    <span class="tplink-label">Дата</span>
                    <input class="tplink-input" type="date" name="date" value="<?= htmlspecialcharsbx((string)($date ?? '')) ?>">
                </label>
                <label>
                    <span class="tplink-label">Уровень</span>
                    <select class="tplink-input" name="level">
                        <option value="">Все</option>
                        <?php foreach (['ERROR', 'WARNING', 'INFO', 'DEBUG'] as $lv): ?>
                            <option value="<?= $lv ?>" <?= ($level ?? '') === $lv ? 'selected' : '' ?>><?= $lv ?></option>
                        <?php endforeach; ?>
                    </select>
                </label>
                <label>
                    <span class="tplink-label">Поиск</span>
                    <input class="tplink-input" type="text" name="search" value="<?= htmlspecialcharsbx((string)($search ?? '')) ?>" placeholder="текст в строке">
                </label>
                <label>
                    <span class="tplink-label">Tail</span>
                    <input class="tplink-input tplink-input--sm" type="number" name="tail" min="50" max="2000" value="<?= (int)$tail ?>">
                </label>
                <button type="submit" class="tplink-btn group">
                    <span>Применить</span>
                    <span class="tplink-btn-icon" aria-hidden="true">
                        <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5"><path d="M5 12h14M13 6l6 6-6 6"/></svg>
                    </span>
                </button>
            </div>
        </div>
    </form>

    <div class="tplink-bezel tplink-log-bezel">
        <div class="tplink-bezel-inner">
            <?php $entries = $channelData['entries'] ?? []; ?>
            <div class="tplink-log-meta">
                канал <strong><?= htmlspecialcharsbx((string)($channelData['channel'] ?? $tab)) ?></strong>
                · <?= (int)($channelData['returned'] ?? 0) ?> / <?= (int)($channelData['total_lines'] ?? 0) ?> строк
            </div>
            <?php if ($entries === []): ?>
                <p class="tplink-empty">Записей нет. Запустите импорт или проверьте дату.</p>
            <?php else: ?>
                <div class="tplink-log-lines">
                    <?php foreach ($entries as $entry): ?>
                        <?php
                        $lv = strtoupper((string)($entry['level'] ?? ''));
                        $lvClass = match ($lv) {
                            'ERROR' => 'tplink-log-line--error',
                            'WARNING' => 'tplink-log-line--warn',
                            default => '',
                        };
                        ?>
                        <div class="tplink-log-line <?= $lvClass ?>">
                            <time><?= htmlspecialcharsbx((string)($entry['time'] ?? '')) ?></time>
                            <span class="tplink-log-level"><?= htmlspecialcharsbx($lv) ?></span>
                            <span class="tplink-log-msg"><?= htmlspecialcharsbx((string)($entry['message'] ?? '')) ?></span>
                            <?php if (!empty($entry['context'])): ?>
                                <pre class="tplink-log-ctx"><?= htmlspecialcharsbx(json_encode($entry['context'], JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT)) ?></pre>
                            <?php endif; ?>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        </div>
    </div>
</section>

<?php else: ?>

<section class="tplink-section tplink-reveal" style="--delay:100ms">
    <div class="tplink-bento tplink-bento--runs">
        <article class="tplink-card tplink-card--wide">
            <div class="tplink-bezel">
                <div class="tplink-bezel-inner tplink-table-wrap">
                    <h2 class="tplink-h3">Прогоны</h2>
                    <table class="tplink-table">
                        <thead>
                            <tr><th>Файл</th><th>Старт</th><th>new</th><th>errors</th><th></th></tr>
                        </thead>
                        <tbody>
                        <?php if ($runs === []): ?>
                            <tr><td colspan="5">Нет JSON-логов в /local/logs/</td></tr>
                        <?php else: ?>
                            <?php foreach ($runs as $run): ?>
                                <?php $c = $run['counters'] ?? []; ?>
                                <tr>
                                    <td><code><?= htmlspecialcharsbx((string)($run['file'] ?? '')) ?></code></td>
                                    <td><?= htmlspecialcharsbx((string)($run['started_at'] ?? '')) ?></td>
                                    <td><?= (int)($c['new'] ?? 0) ?></td>
                                    <td><?= (int)($c['errors'] ?? 0) ?></td>
                                    <td><a class="tplink-text-link" href="?tab=runs&run=<?= urlencode((string)($run['file'] ?? '')) ?>">открыть</a></td>
                                </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </article>

        <?php if ($runPayload !== null && ($runPayload['found'] ?? false)): ?>
        <article class="tplink-card tplink-card--wide">
            <div class="tplink-bezel">
                <div class="tplink-bezel-inner">
                    <h2 class="tplink-h3"><?= htmlspecialcharsbx((string)($runPayload['file'] ?? '')) ?></h2>
                    <?php $p = $runPayload['payload'] ?? []; ?>
                    <div class="tplink-pills">
                        <?php foreach (($p['counters'] ?? []) as $k => $v): ?>
                            <span class="tplink-pill"><?= htmlspecialcharsbx((string)$k) ?> <?= (int)$v ?></span>
                        <?php endforeach; ?>
                    </div>
                    <?php if (!empty($p['hash_check'])): ?>
                        <p class="tplink-hint">sha256: <code><?= htmlspecialcharsbx((string)($p['hash_check']['sorted_full_articles_sha256'] ?? '')) ?></code></p>
                    <?php endif; ?>
                    <pre class="tplink-json"><?= htmlspecialcharsbx(json_encode($p, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT)) ?></pre>
                </div>
            </div>
        </article>
        <?php endif; ?>
    </div>
</section>

<?php endif; ?>

<?php include __DIR__ . '/_layout_end.php'; ?>
