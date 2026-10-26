<?php
declare(strict_types=1);

namespace Lab;

use PDO;

final class OutboxRepository
{
    public function __construct(private PDO $db) {}

    public function countForCampaign(string $campaignId): int
    {
        $stmt = $this->db->prepare(
            'SELECT COUNT(*) FROM marketing_outbox WHERE campaign_id = :campaign_id'
        );
        $stmt->execute(['campaign_id' => $campaignId]);

        return (int) $stmt->fetchColumn();
    }

    /**
     * @param list<array{requestId: string, campaignId: string, customerId: string, promoCode: string, subject: string, body: string, createdAt: string}> $requests
     */
    public function insertPendingBatch(array $requests): void
    {
        $stmt = $this->db->prepare(
            "INSERT INTO marketing_outbox
                (request_id, campaign_id, customer_id, promo_code, subject, body, status, created_at)
             VALUES (:request_id, :campaign_id, :customer_id, :promo_code, :subject, :body, 'PENDING', :created_at)"
        );

        $this->db->beginTransaction();
        try {
            foreach ($requests as $request) {
                $stmt->execute([
                    'request_id' => $request['requestId'],
                    'campaign_id' => $request['campaignId'],
                    'customer_id' => $request['customerId'],
                    'promo_code' => $request['promoCode'],
                    'subject' => $request['subject'],
                    'body' => $request['body'],
                    'created_at' => $request['createdAt'],
                ]);
            }
            $this->db->commit();
        } catch (\Throwable $e) {
            if ($this->db->inTransaction()) {
                $this->db->rollBack();
            }
            throw $e;
        }
    }

    public function findPending(): array
    {
        $stmt = $this->db->query(
            "SELECT request_id, campaign_id, customer_id, promo_code, subject, body
             FROM marketing_outbox
             WHERE status = 'PENDING'
             ORDER BY created_at ASC, request_id ASC"
        );

        return $stmt->fetchAll();
    }

    public function markQueued(string $queuedAt, string $messageId, string $requestId): bool
    {
        $stmt = $this->db->prepare(
            "UPDATE marketing_outbox
             SET status = 'QUEUED', queued_at = :queued_at, sqs_message_id = :message_id
             WHERE request_id = :request_id AND status = 'PENDING'"
        );
        $stmt->execute([
            'queued_at' => $queuedAt,
            'message_id' => $messageId,
            'request_id' => $requestId,
        ]);

        return $stmt->rowCount() === 1;
    }
}
