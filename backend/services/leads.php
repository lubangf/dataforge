<?php

declare(strict_types=1);

function dataforge_data_dir(): string
{
    return dirname(__DIR__, 2) . '/data';
}

function dataforge_leads_db_file(): string
{
    return dataforge_data_dir() . '/leads.sqlite';
}

function dataforge_leads_db(): PDO
{
    static $pdo = null;

    if ($pdo instanceof PDO) {
        return $pdo;
    }

    if (!extension_loaded('pdo_sqlite')) {
        throw new RuntimeException('SQLite support is not available in this PHP runtime.');
    }

    $dir = dataforge_data_dir();
    if (!is_dir($dir) && !mkdir($dir, 0775, true) && !is_dir($dir)) {
        throw new RuntimeException('Unable to create data directory for Data Forge leads.');
    }

    $pdo = new PDO('sqlite:' . dataforge_leads_db_file());
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    $pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);

    $pdo->exec(
        'CREATE TABLE IF NOT EXISTS leads (
            lead_id TEXT PRIMARY KEY,
            created_at_utc TEXT NOT NULL,
            name TEXT NOT NULL,
            email TEXT NOT NULL,
            primary_need TEXT NOT NULL DEFAULT "",
            context TEXT NOT NULL DEFAULT "",
            ip TEXT NOT NULL DEFAULT "",
            user_agent TEXT NOT NULL DEFAULT "",
            status TEXT NOT NULL DEFAULT "New",
            owner TEXT NOT NULL DEFAULT "",
            priority TEXT NOT NULL DEFAULT "Normal",
            last_contact_date TEXT NOT NULL DEFAULT "",
            next_step TEXT NOT NULL DEFAULT "",
            updated_at_utc TEXT NOT NULL DEFAULT ""
        )'
    );

    dataforge_ensure_lead_admin_columns($pdo);

    $pdo->exec('CREATE INDEX IF NOT EXISTS idx_df_leads_created_at ON leads(created_at_utc DESC)');
    $pdo->exec('CREATE INDEX IF NOT EXISTS idx_df_leads_status ON leads(status)');

    return $pdo;
}

function dataforge_ensure_lead_admin_columns(PDO $pdo): void
{
    $colsStmt = $pdo->query('PRAGMA table_info(leads)');
    $columns = [];
    if ($colsStmt !== false) {
        $rows = $colsStmt->fetchAll();
        if (is_array($rows)) {
            foreach ($rows as $row) {
                $name = isset($row['name']) ? (string)$row['name'] : '';
                if ($name !== '') {
                    $columns[$name] = true;
                }
            }
        }
    }

    $required = [
        'status' => 'TEXT NOT NULL DEFAULT "New"',
        'owner' => 'TEXT NOT NULL DEFAULT ""',
        'priority' => 'TEXT NOT NULL DEFAULT "Normal"',
        'last_contact_date' => 'TEXT NOT NULL DEFAULT ""',
        'next_step' => 'TEXT NOT NULL DEFAULT ""',
        'updated_at_utc' => 'TEXT NOT NULL DEFAULT ""',
    ];

    foreach ($required as $name => $definition) {
        if (!isset($columns[$name])) {
            $pdo->exec('ALTER TABLE leads ADD COLUMN ' . $name . ' ' . $definition);
        }
    }
}

function dataforge_leads_append(array $lead): void
{
    $stmt = dataforge_leads_db()->prepare(
        'INSERT INTO leads (
            lead_id,
            created_at_utc,
            name,
            email,
            primary_need,
            context,
            ip,
            user_agent,
            status,
            owner,
            priority,
            last_contact_date,
            next_step,
            updated_at_utc
        ) VALUES (
            :lead_id,
            :created_at_utc,
            :name,
            :email,
            :primary_need,
            :context,
            :ip,
            :user_agent,
            :status,
            :owner,
            :priority,
            :last_contact_date,
            :next_step,
            :updated_at_utc
        )'
    );

    $stmt->execute([
        ':lead_id' => (string)($lead['leadId'] ?? ''),
        ':created_at_utc' => (string)($lead['createdAtUtc'] ?? gmdate('c')),
        ':name' => (string)($lead['name'] ?? ''),
        ':email' => (string)($lead['email'] ?? ''),
        ':primary_need' => (string)($lead['primaryNeed'] ?? ''),
        ':context' => (string)($lead['context'] ?? ''),
        ':ip' => (string)($lead['ip'] ?? ''),
        ':user_agent' => (string)($lead['userAgent'] ?? ''),
        ':status' => 'New',
        ':owner' => '',
        ':priority' => 'Normal',
        ':last_contact_date' => '',
        ':next_step' => '',
        ':updated_at_utc' => '',
    ]);
}

function dataforge_leads_load_all(): array
{
    $stmt = dataforge_leads_db()->query(
        'SELECT
            lead_id AS leadId,
            created_at_utc AS createdAtUtc,
            name,
            email,
            primary_need AS primaryNeed,
            context,
            ip,
            user_agent AS userAgent,
            status,
            owner,
            priority,
            last_contact_date AS lastContactDate,
            next_step AS nextStep,
            updated_at_utc AS updatedAtUtc
        FROM leads
        ORDER BY created_at_utc DESC'
    );

    $rows = $stmt->fetchAll();
    return is_array($rows) ? $rows : [];
}

function dataforge_leads_update_admin(string $leadId, array $data): void
{
    $status = (string)($data['status'] ?? 'New');
    $owner = (string)($data['owner'] ?? '');
    $priority = (string)($data['priority'] ?? 'Normal');
    $lastContactDate = (string)($data['lastContactDate'] ?? '');
    $nextStep = (string)($data['nextStep'] ?? '');
    $updatedAtUtc = (string)($data['updatedAtUtc'] ?? gmdate('c'));

    $stmt = dataforge_leads_db()->prepare(
        'UPDATE leads
        SET status = :status,
            owner = :owner,
            priority = :priority,
            last_contact_date = :last_contact_date,
            next_step = :next_step,
            updated_at_utc = :updated_at_utc
        WHERE lead_id = :lead_id'
    );

    $stmt->execute([
        ':status' => $status,
        ':owner' => $owner,
        ':priority' => $priority,
        ':last_contact_date' => $lastContactDate,
        ':next_step' => $nextStep,
        ':updated_at_utc' => $updatedAtUtc,
        ':lead_id' => $leadId,
    ]);
}

function dataforge_leads_status_totals(array $leads): array
{
    $totals = [
        'All' => count($leads),
        'New' => 0,
        'Qualified' => 0,
        'Demo Scheduled' => 0,
        'Proposal Sent' => 0,
        'Won' => 0,
        'Lost' => 0,
    ];

    foreach ($leads as $lead) {
        $status = (string)($lead['status'] ?? 'New');
        if (!array_key_exists($status, $totals)) {
            $totals[$status] = 0;
        }
        $totals[$status]++;
    }

    return $totals;
}
