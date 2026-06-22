<?php

namespace App\Service;

use App\Entity\Post;
use App\Repository\PostRepository;
use Psr\Log\LoggerInterface;

class PostImporterService
{
    private const int PAGE_CHUNK_SIZE = 5;

    public function __construct(
        private PostRepository  $postRepository,
        private PostApiClient   $postApiClient,
        private LoggerInterface $logger
    )
    {
    }

    public function import(): void
    {
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
        $post->setId($data['id'])
            ->setTitle($data['title'])
            ->setDescription($data['description'])
            ->setBody($data['body'])
            ->setCreatedAt(new \DateTimeImmutable($data['createdAt']));

        return $post;
    }


}
