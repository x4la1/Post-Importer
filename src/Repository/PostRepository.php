<?php

namespace App\Repository;

use App\Entity\Post;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<Post>
 */
class PostRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Post::class);
    }

    public function savePostsArray(array $posts): void
    {
        foreach ($posts as $post) {
            $this->getEntityManager()->persist($post);
        }

        $this->getEntityManager()->flush();
        $this->getEntityManager()->clear();
    }

    public function getExistingIds(array $idsToCheck): array
    {
        if (empty($idsToCheck)) {
            return [];
        }

        $results = $this->createQueryBuilder('p')
            ->select('p.id')
            ->where('p.id IN (:ids)')
            ->setParameter('ids', $idsToCheck)
            ->getQuery()
            ->getArrayResult();

        return array_column($results, 'id');
    }
}
