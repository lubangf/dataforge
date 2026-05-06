<?php

declare(strict_types=1);

session_start();

if (empty($_SESSION['ops_auth'])) {
  header('Location: /dataforge/operations/login');
    exit;
}

require dirname(__DIR__) . '/backend/services/leads.php';

$allowedStatuses = ['New', 'Qualified', 'Demo Scheduled', 'Proposal Sent', 'Won', 'Lost'];
$allowedPriorities = ['Low', 'Normal', 'High'];

$message = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
  $leadId = trim((string)($_POST['leadId'] ?? ''));
  if ($leadId !== '') {
    $status = trim((string)($_POST['status'] ?? 'New'));
    $priority = trim((string)($_POST['priority'] ?? 'Normal'));

    if (!in_array($status, $allowedStatuses, true)) {
      $status = 'New';
    }
    if (!in_array($priority, $allowedPriorities, true)) {
      $priority = 'Normal';
    }

    dataforge_leads_update_admin($leadId, [
      'status' => $status,
      'owner' => trim((string)($_POST['owner'] ?? '')),
      'priority' => $priority,
      'lastContactDate' => trim((string)($_POST['lastContactDate'] ?? '')),
      'nextStep' => trim((string)($_POST['nextStep'] ?? '')),
      'updatedAtUtc' => gmdate('c'),
    ]);
    $message = 'Lead updated.';
  }
}

$allLeads = dataforge_leads_load_all();
$totals = dataforge_leads_status_totals($allLeads);

$search = trim((string)($_GET['search'] ?? ''));
$statusFilter = trim((string)($_GET['status'] ?? 'All'));

$leads = array_values(array_filter($allLeads, static function (array $lead) use ($search, $statusFilter): bool {
  if ($statusFilter !== 'All' && (($lead['status'] ?? 'New') !== $statusFilter)) {
    return false;
  }

  if ($search === '') {
    return true;
  }

  $haystack = strtolower(implode(' ', [
    (string)($lead['name'] ?? ''),
    (string)($lead['email'] ?? ''),
    (string)($lead['primaryNeed'] ?? ''),
    (string)($lead['context'] ?? ''),
    (string)($lead['owner'] ?? ''),
  ]));

  return strpos($haystack, strtolower($search)) !== false;
}));

