<?php

namespace ControleOnline\Service;

use ControleOnline\Entity\People;
use ControleOnline\Entity\Task;
use ControleOnline\Entity\Phone;
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
  ) {
    $this->request = $this->requestStack->getCurrentRequest();
  }


  public function addTask(
    People $provider,
    People $taskFor,
    People $client,
    string $context
  ): Task {


    $phones = [];
    /**
     * @var Phone $phone
     */
    foreach ($client->getPhone() as $phone) {
      $phones[] = $phone->getDdi() . $phone->getDdd() . $phone->getPhone();
    }

    $task = new Task();
    $task->setRegisteredBy($this->security->getToken()->getUser()->getPeople());
    $task->setTaskFor($taskFor);
    $task->setProvider($provider);
    $task->setTaskStatus($this->statusService->discoveryStatus('open', 'open', $context));
    $task->setAnnounce($phones);


    $this->manager->persist($task);
    $this->manager->flush();

    return $task;
  }


  public function securityFilter(QueryBuilder $queryBuilder, $resourceClass = null, $applyTo = null, $rootAlias = null): void
  {

    /*
    $companies   = $this->peopleService->getMyCompanies();
    $queryBuilder->andWhere(sprintf('%s.taskFor IN(:companies) OR %s.client IN(:companies) OR %s.provider IN(:companies) %s.registeredBy IN(:companies)', $rootAlias, $rootAlias));
    $queryBuilder->setParameter('companies', $companies);
    */

    //echo $queryBuilder->getQuery()->getSQL();
  }
}
