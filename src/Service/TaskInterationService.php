<?php

namespace ControleOnline\Service;

use ControleOnline\Entity\TaskInteration;
use Doctrine\ORM\EntityManagerInterface;

use Symfony\Component\Security\Core\Authentication\Token\Storage\TokenStorageInterface
 AS Security;

class TaskInterationService
{

  public function __construct(
    private EntityManagerInterface $manager,
    private Security $security
  ) {}

  public function prePersist(TaskInteration $taskInteration)
  {
    $taskInteration->setRegisteredBy($this->security->getToken()->getUser()->getPeople());
    return  $taskInteration;
  }
}
