<?php
/** @var array<string, mixed> $data */
if (!defined('B_PROLOG_INCLUDED') || B_PROLOG_INCLUDED !== true) {
    die();
}

$stats = $data['stats'] ?? [];
$lastRun = $data['last_run'] ?? null;
$recentRuns = $data['recent_runs'] ?? [];
$cli = htmlspecialcharsbx((string)($data['cli_command'] ?? ''));
$sourceUrl = htmlspecialcharsbx((string)($data['source_url'] ?? ''));
$iblockCode = htmlspecialcharsbx((string)($data['iblock_code'] ?? ''));
$adminBase = \Ibc\Tplink\Helper\AdminPath::adminBase();
/** @var array<string, mixed>|null $importResult */
/** @var string|null $importError */
$importLocked = (bool)($data['import_locked'] ?? false);

include __DIR__ . '/_layout_start.php';
?>

<?php if ($importError !== null): ?>
<div class="tplink-alert tplink-alert--error tplink-reveal is-visible">
    <strong>Ошибка импорта</strong>
    <p><?= htmlspecialcharsbx($importError) ?></p>
</div>
<?php endif; ?>

<?php if ($importResult !== null): ?>
<div class="tplink-alert tplink-alert--success tplink-reveal is-visible">
    <strong>Импорт завершён</strong>
    <?php $c = $importResult['counters'] ?? []; ?>
    <div class="tplink-pills" style="margin-top:0.75rem">
        <span class="tplink-pill">new <?= (int)($c['new'] ?? 0) ?></span>
        <span class="tplink-pill">updated <?= (int)($c['updated'] ?? 0) ?></span>
        <span class="tplink-pill">unchanged <?= (int)($c['unchanged'] ?? 0) ?></span>
        <span class="tplink-pill">missing <?= (int)($c['missing'] ?? 0) ?></span>
        <span class="tplink-pill tplink-pill--warn">errors <?= (int)($c['errors'] ?? 0) ?></span>
    </div>
    <?php if (!empty($importResult['log_path'])): ?>
        <p class="tplink-hint" style="margin-top:0.75rem">Лог: <code><?= htmlspecialcharsbx((string)$importResult['log_path']) ?></code></p>
    <?php endif; ?>
</div>
<?php endif; ?>

<section class="tplink-hero tplink-reveal">
    <span class="tplink-eyebrow">Catalog Sync Console</span>
    <h1 class="tplink-h1">Импорт TP-Link</h1>
    <p class="tplink-lead">CLI-синхронизация каталога Wi‑Fi роутеров с tp-link.com в инфоблок <code><?= $iblockCode ?></code>.</p>
</section>

