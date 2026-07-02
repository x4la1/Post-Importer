<?php

namespace App\Service;

use Psr\Log\LoggerInterface;
use Symfony\Contracts\HttpClient\HttpClientInterface;


class PostApiClient
{
    private const string BASE_URL = "https://proof.moneymediagroup.co.uk/api";
    private const int RETRY_COUNT = 5;
    private const int TIMEOUT = 15;

    public function __construct(
        private readonly HttpClientInterface $client,
        private readonly ProxyPoolServiceInterface    $proxyPool,
        private readonly LoggerInterface     $logger
    )
    {
    }

    public function fetchPostsByPages(array $pages): array
    {
        $pendingPages = $pages;
        $ids = [];

        for ($attempt = 1; $attempt <= self::RETRY_COUNT; ++$attempt) {
            if (empty($pendingPages)) {
                break;
            }

            $responses = [];

            foreach ($pendingPages as $page) {
                $proxy = $this->proxyPool->getNextProxy();

                $options = [
                    'query' => [
                        'page' => $page,
                    ],
                    'timeout' => self::TIMEOUT,
                    'user_data' => $page,
                ];

                if (!is_null($proxy)) {
                    $options['proxy'] = $proxy;
                }

                $responses[] = $this->client->request(
                    'GET',
                    self::BASE_URL . "/posts",
                    $options,
                );
            }

            $loadedPages = [];

            foreach ($this->client->stream($responses) as $response => $chunk) {
                try {
                    if ($chunk->isFirst()) {
                        $statusCode = $response->getStatusCode();

                        if ($statusCode < 200 || $statusCode >= 300) {
                            $this->logger->warning("Invalid response", ['Status code' => $statusCode]);
                            $response->cancel();

                            continue;
                        }
                    }

                    if (!$chunk->isLast()) {
                        continue;
                    }

                    $page = (int)$response->getInfo('user_data');

                    array_push($ids, ...array_column($response->toArray(false), 'id'));
                    $loadedPages[] = $page;

                } catch (\Throwable $e) {
                    $response->cancel();

                    $this->logger->warning('Error', [
                        'error' => $e->getMessage(),
                    ]);
                }
            }

            if (!empty($loadedPages)) {
                $pendingPages = array_values(array_diff($pendingPages, $loadedPages));
            }
        }

        foreach ($pendingPages as $page) {
            $this->logger->warning("Failed to import page: $page");
        }

        return $this->fetchPostsByIds($ids);
    }

    private function fetchPostsByIds(array $ids): array
    {
        $pendingIds = $ids;
        $result = [];

        for ($attempt = 1; $attempt <= self::RETRY_COUNT; ++$attempt) {
            if (empty($pendingIds)) {
                break;
            }

            $responses = [];

            foreach ($pendingIds as $id) {
                $proxy = $this->proxyPool->getNextProxy();

                $options = [
                    'timeout' => self::TIMEOUT,
                    'user_data' => $id
                ];

                if (!is_null($proxy)) {
                    $options['proxy'] = $proxy;
                }

                $responses[] = $this->client->request(
                    'GET',
                    self::BASE_URL . "/post/{$id}",
                    $options,
                );
            }

            $loadedIds = [];

            foreach ($this->client->stream($responses) as $response => $chunk) {
                try {
                    if ($chunk->isFirst()) {
                        $statusCode = $response->getStatusCode();

                        if ($statusCode < 200 || $statusCode >= 300) {
                            $this->logger->warning("Invalid response", ['Status code' => $statusCode]);
                            $response->cancel();

                            continue;
                        }
                    }

                    if (!$chunk->isLast()) {
                        continue;
                    }

                    $id = (string)$response->getInfo('user_data');

                    $result[] = $response->toArray(false);
                    $loadedIds[] = $id;
                } catch (\Throwable $e) {
                    $response->cancel();

                    $this->logger->warning('Error', [
                        'error' => $e->getMessage(),
                    ]);
                }
            }

            if (!empty($loadedIds)) {
                $pendingIds = array_values(array_diff($pendingIds, $loadedIds));
            }
        }

        foreach ($pendingIds as $id) {
            $this->logger->warning("Failed to import post with id: $id");
        }

        return $result;
    }
}
