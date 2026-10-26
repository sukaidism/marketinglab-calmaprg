<?php
declare(strict_types=1);

namespace Lab;

use PHPMailer\PHPMailer\PHPMailer;

final class EmailSender
{
    public function __construct(private array $smtpConfig) {}

    public function send(array $customer, array $job): string
    {
        $mailer = new PHPMailer(true);
        $mailer->isSMTP();
        $mailer->Host       = $this->smtpConfig['host'];
        $mailer->Port       = (int) $this->smtpConfig['port'];
        $mailer->SMTPAuth   = true;
        $mailer->Username   = $this->smtpConfig['username'];
        $mailer->Password   = $this->smtpConfig['password'];
        $mailer->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
        $mailer->CharSet    = 'UTF-8';

        $mailer->setFrom($this->smtpConfig['from_address'], 'Marketing Laboratory');
        $mailer->isHTML(false);

        // TODO E1: Add the customer's email and name as the recipient.
        $mailer->addAddress($customer['email'], $customer['full_name']);

        // TODO E2: Set the campaign subject and a personalized plain-text body that clearly displays the persisted promoCode from the job.
        $mailer->Subject = $job['subject'];
        $mailer->Body = sprintf(
            "Hello %s,\n\n%s\n\nCampaign ID: %s\nPromotional Code: %s\nRequest ID: %s\n",
            $customer['full_name'],
            $job['body'],
            $job['campaignId'],
            $job['promoCode'],
            $job['requestId']
        );

        $mailer->send();
        return $mailer->getLastMessageID();
    }
}
