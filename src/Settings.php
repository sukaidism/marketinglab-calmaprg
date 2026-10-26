<?php
declare(strict_types=1);

namespace Lab;

use Aws\Sqs\SqsClient;
use RuntimeException;

final class Settings
{
    public static function required(string $name): string
    {
        $val = getenv($name);
        if ($val === false || trim($val) === '') {
            throw new RuntimeException("Required environment variable {$name} is not set.");
        }
        return trim($val);
    }

    public static function optional(string $name, string $default = ''): string
    {
        $val = getenv($name);
        return ($val === false || trim($val) === '') ? $default : trim($val);
    }

    public static function queue(): SqsClient
    {
        $config = [
            'version' => 'latest',
            'region'  => self::optional('AWS_REGION', 'us-east-1'),
        ];
        $profile = self::optional('AWS_PROFILE', '');
        if ($profile !== '') {
            $config['profile'] = $profile;
        }
        return new SqsClient($config);
    }

    public static function smtp(): array
    {
        return [
            'host'         => self::required('MAILTRAP_HOST'),
            'port'         => (int) self::required('MAILTRAP_PORT'),
            'username'     => self::required('MAILTRAP_USERNAME'),
            'password'     => self::required('MAILTRAP_PASSWORD'),
            'from_address' => self::optional('MAIL_FROM_ADDRESS', 'marketing@example.com'),
        ];
    }

    public static function sendDelaySeconds(): int
    {
        return max(0, (int) self::optional('MAILTRAP_DELAY_SECONDS', '11'));
    }
}

?>