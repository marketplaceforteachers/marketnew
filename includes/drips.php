<?php
require_once __DIR__ . '/resend.php';

const DRIP_TRIGGERS = [
    'teacher_registered' => 'A teacher creates an account',
    'buyer_registered' => 'A buyer creates an account',
    'listing_posted' => 'A teacher posts a new listing',
    'order_paid' => 'An order is paid',
];

/** Enrolls a user in every enabled drip campaign matching this trigger event. Safe to call repeatedly — a user can be enrolled more than once (e.g. posting two listings), which is intentional for per-event drips. */
function enroll_in_drips(string $triggerEvent, int $userId): void
{
    $stmt = db()->prepare('SELECT id FROM email_drips WHERE trigger_event = ? AND is_enabled = 1');
    $stmt->execute([$triggerEvent]);
    $insert = db()->prepare('INSERT INTO email_drip_enrollments (drip_id, user_id) VALUES (?, ?)');
    foreach ($stmt->fetchAll() as $drip) {
        $insert->execute([$drip['id'], $userId]);
    }
}

function get_all_drips(): array
{
    return db()->query(
        "SELECT d.*, (SELECT COUNT(*) FROM email_drip_steps s WHERE s.drip_id = d.id) AS step_count,
                (SELECT COUNT(*) FROM email_drip_enrollments e WHERE e.drip_id = d.id AND e.status = 'active') AS active_count,
                (SELECT COUNT(*) FROM email_drip_enrollments e WHERE e.drip_id = d.id) AS total_enrolled
         FROM email_drips d ORDER BY d.created_at DESC"
    )->fetchAll();
}

function get_drip(int $id): ?array
{
    $stmt = db()->prepare('SELECT * FROM email_drips WHERE id = ?');
    $stmt->execute([$id]);
    $row = $stmt->fetch();
    return $row ?: null;
}

function get_drip_steps(int $dripId): array
{
    $stmt = db()->prepare('SELECT * FROM email_drip_steps WHERE drip_id = ? ORDER BY step_order ASC, delay_hours ASC');
    $stmt->execute([$dripId]);
    return $stmt->fetchAll();
}

/** Sends every step whose delay has elapsed for every active enrollment, skipping steps already sent. Returns how many emails went out. Call this from the cron endpoint. */
function process_due_drip_steps(): int
{
    $totalSent = 0;
    $enrollments = db()->query("SELECT * FROM email_drip_enrollments WHERE status = 'active'")->fetchAll();
    $siteName = get_setting('branding')['siteName'];

    foreach ($enrollments as $enrollment) {
        $steps = get_drip_steps((int) $enrollment['drip_id']);
        if (!$steps) {
            continue;
        }

        $sentStmt = db()->prepare('SELECT step_id FROM email_drip_sends WHERE enrollment_id = ?');
        $sentStmt->execute([$enrollment['id']]);
        $sentStepIds = array_column($sentStmt->fetchAll(), 'step_id');

        foreach ($steps as $step) {
            if (in_array($step['id'], $sentStepIds, true)) {
                continue;
            }
            $dueAt = strtotime($enrollment['enrolled_at']) + ((int) $step['delay_hours']) * 3600;
            if (time() < $dueAt) {
                continue;
            }

            $userStmt = db()->prepare('SELECT name, email FROM users WHERE id = ?');
            $userStmt->execute([$enrollment['user_id']]);
            $user = $userStmt->fetch();

            if ($user) {
                $result = send_transactional_email($step['template_key'], $user['email'], [
                    'teacher_name' => $user['name'],
                    'name' => $user['name'],
                    'site_name' => $siteName,
                ]);
                if ($result['status'] === 'sent') {
                    $totalSent++;
                }
            }

            db()->prepare('INSERT IGNORE INTO email_drip_sends (enrollment_id, step_id) VALUES (?, ?)')
                ->execute([$enrollment['id'], $step['id']]);
            $sentStepIds[] = $step['id'];
        }

        if (count(array_intersect(array_column($steps, 'id'), $sentStepIds)) >= count($steps)) {
            db()->prepare("UPDATE email_drip_enrollments SET status = 'completed' WHERE id = ?")->execute([$enrollment['id']]);
        }
    }

    return $totalSent;
}

/** The shared secret that authorizes the cron endpoint over HTTP (cPanel "curl a URL" cron jobs). Auto-generated on first use. */
function get_cron_secret(): string
{
    $cron = get_setting('cron');
    if (empty($cron['secret'])) {
        $cron['secret'] = bin2hex(random_bytes(20));
        set_setting('cron', $cron);
    }
    return $cron['secret'];
}
