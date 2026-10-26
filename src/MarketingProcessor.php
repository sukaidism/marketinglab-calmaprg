<?php
declare(strict_types=1);

namespace Lab;

use InvalidArgumentException;

final class MarketingProcessor
{
    public function __construct(
        private CustomerRepository $customers,
        private EmailHistory $history,
        private EmailSender $sender,
        private ActivityLogger $logger,
        private int $sendDelaySeconds = 11
    ) {}

    public function process(array $job): void
    {
        $requiredFields = ['requestId', 'campaignId', 'customerId', 'promoCode', 'subject', 'body'];
        foreach ($requiredFields as $field) {
            if (!isset($job[$field]) || !is_string($job[$field]) || trim($job[$field]) === '') {
                throw new InvalidArgumentException("Missing or invalid required field: {$field}");
            }
        }

        // TODO P1: Use EmailHistory::isComplete() to return early for an already SENT or SKIPPED pair.
        if ($this->history->isComplete($job['campaignId'], $job['customerId'])) {
            $this->logger->record('duplicate_skipped', [
                'requestId'  => $job['requestId'],
                'campaignId' => $job['campaignId'],
                'customerId' => $job['customerId'],
            ]);
            return;
        }

        // TODO M2: Record processing_started
        $this->logger->record('processing_started', [
            'requestId'  => $job['requestId'],
            'campaignId' => $job['campaignId'],
            'customerId' => $job['customerId'],
        ]);

        $customer = null;
        try {
            $customer = $this->customers->findById($job['customerId']);

            // TODO P3: Save SKIPPED and return if the customer is missing or not opted in.
            if ($customer === null || (int) $customer['marketing_opt_in'] !== 1) {
                $reason = ($customer === null)
                    ? 'Customer record not found'
                    : 'Customer has not opted in to marketing';
                $this->history->saveSkipped($job, $reason, $customer['email'] ?? null);

                // TODO M2: Record skipped only after local outcome is saved.
                $this->logger->record('skipped', [
                    'requestId'  => $job['requestId'],
                    'campaignId' => $job['campaignId'],
                    'customerId' => $job['customerId'],
                    'reason'     => $reason,
                ]);
                return;
            }

            // TODO P4: Call the injected EmailSender and retain the returned MIME Message-ID.
            $mimeMessageId = $this->sender->send($customer, $job);

            // TODO P5: Save SENT with recipient, provider, Message-ID, and UTC sent_at.
            $sentAt = gmdate('Y-m-d H:i:s');
            $this->history->saveSent($job, $customer['email'], 'mailtrap', $mimeMessageId, $sentAt);

            // TODO M2: Record sent only after local outcome is saved.
            $this->logger->record('sent', [
                'requestId'     => $job['requestId'],
                'campaignId'    => $job['campaignId'],
                'customerId'    => $job['customerId'],
                'mimeMessageId' => $mimeMessageId,
            ]);

            if ($this->sendDelaySeconds > 0) {
                sleep($this->sendDelaySeconds);
            }
        } catch (\Throwable $e) {
            $this->history->saveFailed($job, $e->getMessage(), $customer['email'] ?? null);
            throw $e;
        }
    }
}
