<?php
declare(strict_types=1);

namespace Lab;

use PDO;

final class CustomerRepository
{
    public function __construct(private PDO $db) {}

    /**
     * @param list<array{customerId: string, fullName: string, email: string, marketingOptIn: int, createdAt: string}> $customers
     */
    public function insertIgnoringDuplicates(array $customers): void
    {
        $stmt = $this->db->prepare(
            'INSERT IGNORE INTO customers (customer_id, full_name, email, marketing_opt_in, created_at)
             VALUES (:customer_id, :full_name, :email, :marketing_opt_in, :created_at)'
        );

        $this->db->beginTransaction();
        try {
            foreach ($customers as $customer) {
                $stmt->execute([
                    'customer_id' => $customer['customerId'],
                    'full_name' => $customer['fullName'],
                    'email' => $customer['email'],
                    'marketing_opt_in' => $customer['marketingOptIn'],
                    'created_at' => $customer['createdAt'],
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

    public function findById(string $customerId): ?array
    {
        $stmt = $this->db->prepare(
            'SELECT customer_id, full_name, email, marketing_opt_in
             FROM customers
             WHERE customer_id = :customer_id'
        );
        $stmt->execute(['customer_id' => $customerId]);
        $customer = $stmt->fetch();

        return $customer === false ? null : $customer;
    }

    public function selectRandomOptedIn(int $limit): array
    {
        if ($limit < 1) {
            throw new \InvalidArgumentException('Customer selection limit must be positive.');
        }

        $stmt = $this->db->prepare(
            'SELECT customer_id
             FROM customers
             WHERE marketing_opt_in = 1
             ORDER BY RAND()
             LIMIT :limit'
        );
        $stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
        $stmt->execute();

        return $stmt->fetchAll(PDO::FETCH_COLUMN);
    }
}
