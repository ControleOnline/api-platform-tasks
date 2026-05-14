<?php

namespace ControleOnline\Repository;

use ControleOnline\Entity\Task;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @method Task|null find($id, $lockMode = null, $lockVersion = null)
 * @method Task|null findOneBy(array $criteria, array $orderBy = null)
 * @method Task[]    findAll()
 * @method Task[]    findBy(array $criteria, array $orderBy = null, $limit = null, $offset = null)
 */
class TaskRepository extends ServiceEntityRepository
{
  public function __construct(ManagerRegistry $registry)
  {
    parent::__construct($registry, Task::class);
  }

  public function findOverduePendingTasksByTypeAndStatus(
    string $type,
    object $status,
    \DateTimeInterface $referenceTime
  ): array {
    return $this->createQueryBuilder('task')
      ->andWhere('task.type = :type')
      ->andWhere('task.taskStatus = :status')
      ->andWhere('task.dueDate IS NOT NULL')
      ->andWhere('task.dueDate <= :referenceTime')
      ->setParameter('type', $type)
      ->setParameter('status', $status)
      ->setParameter('referenceTime', $referenceTime)
      ->orderBy('task.dueDate', 'ASC')
      ->getQuery()
      ->getResult();
  }
}