<section class="tplink-bento tplink-reveal" style="--delay:80ms">
    <article class="tplink-card tplink-card--hero">
        <div class="tplink-bezel">
            <div class="tplink-bezel-inner">
                <span class="tplink-stat-label">Элементов в каталоге</span>
                <span class="tplink-stat-value"><?= (int)($stats['elements_total'] ?? 0) ?></span>
                <span class="tplink-stat-meta">активных <?= (int)($stats['active'] ?? 0) ?></span>
            </div>
        </div>
    </article>

    <article class="tplink-card">
        <div class="tplink-bezel">
            <div class="tplink-bezel-inner">
                <span class="tplink-stat-label">Needs review</span>
                <span class="tplink-stat-value tplink-stat-value--amber"><?= (int)($stats['needs_review'] ?? 0) ?></span>
            </div>
        </div>
    </article>

    <article class="tplink-card">
        <div class="tplink-bezel">
            <div class="tplink-bezel-inner">
                <span class="tplink-stat-label">Missing at source</span>
                <span class="tplink-stat-value tplink-stat-value--rose"><?= (int)($stats['missing_at_source'] ?? 0) ?></span>
            </div>
        </div>
    </article>

    <article class="tplink-card tplink-card--wide">
        <div class="tplink-bezel">
            <div class="tplink-bezel-inner tplink-run-block">
                <span class="tplink-stat-label">Запуск из админки</span>
                <form class="tplink-run-form" method="post" id="tplink-run-form" data-import-form>
                    <?= bitrix_sessid_post() ?>
                    <input type="hidden" name="run_import" value="Y">
                    <label class="tplink-check">
                        <input type="checkbox" name="dry_run" value="Y">
                        <span>Dry-run (без записи в IB)</span>
                    </label>
                    <div class="tplink-run-actions">
                        <button type="submit" class="tplink-btn tplink-btn--primary group" <?= $importLocked ? 'disabled' : '' ?>>
                            <span>Запустить импорт</span>
                            <span class="tplink-btn-icon" aria-hidden="true">
                                <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5"><path d="M5 12h14M13 6l6 6-6 6"/></svg>
                            </span>
                        </button>
                    </div>
                    <p class="tplink-hint">Полный прогон может занять 10–30 минут. Не закрывайте вкладку до завершения.</p>
                    <?php if ($importLocked): ?>
                        <p class="tplink-hint tplink-hint--warn">Импорт уже выполняется…</p>
                    <?php endif; ?>
                </form>
            </div>
        </div>
    </article>

    <article class="tplink-card tplink-card--wide">
        <div class="tplink-bezel">
            <div class="tplink-bezel-inner tplink-cli-block">
                <span class="tplink-stat-label">CLI (альтернатива)</span>
                <div class="tplink-cli-row">
                    <code class="tplink-cli" id="tplink-cli-cmd"><?= $cli ?></code>
                    <button type="button" class="tplink-btn group" data-copy="#tplink-cli-cmd">
                        <span>Скопировать</span>
                        <span class="tplink-btn-icon" aria-hidden="true">
                            <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5"><rect x="9" y="9" width="13" height="13" rx="2"/><path d="M5 15H4a2 2 0 0 1-2-2V4a2 2 0 0 1 2-2h9a2 2 0 0 1 2 2v1"/></svg>
                        </span>
                    </button>
                </div>
                <p class="tplink-hint">Dry-run: добавьте <code>-- --dry-run</code>. CSV: <code>-- --csv=run1.csv</code></p>
            </div>
        </div>
    </article>

    <article class="tplink-card tplink-card--tall">
        <div class="tplink-bezel">
            <div class="tplink-bezel-inner">
                <span class="tplink-stat-label">Последний прогон</span>
                <?php if ($lastRun === null): ?>
                    <p class="tplink-empty">Прогонов ещё не было. Нажмите «Запустить импорт».</p>
                <?php else: ?>
                    <dl class="tplink-kv">
                        <div><dt>Файл</dt><dd><code><?= htmlspecialcharsbx((string)($lastRun['file'] ?? '')) ?></code></dd></div>
                        <div><dt>Старт</dt><dd><?= htmlspecialcharsbx((string)($lastRun['started_at'] ?? '—')) ?></dd></div>
                        <div><dt>Финиш</dt><dd><?= htmlspecialcharsbx((string)($lastRun['finished_at'] ?? '—')) ?></dd></div>
                    </dl>
                    <?php $c = $lastRun['counters'] ?? []; ?>
                    <div class="tplink-pills">
                        <span class="tplink-pill">new <?= (int)($c['new'] ?? 0) ?></span>
                        <span class="tplink-pill">upd <?= (int)($c['updated'] ?? 0) ?></span>
                        <span class="tplink-pill">ok <?= (int)($c['unchanged'] ?? 0) ?></span>
                        <span class="tplink-pill tplink-pill--warn">err <?= (int)($c['errors'] ?? 0) ?></span>
                    </div>
                    <a class="tplink-btn tplink-btn--ghost group" href="<?= htmlspecialcharsbx($adminBase) ?>/logs.php?tab=runs&run=<?= urlencode((string)($lastRun['file'] ?? '')) ?>">
                        <span>Открыть лог</span>
                        <span class="tplink-btn-icon" aria-hidden="true">
                            <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5"><path d="M7 17L17 7M17 7H9M17 7v8"/></svg>
                        </span>
                    </a>
                <?php endif; ?>
            </div>
        </div>
    </article>

    <article class="tplink-card">
        <div class="tplink-bezel">
            <div class="tplink-bezel-inner">
                <span class="tplink-stat-label">Источник</span>
                <a class="tplink-link" href="<?= $sourceUrl ?>" target="_blank" rel="noopener"><?= $sourceUrl ?></a>
                <p class="tplink-hint">Sitemap: <?= ($data['use_sitemap'] ?? 'Y') === 'Y' ? 'включён' : 'выкл' ?></p>
                <p class="tplink-hint">IB ID: <?= (int)($stats['iblock_id'] ?? 0) ?></p>
            </div>
        </div>
    </article>
</section>

<?php if ($recentRuns !== []): ?>
<section class="tplink-section tplink-reveal" style="--delay:160ms">
    <h2 class="tplink-h2">Недавние прогоны</h2>
    <div class="tplink-bezel tplink-table-bezel">
        <div class="tplink-bezel-inner tplink-table-wrap">
            <table class="tplink-table">
                <thead>
                    <tr>
                        <th>Файл</th>
                        <th>Старт</th>
                        <th>new</th>
                        <th>updated</th>
                        <th>errors</th>
                        <th></th>
                    </tr>
                </thead>
                <tbody>
                <?php foreach ($recentRuns as $run): ?>
                    <?php $c = $run['counters'] ?? []; ?>
                    <tr>
                        <td><code><?= htmlspecialcharsbx((string)($run['file'] ?? '')) ?></code></td>
                        <td><?= htmlspecialcharsbx((string)($run['started_at'] ?? '')) ?></td>
                        <td><?= (int)($c['new'] ?? 0) ?></td>
                        <td><?= (int)($c['updated'] ?? 0) ?></td>
                        <td><?= (int)($c['errors'] ?? 0) ?></td>
                        <td><a class="tplink-text-link" href="<?= htmlspecialcharsbx($adminBase) ?>/logs.php?tab=runs&run=<?= urlencode((string)($run['file'] ?? '')) ?>">детали</a></td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
</section>
<?php endif; ?>

<?php include __DIR__ . '/_layout_end.php'; ?>

<div class="tplink-overlay" id="tplink-import-overlay" hidden aria-live="polite">
    <div class="tplink-overlay-card">
        <div class="tplink-spinner" aria-hidden="true"></div>
        <p class="tplink-overlay-title">Импорт выполняется</p>
        <p class="tplink-overlay-text">Синхронизация с tp-link.com… Это может занять несколько минут.</p>
    </div>
</div>
