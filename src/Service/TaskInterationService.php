<?php

namespace ControleOnline\Service;

use ControleOnline\Entity\TaskInteration;
use Doctrine\ORM\EntityManagerInterface;

use Symfony\Component\Security\Core\Security;

class TaskInterationService
{

  public function __construct(
    private EntityManagerInterface $manager,
    private Security $security
  ) {}

  public function beforePersist(TaskInteration $taskInteration)
  {
    $taskInteration->setRegisteredBy($this->security->getUser()->getPeople());
    return  $taskInteration;
  }
}
