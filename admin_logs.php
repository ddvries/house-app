<?php

declare(strict_types=1);

require __DIR__ . '/bootstrap.php';

use App\Core\Auth;
use App\Core\SecurityLogParser;
use function App\Core\e;
use function App\Core\t;

Auth::requireAuth();
if (!Auth::isAdmin()) {
    http_response_code(403);
    exit(t('error.access_denied'));
}

// Get filters from query/form
$filterEvent = trim((string) ($_GET['event'] ?? ''));
$filterIp = trim((string) ($_GET['ip'] ?? ''));
$filterDate = trim((string) ($_GET['date'] ?? ''));

// Parse and filter logs
$allEntries = SecurityLogParser::all();
$filteredEntries = $allEntries;

if ($filterEvent !== '') {
    $filteredEntries = SecurityLogParser::byEvent($filterEvent);
}

if ($filterIp !== '') {
    $filtered2 = [];
    foreach ($filteredEntries as $entry) {
        if ($entry['ip'] === $filterIp) {
            $filtered2[] = $entry;
        }
    }
    $filteredEntries = $filtered2;
}

if ($filterDate !== '') {
    $filtered3 = [];
    foreach ($filteredEntries as $entry) {
        if (str_starts_with($entry['timestamp'], $filterDate)) {
            $filtered3[] = $entry;
        }
    }
    $filteredEntries = $filtered3;
}

// Get statistics
$uniqueIps = SecurityLogParser::uniqueIps();
$eventCounts = SecurityLogParser::eventCounts();
$totalEvents = count($allEntries);

$title = 'Security Logs';
$active = 'admin-logs';
require __DIR__ . '/partials/layout_start.php';
?>

<section class="page-title">
  <div>
    <h2><?= e(t('admin_logs.heading')) ?></h2>
    <p><?= e(t('admin_logs.subtitle')) ?></p>
  </div>
</section>

<section class="card" style="margin-bottom: 1.5rem;">
  <h3><?= e(t('admin_logs.statistics')) ?></h3>
  <div class="grid cols-4" style="gap: 1rem;">
    <div style="padding: 1rem; background: #f5f5f5; border-radius: 4px;">
      <div style="font-size: 0.9rem; color: #666; margin-bottom: 0.5rem;"><?= e(t('admin_logs.total_events')) ?></div>
      <div style="font-size: 1.8rem; font-weight: bold;"><?= e((string) $totalEvents) ?></div>
    </div>
    <div style="padding: 1rem; background: #f5f5f5; border-radius: 4px;">
      <div style="font-size: 0.9rem; color: #666; margin-bottom: 0.5rem;"><?= e(t('admin_logs.unique_ips')) ?></div>
      <div style="font-size: 1.8rem; font-weight: bold;"><?= e((string) count($uniqueIps)) ?></div>
    </div>
    <div style="padding: 1rem; background: #f5f5f5; border-radius: 4px;">
      <div style="font-size: 0.9rem; color: #666; margin-bottom: 0.5rem;"><?= e(t('admin_logs.event_types')) ?></div>
      <div style="font-size: 1.8rem; font-weight: bold;"><?= e((string) count($eventCounts)) ?></div>
    </div>
    <div style="padding: 1rem; background: #f5f5f5; border-radius: 4px;">
      <div style="font-size: 0.9rem; color: #666; margin-bottom: 0.5rem;"><?= e(t('admin_logs.filtered_results')) ?></div>
      <div style="font-size: 1.8rem; font-weight: bold;"><?= e((string) count($filteredEntries)) ?></div>
    </div>
  </div>
</section>

<section class="card" style="margin-bottom: 1.5rem;">
  <h3><?= e(t('admin_logs.event_distribution')) ?></h3>
  <table>
    <thead>
      <tr>
        <th><?= e(t('admin_logs.event_type')) ?></th>
        <th style="text-align: right;"><?= e(t('admin_logs.count')) ?></th>
      </tr>
    </thead>
    <tbody>
      <?php if ($eventCounts !== []): ?>
        <?php foreach ($eventCounts as $event => $count): ?>
          <tr>
            <td>
              <a href="?event=<?= urlencode($event) ?>" style="color: #0066cc; text-decoration: none;">
                <?= e($event) ?>
              </a>
            </td>
            <td style="text-align: right;"><?= e((string) $count) ?></td>
          </tr>
        <?php endforeach; ?>
      <?php else: ?>
        <tr>
          <td colspan="2" style="text-align: center; color: #999;"><?= e(t('admin_logs.no_events')) ?></td>
        </tr>
      <?php endif; ?>
    </tbody>
  </table>
