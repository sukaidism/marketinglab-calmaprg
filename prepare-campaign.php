<?php
declare(strict_types=1);
require __DIR__ . '/config.php';

$campaignId = $argv[1] ?? 'CAMP-001';
$promoCode  = $argv[2] ?? 'PROMO15';

$db = (new Lab\Database())->connect();
$preparer = new Lab\CampaignPreparer(
    new Lab\CustomerRepository($db),
    new Lab\OutboxRepository($db)
);
$preparer->prepare($campaignId, $promoCode);
