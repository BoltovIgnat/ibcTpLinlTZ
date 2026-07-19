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
/** @var array<string, mixed>|null $cleanupResult */
/** @var string|null $cleanupError */
$importLocked = (bool)($data['import_locked'] ?? false);
$clearCli = htmlspecialcharsbx((string)($data['clear_cli_command'] ?? ''));

include __DIR__ . '/_layout_start.php';
?>

<?php if ($cleanupError !== null): ?>
<div class="tplink-alert tplink-alert--error tplink-reveal is-visible">
    <strong>Ошибка очистки</strong>
    <p><?= htmlspecialcharsbx($cleanupError) ?></p>
</div>
<?php endif; ?>

<?php if ($cleanupResult !== null): ?>
<div class="tplink-alert tplink-alert--success tplink-reveal is-visible">
    <strong>Каталог очищен</strong>
    <p>Удалено элементов: <?= (int)($cleanupResult['deleted'] ?? 0) ?> (IB <?= (int)($cleanupResult['iblock_id'] ?? 0) ?>, <code><?= htmlspecialcharsbx((string)($cleanupResult['iblock_code'] ?? '')) ?></code>)</p>
</div>
<?php endif; ?>

<section class="tplink-hero tplink-reveal">
    <span class="tplink-eyebrow">Catalog Sync Console</span>
    <h1 class="tplink-h1">Импорт TP-Link</h1>
    <p class="tplink-lead">CLI-синхронизация каталога Wi‑Fi роутеров с tp-link.com в инфоблок <code><?= $iblockCode ?></code>. Импорт только через CLI (ТЗ A.2).</p>
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
            <div class="tplink-bezel-inner tplink-cli-block">
                <span class="tplink-stat-label">Запуск импорта (только CLI)</span>
                <div class="tplink-cli-row">
                    <code class="tplink-cli" id="tplink-cli-cmd"><?= $cli ?></code>
                    <button type="button" class="tplink-btn group" data-copy="#tplink-cli-cmd">
                        <span>Скопировать</span>
                        <span class="tplink-btn-icon" aria-hidden="true">
                            <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5"><rect x="9" y="9" width="13" height="13" rx="2"/><path d="M5 15H4a2 2 0 0 1-2-2V4a2 2 0 0 1 2-2h9a2 2 0 0 1 2 2v1"/></svg>
                        </span>
                    </button>
                </div>
                <p class="tplink-hint">Веб-запуск запрещён (ТЗ A.2). Dry-run: <code>-- --dry-run</code>. CSV: <code>-- --csv=run1.csv</code></p>
                <?php if ($importLocked): ?>
                    <p class="tplink-hint tplink-hint--warn">Импорт уже выполняется…</p>
                <?php endif; ?>
            </div>
        </div>
    </article>

    <article class="tplink-card tplink-card--tall">
        <div class="tplink-bezel">
            <div class="tplink-bezel-inner">
                <span class="tplink-stat-label">Последний прогон</span>
                <?php if ($lastRun === null): ?>
                    <p class="tplink-empty">Прогонов ещё не было. Запустите импорт через CLI.</p>
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

<section class="tplink-section tplink-reveal tplink-danger-zone" style="--delay:120ms">
    <h2 class="tplink-h2">Опасная зона</h2>
    <div class="tplink-bezel tplink-danger-bezel">
        <div class="tplink-bezel-inner tplink-run-block">
            <span class="tplink-stat-label">Очистка каталога</span>
            <p class="tplink-hint">Удаляет все элементы инфоблока <code><?= $iblockCode ?></code> и сбрасывает блокировку импорта. Логи прогонов не удаляются.</p>
            <form class="tplink-run-form" method="post" data-clear-form>
                <?= bitrix_sessid_post() ?>
                <input type="hidden" name="clear_catalog" value="Y">
                <label class="tplink-field">
                    <span class="tplink-field-label">Подтверждение: введите CLEAR</span>
                    <input type="text" name="clear_confirm" class="tplink-input" autocomplete="off" spellcheck="false" placeholder="CLEAR" required>
                </label>
                <div class="tplink-run-actions">
                    <button type="submit" class="tplink-btn tplink-btn--danger group" <?= $importLocked ? 'disabled' : '' ?>>
                        <span>Очистить каталог</span>
                        <span class="tplink-btn-icon" aria-hidden="true">
                            <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5"><path d="M3 6h18M8 6V4a2 2 0 0 1 2-2h4a2 2 0 0 1 2 2v2M19 6l-1 14a2 2 0 0 1-2 2H8a2 2 0 0 1-2-2L5 6"/><path d="M10 11v6M14 11v6"/></svg>
                        </span>
                    </button>
                </div>
                <?php if ($importLocked): ?>
                    <p class="tplink-hint tplink-hint--warn">Импорт выполняется — очистка недоступна.</p>
                <?php endif; ?>
            </form>
            <div class="tplink-cli-row" style="margin-top:1rem">
                <code class="tplink-cli" id="tplink-clear-cli"><?= $clearCli ?></code>
                <button type="button" class="tplink-btn group" data-copy="#tplink-clear-cli">
                    <span>Скопировать</span>
                    <span class="tplink-btn-icon" aria-hidden="true">
                        <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5"><rect x="9" y="9" width="13" height="13" rx="2"/><path d="M5 15H4a2 2 0 0 1-2-2V4a2 2 0 0 1 2-2h9a2 2 0 0 1 2 2v1"/></svg>
                    </span>
                </button>
            </div>
            <p class="tplink-hint">Preview: <code>--dry-run</code> без <code>--yes</code></p>
        </div>
    </div>
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
