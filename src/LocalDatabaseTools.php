<?php
declare(strict_types=1);

namespace Lab;

use PDO;
use RuntimeException;

final class LocalDatabaseTools
{
    public function __construct(
        private PDO $db,
        private DatabaseSummaryRepository $summary
    ) {}

    public function initSchema(string $schemaPath): void
    {
        $sql = file_get_contents($schemaPath);
        if ($sql === false) {
            throw new RuntimeException("Cannot read schema file: {$schemaPath}");
        }
        $statements = array_filter(array_map('trim', explode(';', $sql)));
        foreach ($statements as $stmt) {
            if ($stmt !== '') {
                $this->db->exec($stmt);
            }
        }
        echo "Database schema initialized successfully.\n";
    }

    public function inspect(): void
    {
        $totalCustomers = $this->summary->totalCustomers();
        echo "=== Local Database Summary ===\n";
        echo "Total customers: {$totalCustomers}\n";

        foreach ($this->summary->customerConsentCounts() as $row) {
            echo "  marketing_opt_in={$row['marketing_opt_in']}: {$row['total']}\n";
        }

        echo "\nOutbox counts by campaign and status:\n";
        $outboxRows = $this->summary->outboxCounts();
        if (!$outboxRows) {
            echo "  (empty)\n";
        } else {
            foreach ($outboxRows as $row) {
                echo "  {$row['campaign_id']} [{$row['status']}]: {$row['total']}\n";
            }
        }

        echo "\nSent marketing emails history by campaign and status:\n";
        $histRows = $this->summary->emailHistoryCounts();
        if (!$histRows) {
            echo "  (empty)\n";
        } else {
            foreach ($histRows as $row) {
                echo "  {$row['campaign_id']} [{$row['status']}]: {$row['total']}\n";
            }
        }
    }
}
