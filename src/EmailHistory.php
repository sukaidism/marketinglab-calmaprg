<?php
declare(strict_types=1);

namespace Lab;

use PDO;

final class EmailHistory
{
    public function __construct(private PDO $db) {}

    public function isComplete(string $campaignId, string $customerId): bool
    {
        $stmt = $this->db->prepare(
            "SELECT status FROM sent_marketing_emails WHERE campaign_id = ? AND customer_id = ?"
        );
        $stmt->execute([$campaignId, $customerId]);
        $status = $stmt->fetchColumn();
        return in_array($status, ['SENT', 'SKIPPED'], true);
    }

    public function saveSent(
        array $job,
        string $recipientEmail,
        string $provider,
        string $messageId,
        string $sentAt
    ): void {
        $stmt = $this->db->prepare(
            "INSERT INTO sent_marketing_emails
                (campaign_id, customer_id, request_id, promo_code, recipient_email, subject, status, provider, message_id, reason, processed_at, sent_at)
             VALUES (?, ?, ?, ?, ?, ?, 'SENT', ?, ?, NULL, ?, ?)
             ON DUPLICATE KEY UPDATE
                request_id = VALUES(request_id),
                promo_code = VALUES(promo_code),
                recipient_email = VALUES(recipient_email),
                subject = VALUES(subject),
                status = 'SENT',
                provider = VALUES(provider),
                message_id = VALUES(message_id),
                reason = NULL,
                processed_at = VALUES(processed_at),
                sent_at = VALUES(sent_at)"
        );
        $stmt->execute([
            $job['campaignId'],
            $job['customerId'],
            $job['requestId'],
            $job['promoCode'],
            $recipientEmail,
            $job['subject'],
            $provider,
            $messageId,
            $sentAt,
            $sentAt,
        ]);
    }

    public function saveSkipped(array $job, string $reason, ?string $recipientEmail = null): void
    {
        $now = gmdate('Y-m-d H:i:s');
        $stmt = $this->db->prepare(
            "INSERT INTO sent_marketing_emails
                (campaign_id, customer_id, request_id, promo_code, recipient_email, subject, status, provider, message_id, reason, processed_at, sent_at)
             VALUES (?, ?, ?, ?, ?, ?, 'SKIPPED', NULL, NULL, ?, ?, NULL)
             ON DUPLICATE KEY UPDATE
                request_id = VALUES(request_id),
                promo_code = VALUES(promo_code),
                recipient_email = VALUES(recipient_email),
                subject = VALUES(subject),
                status = 'SKIPPED',
                reason = VALUES(reason),
                processed_at = VALUES(processed_at),
                sent_at = NULL"
        );
        $stmt->execute([
            $job['campaignId'],
            $job['customerId'],
            $job['requestId'],
            $job['promoCode'],
            $recipientEmail,
            $job['subject'],
            $reason,
            $now,
        ]);
    }

    public function saveFailed(array $job, string $reason, ?string $recipientEmail = null): void
    {
        if (empty($job['campaignId']) || empty($job['customerId']) || empty($job['requestId'])) {
            return;
        }
        $now = gmdate('Y-m-d H:i:s');
        $stmt = $this->db->prepare(
            "INSERT INTO sent_marketing_emails
                (campaign_id, customer_id, request_id, promo_code, recipient_email, subject, status, provider, message_id, reason, processed_at, sent_at)
             VALUES (?, ?, ?, ?, ?, ?, 'FAILED', NULL, NULL, ?, ?, NULL)
             ON DUPLICATE KEY UPDATE
                request_id = VALUES(request_id),
                promo_code = VALUES(promo_code),
                recipient_email = VALUES(recipient_email),
                subject = VALUES(subject),
                status = 'FAILED',
                reason = VALUES(reason),
                processed_at = VALUES(processed_at)"
        );
        $stmt->execute([
            $job['campaignId'],
            $job['customerId'],
            $job['requestId'],
            $job['promoCode'] ?? '',
            $recipientEmail,
            $job['subject'] ?? '',
            $reason,
            $now,
        ]);
    }
}
