<?php
declare(strict_types=1);

namespace Lab;

use Faker\Generator;
final class CustomerSeeder
{
    public function __construct(
        private CustomerRepository $customers,
        private Generator $faker
    ) {}

    public function seed(): void
    {
        $createdAt = gmdate('Y-m-d H:i:s');
        $customers = [];
        for ($i = 1; $i <= 10000; $i++) {
            $customers[] = [
                'customerId' => sprintf('CUST-%05d', $i),
                'fullName' => $this->faker->name(),
                'email' => sprintf('customer%05d@example.com', $i),
                'marketingOptIn' => $i <= 8000 ? 1 : 0,
                'createdAt' => $createdAt,
            ];
        }

        $this->customers->insertIgnoringDuplicates($customers);
        echo "Seeded 10,000 customers (8,000 opted-in, 2,000 opted-out).\n";
    }
}
