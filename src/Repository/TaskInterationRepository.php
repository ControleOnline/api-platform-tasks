<?php

namespace ControleOnline\Repository;

use ControleOnline\Entity\TaskInteration;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @method TaskInteration|null find($id, $lockMode = null, $lockVersion = null)
 * @method TaskInteration|null findOneBy(array $criteria, array $orderBy = null)
 * @method TaskInteration[]    findAll()
 * @method TaskInteration[]    findBy(array $criteria, array $orderBy = null, $limit = null, $offset = null)
 */
class TaskInterationRepository extends ServiceEntityRepository
{
  public function __construct(ManagerRegistry $registry)
  {
    parent::__construct($registry, TaskInteration::class);
  }
}
