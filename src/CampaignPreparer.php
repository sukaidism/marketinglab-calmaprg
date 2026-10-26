<?php
declare(strict_types=1);

namespace Lab;

use RuntimeException;

final class CampaignPreparer
{
    public function __construct(
        private CustomerRepository $customers,
        private OutboxRepository $outbox
    ) {}

    public function prepare(string $campaignId, string $promoCode): void
    {
        $existingCount = $this->outbox->countForCampaign($campaignId);

        if ($existingCount === 50) {
            echo "Campaign {$campaignId} already has 50 prepared requests. Preserving existing selection.\n";
            return;
        }
        if ($existingCount > 0) {
            throw new RuntimeException(
                "Inconsistent outbox state for campaign {$campaignId}: found {$existingCount} rows (expected 0 or 50)."
            );
        }

        $selectedIds = $this->customers->selectRandomOptedIn(50);

        if (count($selectedIds) !== 50) {
            throw new RuntimeException('Unable to select 50 eligible opted-in customers.');
        }

        $subject = "Laboratory promotion ({$campaignId})";
        $body = "You have been selected to receive our promotional offer. Use promo code {$promoCode} at checkout.";

        $createdAt = gmdate('Y-m-d H:i:s');
        $requests = [];
        foreach ($selectedIds as $customerId) {
            $requests[] = [
                'requestId' => bin2hex(random_bytes(16)),
                'campaignId' => $campaignId,
                'customerId' => $customerId,
                'promoCode' => $promoCode,
                'subject' => $subject,
                'body' => $body,
                'createdAt' => $createdAt,
            ];
        }
        $this->outbox->insertPendingBatch($requests);
        echo "Prepared 50 PENDING requests for campaign {$campaignId} with promo code {$promoCode}.\n";
    }
}