if (isset($_GET['export']) && $_GET['export'] === 'csv') {
  header('Content-Type: text/csv; charset=utf-8');
  header('Content-Disposition: attachment; filename="dataforge-leads-' . gmdate('Ymd-His') . '.csv"');

  $out = fopen('php://output', 'w');
  if ($out !== false) {
    fputcsv($out, [
      'Lead ID',
      'Created At (UTC)',
      'Name',
      'Email',
      'Primary Need',
      'Status',
      'Priority',
      'Owner',
      'Last Contact Date',
      'Next Step',
      'Context',
    ]);

    foreach ($leads as $lead) {
      fputcsv($out, [
        (string)($lead['leadId'] ?? ''),
        (string)($lead['createdAtUtc'] ?? ''),
        (string)($lead['name'] ?? ''),
        (string)($lead['email'] ?? ''),
        (string)($lead['primaryNeed'] ?? ''),
        (string)($lead['status'] ?? ''),
        (string)($lead['priority'] ?? ''),
        (string)($lead['owner'] ?? ''),
        (string)($lead['lastContactDate'] ?? ''),
        (string)($lead['nextStep'] ?? ''),
        (string)($lead['context'] ?? ''),
      ]);
    }

    fclose($out);
  }

  exit;
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0" />
  <title>Operations | Data Forge Leads</title>
  <link rel="icon" type="image/png" href="/dataforge/assets/img/dataforge-mark-orange.png" />
  <link rel="stylesheet" href="/dataforge/assets/css/styles.css?v=20260506-3" />
</head>
<body>
  <main class="container ops-main">
    <section>
      <div class="ops-top">
        <div>
          <p class="kicker">Operations Dashboard</p>
          <h1>Sales and Lead Management</h1>
          <p>Review inquiries, qualify opportunities, and track next actions.</p>
        </div>
        <a class="btn btn-ghost" href="/dataforge/operations/logout">Sign Out</a>
      </div>

      <?php if ($message !== ''): ?>
        <p class="ops-success"><?= htmlspecialchars($message, ENT_QUOTES, 'UTF-8') ?></p>
      <?php endif; ?>

      <div class="ops-grid">
        <?php foreach ($totals as $label => $value): ?>
          <article class="ops-kpi">
            <p><?= htmlspecialchars($label, ENT_QUOTES, 'UTF-8') ?></p>
            <strong><?= (int)$value ?></strong>
          </article>
        <?php endforeach; ?>
      </div>

      <form method="get" class="ops-filter">
        <label>
          Search
          <input type="text" name="search" value="<?= htmlspecialchars($search, ENT_QUOTES, 'UTF-8') ?>" placeholder="Name, email, owner, context">
        </label>
        <label>
          Status
          <select name="status">
            <?php foreach (array_keys($totals) as $statusOption): ?>
              <option value="<?= htmlspecialchars($statusOption, ENT_QUOTES, 'UTF-8') ?>" <?= $statusOption === $statusFilter ? 'selected' : '' ?>><?= htmlspecialchars($statusOption, ENT_QUOTES, 'UTF-8') ?></option>
            <?php endforeach; ?>
          </select>
        </label>
        <button class="btn" type="submit">Apply</button>
        <button class="btn btn-ghost" type="submit" name="export" value="csv">Export CSV</button>
      </form>

      <div class="ops-table-wrap card">
        <table class="ops-table" aria-label="Leads table">
          <thead>
            <tr>
              <th>Lead</th>
              <th>Need</th>
              <th>Submitted</th>
              <th>Context</th>
              <th>Owner</th>
              <th>Status</th>
              <th>Next Step</th>
              <th>Action</th>
            </tr>
          </thead>
          <tbody>
            <?php if (count($leads) === 0): ?>
              <tr><td colspan="8">No leads found for the current filter.</td></tr>
            <?php endif; ?>
            <?php foreach ($leads as $lead): ?>
              <tr>
                <td>
                  <strong><?= htmlspecialchars((string)($lead['name'] ?? ''), ENT_QUOTES, 'UTF-8') ?></strong><br>
                  <a href="mailto:<?= htmlspecialchars((string)($lead['email'] ?? ''), ENT_QUOTES, 'UTF-8') ?>"><?= htmlspecialchars((string)($lead['email'] ?? ''), ENT_QUOTES, 'UTF-8') ?></a>
                </td>
                <td><?= htmlspecialchars((string)($lead['primaryNeed'] ?? ''), ENT_QUOTES, 'UTF-8') ?></td>
                <td><?= htmlspecialchars(substr((string)($lead['createdAtUtc'] ?? ''), 0, 10), ENT_QUOTES, 'UTF-8') ?></td>
                <td><?= nl2br(htmlspecialchars((string)($lead['context'] ?? ''), ENT_QUOTES, 'UTF-8')) ?></td>
                <td colspan="4">
                  <form method="post" class="ops-inline">
                    <input type="hidden" name="leadId" value="<?= htmlspecialchars((string)$lead['leadId'], ENT_QUOTES, 'UTF-8') ?>">
                    <div class="ops-row-grid">
                      <label>
                        Owner
                        <input type="text" name="owner" value="<?= htmlspecialchars((string)($lead['owner'] ?? ''), ENT_QUOTES, 'UTF-8') ?>" placeholder="Rep name">
                      </label>
                      <label>
                        Status
                        <select name="status">
                          <?php foreach ($allowedStatuses as $status): ?>
                            <option value="<?= htmlspecialchars($status, ENT_QUOTES, 'UTF-8') ?>" <?= (($lead['status'] ?? 'New') === $status) ? 'selected' : '' ?>><?= htmlspecialchars($status, ENT_QUOTES, 'UTF-8') ?></option>
                          <?php endforeach; ?>
                        </select>
                      </label>
                      <label>
                        Priority
                        <select name="priority">
                          <?php foreach ($allowedPriorities as $priority): ?>
                            <option value="<?= htmlspecialchars($priority, ENT_QUOTES, 'UTF-8') ?>" <?= (($lead['priority'] ?? 'Normal') === $priority) ? 'selected' : '' ?>><?= htmlspecialchars($priority, ENT_QUOTES, 'UTF-8') ?></option>
                          <?php endforeach; ?>
                        </select>
                      </label>
                    </div>
                    <div class="ops-row-actions">
                      <label>
                        Last Contact
                        <input type="date" name="lastContactDate" value="<?= htmlspecialchars((string)($lead['lastContactDate'] ?? ''), ENT_QUOTES, 'UTF-8') ?>">
                      </label>
                      <label>
                        Next Step
                        <input type="text" name="nextStep" value="<?= htmlspecialchars((string)($lead['nextStep'] ?? ''), ENT_QUOTES, 'UTF-8') ?>" placeholder="Schedule intro / follow up / proposal">
                      </label>
                      <button class="btn" type="submit">Save</button>
                    </div>
                  </form>
                </td>
              </tr>
            <?php endforeach; ?>
          </tbody>
        </table>
      </div>
    </section>
  </main>
</body>
</html>
