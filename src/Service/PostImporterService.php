<?php

namespace App\Service;

use App\Entity\Post;
use App\Repository\PostRepository;
use Psr\Log\LoggerInterface;

//TODO: Добавит сохранения поседней страницы в файл
class PostImporterService
{
    private const int PAGE_CHUNK_SIZE = 5;

    public function __construct(
        private readonly PostRepository $postRepository,
        private readonly PostApiClient  $postApiClient,
        private LoggerInterface         $logger
    )
    {
    }

    public function import(): void
    {
        //TODO: сохранять на диск номер последней страницы
        $currentPage = 1;

        while (true) {
            $pages = range($currentPage, $currentPage + self::PAGE_CHUNK_SIZE - 1);
            $currentPage += self::PAGE_CHUNK_SIZE;

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
