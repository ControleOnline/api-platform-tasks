<?php

namespace ControleOnline\Service;

use ControleOnline\Entity\People;
use ControleOnline\Entity\Task;
use ControleOnline\Entity\TaskInteration;
use ControleOnline\Messages\MessageInterface;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Security\Core\Authentication\Token\Storage\TokenStorageInterface as Security;
use Symfony\Component\HttpFoundation\RequestStack;
use Doctrine\ORM\QueryBuilder;

class TaskService
{
  private $request;

  public function __construct(
    private EntityManagerInterface $manager,
    private Security $security,
    private RequestStack $requestStack,
    private StatusService $statusService,
    private PeopleService $peopleService,
    private FileService $fileService
  ) {
    $this->request = $this->requestStack->getCurrentRequest();
  }

  public function securityFilter(QueryBuilder $queryBuilder, $resourceClass = null, $applyTo = null, $rootAlias = null): void
  {

    /*
    $companies   = $this->peopleService->getMyCompanies();
    $queryBuilder->andWhere(sprintf('%s.taskFor IN(:companies) OR %s.client IN(:companies) OR %s.provider IN(:companies) %s.registeredBy IN(:companies)', $rootAlias, $rootAlias));
    $queryBuilder->setParameter('companies', $companies);
    */

    echo $queryBuilder->getQuery()->getSQL();
  }
}
