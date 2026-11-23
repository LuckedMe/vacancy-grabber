<?php

declare(strict_types=1);

namespace App\Tests\Suite\Unit\Application\Telegram;

use App\Application\Telegram\Message\Command\SendTelegramNotificationAsyncCommand;
use App\Application\Telegram\MessageHandler\Command\SendTelegramNotificationHandler;
use App\Infrastructure\Telegram\TelegramException;
use App\Infrastructure\Telegram\TelegramNotifier;
use Codeception\Test\Unit;
use Symfony\Component\HttpClient\MockHttpClient;
use Symfony\Component\HttpClient\Response\MockResponse;

use function json_decode;

final class SendTelegramNotificationHandlerTest extends Unit
{
    public function testSendsTheMessageToTheChat(): void
    {
        $requests = [];
        $client   = new MockHttpClient(static function (string $method, string $url, array $options) use (&$requests) {
            $requests[] = [$method, $url, json_decode($options['body'], true)];

            return new MockResponse('{"ok":true,"result":{}}');
        });

        $handler = new SendTelegramNotificationHandler(new TelegramNotifier($client, 'token'));
        $handler(new SendTelegramNotificationAsyncCommand('123', 'hello'));

        self::assertCount(1, $requests);
        self::assertSame('POST', $requests[0][0]);
        self::assertSame('https://api.telegram.org/bottoken/sendMessage', $requests[0][1]);
        self::assertSame('123', $requests[0][2]['chat_id']);
        self::assertSame('hello', $requests[0][2]['text']);
    }

    public function testDoesNothingWithoutBotToken(): void
    {
        $client = new MockHttpClient(static function (): never {
            self::fail('No request is expected when the bot token is empty');
        });

        $handler = new SendTelegramNotificationHandler(new TelegramNotifier($client, ''));
        $handler(new SendTelegramNotificationAsyncCommand('123', 'hello'));

        self::assertSame(0, $client->getRequestsCount());
    }

    public function testTelegramErrorPropagatesSoMessengerCanRetry(): void
    {
        $client = new MockHttpClient(new MockResponse(
            '{"ok":false,"error_code":400,"description":"chat not found"}',
            ['http_code' => 400],
        ));

        $handler = new SendTelegramNotificationHandler(new TelegramNotifier($client, 'token'));

        $this->expectException(TelegramException::class);
        $handler(new SendTelegramNotificationAsyncCommand('123', 'hello'));
    }
}