</section>

<section class="card">
  <h3><?= e(t('admin_logs.filter_logs')) ?></h3>
  <form method="get" style="display: grid; grid-template-columns: 1fr 1fr 1fr auto; gap: 1rem; margin-bottom: 1.5rem; align-items: flex-end;">
    <div>
      <label for="event" style="display: block; margin-bottom: 0.5rem; font-size: 0.9rem;"><?= e(t('admin_logs.event_type')) ?></label>
      <input 
        type="text" 
        id="event" 
        name="event" 
        placeholder="e.g., Login, CSRF, Path traversal" 
        value="<?= e($filterEvent) ?>"
        style="width: 100%; padding: 0.5rem; border: 1px solid #ddd; border-radius: 4px; font-size: 0.9rem;"
      />
    </div>
    <div>
      <label for="ip" style="display: block; margin-bottom: 0.5rem; font-size: 0.9rem;"><?= e(t('admin_logs.ip_address')) ?></label>
      <select id="ip" name="ip" style="width: 100%; padding: 0.5rem; border: 1px solid #ddd; border-radius: 4px; font-size: 0.9rem;">
        <option value=""><?= e(t('admin_logs.all_ips')) ?></option>
        <?php foreach (array_slice($uniqueIps, 0, 50) as $ip): ?>
          <option value="<?= e($ip) ?>" <?= $filterIp === $ip ? 'selected' : '' ?>>
            <?= e($ip) ?>
          </option>
        <?php endforeach; ?>
      </select>
    </div>
    <div>
      <label for="date" style="display: block; margin-bottom: 0.5rem; font-size: 0.9rem;"><?= e(t('admin_logs.date')) ?></label>
      <input 
        type="date" 
        id="date" 
        name="date" 
        value="<?= e($filterDate) ?>"
        style="width: 100%; padding: 0.5rem; border: 1px solid #ddd; border-radius: 4px; font-size: 0.9rem;"
      />
    </div>
    <div>
      <button type="submit" class="btn primary" style="width: auto;"><?= e(t('admin_logs.filter')) ?></button>
      <?php if ($filterEvent !== '' || $filterIp !== '' || $filterDate !== ''): ?>
        <a href="admin_logs.php" class="btn ghost" style="width: auto; margin-left: 0.5rem;"><?= e(t('admin_logs.clear')) ?></a>
      <?php endif; ?>
    </div>
  </form>

  <table>
    <thead>
      <tr>
        <th><?= e(t('admin_logs.timestamp')) ?></th>
        <th><?= e(t('admin_logs.ip')) ?></th>
        <th><?= e(t('admin_logs.event')) ?></th>
        <th><?= e(t('admin_logs.details')) ?></th>
      </tr>
    </thead>
    <tbody>
      <?php if ($filteredEntries !== []): ?>
        <?php foreach ($filteredEntries as $entry): ?>
          <tr>
            <td style="font-size: 0.9rem; white-space: nowrap;"><?= e($entry['timestamp']) ?></td>
            <td>
              <a href="?ip=<?= urlencode($entry['ip']) ?>" style="color: #0066cc; text-decoration: none; font-family: monospace;">
                <?= e($entry['ip']) ?>
              </a>
            </td>
            <td style="font-weight: 500;"><?= e($entry['event']) ?></td>
            <td>
              <?php if ($entry['context'] !== []): ?>
                <details style="cursor: pointer;">
                  <summary style="color: #0066cc;">View details</summary>
                  <pre style="background: #f5f5f5; padding: 0.5rem; border-radius: 4px; margin-top: 0.5rem; font-size: 0.85rem; overflow-x: auto;">
<?php foreach ($entry['context'] as $key => $value): ?>
<?= e((string) $key) ?>: <?= is_array($value) ? json_encode($value) : e((string) $value) . PHP_EOL ?>
<?php endforeach; ?>
                  </pre>
                </details>
              <?php else: ?>
                <span style="color: #999;">—</span>
              <?php endif; ?>
            </td>
          </tr>
        <?php endforeach; ?>
      <?php else: ?>
        <tr>
          <td colspan="4" style="text-align: center; color: #999; padding: 2rem;">
            <?= e(t('admin_logs.no_matching')) ?>
          </td>
        </tr>
      <?php endif; ?>
    </tbody>
  </table>
</section>

<?php require __DIR__ . '/partials/layout_end.php';
