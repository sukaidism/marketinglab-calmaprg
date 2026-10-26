<?php
declare(strict_types=1);

namespace Lab;

use PDO;

final class DatabaseSummaryRepository
{
    public function __construct(private PDO $db) {}

    public function totalCustomers(): int
    {
        return (int) $this->db->query('SELECT COUNT(*) FROM customers')->fetchColumn();
    }

    public function customerConsentCounts(): array
    {
        return $this->db
            ->query(
                'SELECT marketing_opt_in, COUNT(*) AS total
                 FROM customers
                 GROUP BY marketing_opt_in
                 ORDER BY marketing_opt_in DESC'
            )
            ->fetchAll();
    }

    public function outboxCounts(): array
    {
        return $this->db
            ->query(
                'SELECT campaign_id, status, COUNT(*) AS total
                 FROM marketing_outbox
                 GROUP BY campaign_id, status'
            )
            ->fetchAll();
    }

    public function emailHistoryCounts(): array
    {
        return $this->db
            ->query(
                'SELECT campaign_id, status, COUNT(*) AS total
                 FROM sent_marketing_emails
                 GROUP BY campaign_id, status'
            )
            ->fetchAll();
    }
}
