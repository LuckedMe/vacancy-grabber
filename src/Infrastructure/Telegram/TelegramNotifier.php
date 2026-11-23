<?php

declare(strict_types=1);

namespace App\Infrastructure\Telegram;

use App\Application\Telegram\TelegramSender;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Contracts\HttpClient\Exception\ExceptionInterface;
use Symfony\Contracts\HttpClient\HttpClientInterface;

use function sprintf;

final readonly class TelegramNotifier implements TelegramSender
{
    public function __construct(
        private HttpClientInterface $httpClient,
        #[Autowire('%env(string:TELEGRAM_API_TOKEN)%')]
        private string $botToken,
    ) {
    }

    public function isEnabled(): bool
    {
        return $this->botToken !== '';
    }

    /**
     * @throws TelegramException
     */
    public function send(string $chatId, string $text): void
    {
        $this->call('sendMessage', ['chat_id' => $chatId, 'text' => $text, 'disable_web_page_preview' => true]);
    }

    /**
     * Updates the bot has received and Telegram still keeps (about 24 hours); nothing is marked as read.
     *
     * @return list<array<string, mixed>>
     *
     * @throws TelegramException
     */
    public function getUpdates(): array
    {
        return $this->call('getUpdates', ['limit' => 100]);
    }

    /**
     * @param array<string, mixed> $payload
     *
     * @return mixed the "result" of the response
     *
     * @throws TelegramException
     */
    private function call(string $method, array $payload): mixed
    {
        try {
            $response = $this->httpClient->request(
                'POST',
                sprintf('https://api.telegram.org/bot%s/%s', $this->botToken, $method),
                ['json' => $payload]
            );

            // false: do not throw on 4xx/5xx, Telegram explains the error in the body
            $data = $response->toArray(false);
        } catch (ExceptionInterface $e) {
            throw new TelegramException('Telegram request failed: ' . $e->getMessage(), 0, $e);
        }

        if (($data['ok'] ?? false) !== true) {
            throw new TelegramException(sprintf(
                'Telegram API error %s: %s',
                $data['error_code'] ?? $response->getStatusCode(),
                $data['description'] ?? 'unknown error',
            ));
        }

        return $data['result'] ?? null;
    }
}
