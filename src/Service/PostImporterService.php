<?php

namespace App\Service;

use App\Entity\Post;
use App\Repository\PostRepository;
use Psr\Log\LoggerInterface;

class PostImporterService
{
    private const int PAGE_CHUNK_SIZE = 5;
    private const string LAST_PAGE_FILE_PATH = __DIR__ . '/LastPage.txt';

    public function __construct(
        private readonly PostRepository     $postRepository,
        private readonly PostApiClient      $postApiClient,
        private ImportStateStorageInterface $importStateStorage,
        private LoggerInterface             $logger
    )
    {
    }

    public function import(?int $fromPage = null, ?int $toPage = null): void
    {
        $currentPage = $fromPage ?? $this->importStateStorage->getLastPageToImport();

        while (true) {
            $this->importStateStorage->setLastPageToImport($currentPage);
            $pages = range($currentPage, $currentPage + self::PAGE_CHUNK_SIZE - 1);

            $currentPage += self::PAGE_CHUNK_SIZE;

            if ($toPage !== null) {
                $pages = array_values(array_filter(
                    $pages,
                    fn($p) => $p <= $toPage
                ));
            }

            if (empty($pages)) {
                break;
            }

            $postsData = $this->postApiClient->fetchPostsByPages($pages);

            if (empty($postsData)) {
                break;
            }

            $incomingIds = array_column($postsData, 'id');
            $existingIds = $this->postRepository->getExistingIds($incomingIds);

            $processedIds = [];
            $posts = [];

            foreach ($postsData as $data) {
                if (!$this->isValidPost($data)) {
                    $this->logger->warning('Invalid post data');
                    continue;
                }

                $id = $data['id'];

                if (!in_array($id, $existingIds) && !in_array($id, $processedIds)) {
                    $posts[] = $this->arrayToPost($data);
                    $processedIds[] = $id;
                }
            }

            if (!empty($posts)) {
                $this->postRepository->savePostsArray($posts);
            }
        }
    }

    private function arrayToPost(array $data): Post
    {
        $post = new Post();
        $post->setExternalId($data['id'])
            ->setTitle($data['title'])
            ->setDescription($data['description'])
            ->setBody($data['body'])
            ->setCreatedAt(new \DateTimeImmutable($data['createdAt']));

        return $post;
    }

    private function isValidPost(array $data): bool
    {
        if (empty($data['id']) || empty($data['title']) || empty($data['description']) || empty($data['body'])) {
            return false;
        }

        if (mb_strlen($data['id']) > 255 || mb_strlen($data['title']) > 255 || mb_strlen($data['description']) > 255 || mb_strlen($data['body']) > 255) {
            return false;
        }

        try {
            new \DateTimeImmutable($data['createdAt']);
        } catch (\Exception $e) {
            return false;
        }

        return true;
    }
}
