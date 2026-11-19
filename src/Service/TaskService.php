<?php

/*
 * Contract imported from AGENTS.md
 * ## Escopo
 * - Modulo de tarefas.
 * - Cobre `Task`, `TaskInteration` e servicos ligados ao acompanhamento e interacao em tarefas.
 *
 * ## Quando usar
 * - Prompts sobre tarefas, historico de interacao, acompanhamento e fluxo interno de task.
 *
 * ## Limites
 * - Nao misturar aqui fila de producao, pedido ou notificacao externa quando houver modulo proprio para isso.
 */


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
    private ?PeopleService $peopleService = null,
    private ?TicketRulesService $ticketRules = null,
    private ?TicketAttendanceService $attendance = null,
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
    $task->setType($context);
    $task->setClient($client);
    

    $this->manager->persist($task);
    $this->manager->flush();

    return $task;
  }

  /**
   * Creates an AI chat Task (type=ia) scoped to the company (provider).
   * client defaults to the company; announce holds channel/provider metadata.
   *
   * @param array<string, mixed> $announceMeta
   */
  public function addIaTask(
    People $company,
    string $name,
    array $announceMeta = []
  ): Task {
    $user = $this->security->getToken()?->getUser();
    $registeredBy = $user && method_exists($user, 'getPeople') ? $user->getPeople() : $company;

    $task = new Task();
    $task->setName(mb_substr($name !== '' ? $name : 'Chat IA', 0, 50));
    $task->setRegisteredBy($registeredBy);
    $task->setTaskFor($registeredBy);
    $task->setProvider($company);
    $task->setClient($company);
    $task->setType('ia');
    $task->setTaskStatus($this->statusService->discoveryStatus('open', 'open', 'ia'));
    $meta = array_merge(['channel' => 'mcp'], $announceMeta);
    $task->setAnnounce($meta);

    $this->manager->persist($task);
    $this->manager->flush();

    return $task;
  }

  /** Requests are born without a responsible person: the distribution engine picks one. */
  public function prePersist(Task $task): Task
  {
    if ($this->ticketRules && TicketRulesService::isTicket($task) && !$task->getTaskFor() && $task->getProvider()) {
      if (!$task->getAiAgent() && $this->attendance?->aiFirst($task)) {
        // AI first: the company AI attends with no responsible person until it hands off.
        $task->setAiAgent('auto');
      } elseif (!$task->getAiAgent()) {
        $responsible = $this->ticketRules->pickResponsible($task);
        if ($responsible) {
          $task->setTaskFor($responsible);
        }
      }
    }

    return $task;
  }

  /**
   * A user only sees tasks of the companies they work for (provider, client or taskFor) and the ones
   * where they are the client or the author. Without an authenticated person (worker, cron) nothing
   * is filtered: background jobs run with their own tenant/company scoping.
   */
  public function securityFilter(QueryBuilder $queryBuilder, $resourceClass = null, $applyTo = null, $rootAlias = null): void
  {
    $me = $this->peopleService?->getMyPeople();
    if (!$me) {
      return;
    }
    $companies = $this->peopleService->getMyCompanies() ?: [0];

    $queryBuilder
      ->andWhere(sprintf(
        '%1$s.provider IN (:taskSecurityCompanies) OR %1$s.client IN (:taskSecurityCompanies) OR %1$s.taskFor IN (:taskSecurityCompanies)'
          . ' OR %1$s.client = :taskSecurityMe OR %1$s.taskFor = :taskSecurityMe OR %1$s.registeredBy = :taskSecurityMe',
        $rootAlias
      ))
      ->setParameter('taskSecurityCompanies', $companies)
      ->setParameter('taskSecurityMe', $me);
  }
}
