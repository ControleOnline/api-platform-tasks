<?php

namespace ControleOnline\Service;

use ControleOnline\Entity\Connection;
use ControleOnline\Entity\People;
use ControleOnline\Entity\Task;
use ControleOnline\Entity\TaskInteration;
use ControleOnline\Messages\MessageInterface;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\QueryBuilder;
use Symfony\Component\Security\Core\Authentication\Token\Storage\TokenStorageInterface as Security;

class TaskInterationService
{

  private bool $notify = true;

  public function __construct(
    private EntityManagerInterface $manager,
    private Security $security,
    private StatusService $statusService,
    private DomainService $domainService,
    private PeopleService $peopleService,
    private FileService $fileService,
    private IntegrationService $integrationService,
    private AutomationMessagesService $automationMessagesService,
    private ?TicketRulesService $ticketRules = null,
  ) {}

  public function addClientInteration(MessageInterface $message, Connection $connection, string $type): TaskInteration
  {

    $this->notify = false;
    $number = preg_replace('/\D/', '', $message->getOriginNumber());
    $name = '';
    $phone = [
      'ddi' => substr($number, 0, 2),
      'ddd' => substr($number, 2, 2),
      'phone' => substr($number, 4)
    ];
    $registredBy = $this->peopleService->discoveryPeople(null,  null,  $phone,  $name, null);
    $task = $this->discoveryOpenTask($connection->getPeople(), $registredBy, $registredBy, $type, $number);

    $this->automationMessagesService->receiveMessage($message, $connection, $task);

    return $this->addInteration($registredBy, $message, $task, $type, 'public');
  }

  /**
   * Every inbound customer message (WhatsApp official/QR or e-mail) enters here.
   *
   * - joins the customer's open conversation in ia|relationship|support, if any;
   * - otherwise opens one: "ia" with an AI assigned when the company has an AI configured,
   *   or the connection type (relationship/support) for humans only;
   * - remembers the destination and the connection it came through ("conn:{id}"),
   *   so every reply leaves through the same number/mailbox.
   */
  public function receiveCustomerMessage(MessageInterface $message, Connection $connection, string $name, bool $aiAvailable): TaskInteration
  {
    $this->notify = false;
    try {
      $isEmail = method_exists($connection, 'isEmail') && $connection->isEmail();
      $origin = trim($message->getOriginNumber());
      if ($isEmail) {
        $destination = strtolower($origin);
        $client = $this->peopleService->discoveryPeople(null, $destination, [], $name ?: $destination, null);
      } else {
        $destination = preg_replace('/\D/', '', $origin);
        $phone = ['ddi' => substr($destination, 0, 2), 'ddd' => substr($destination, 2, 2), 'phone' => substr($destination, 4)];
        $client = $this->peopleService->discoveryPeople(null, null, $phone, $name, null);
      }
      $provider = $connection->getPeople();

      $task = $this->findOpenConversationTask($provider, $client);
      if (!$task) {
        $type = $aiAvailable ? 'ia' : (in_array($connection->getType(), ['relationship', 'support'], true) ? $connection->getType() : 'support');
        $task = $this->discoveryOpenTask($provider, $client, $client, $type, $destination);
        if ($aiAvailable) $task->setAiAgent('auto');
        if (!$task->getName())
          $task->setName(mb_substr(($isEmail ? 'E-mail ' : 'WhatsApp ') . ($name ?: $destination), 0, 50));
      }
      $task->addAnnounce($destination);
      $task->addAnnounce(self::CONNECTION_MARKER . $connection->getId());
      $task->setAlterDate(new \DateTime('now'));

      return $this->addInteration($client, $message, $task, 'comment', 'public');
    } finally {
      $this->notify = true;
    }
  }

  public function addInteration(People $registredBy, MessageInterface $message, Task $task, string $type, ?string $visibility = 'private'): TaskInteration
  {

    $file = null;
    $media = $message->getMessageContent()->getMedia();
    if ($media)
      $file = $this->fileService->addFile(
        $registredBy,
        pack("C*", ...$media->getData()),
        $type
      );

    $taskInteration = new TaskInteration();
    $taskInteration->setTask($task);
    $taskInteration->setType($type);
    $taskInteration->setVisibility($visibility);
    $taskInteration->setBody($message->getMessageContent()->getBody());
    $taskInteration->setRegisteredBy($registredBy);
    if ($file)
      $taskInteration->setFile($file);

    $this->manager->persist($taskInteration);
    $this->manager->flush();

    return $taskInteration;
  }

  public function discoveryOpenTask(People $provider, People $client, People $registredBy, string $type, ?string $announce = null): Task
  {

    $openStatus = $this->statusService->discoveryStatus('open', 'open', $type);
    $pendingStatus = $this->statusService->discoveryStatus('pending', 'pending', $type);

    $task = $this->manager->getRepository(Task::class)->findOneBy([
      'taskStatus' => [$openStatus, $pendingStatus],
      'provider' => $provider,
      'client' => $client,
      'registeredBy' => $registredBy,
      'type' => $type
    ]);

    if (!$task) {
      $task = new Task();
      $task->setRegisteredBy($registredBy);
      $task->setProvider($provider);
      $task->setClient($client);
      $task->settype($type);
    }

    if ($announce) $task->addAnnounce($announce);
    $task->setTaskStatus($openStatus);
    $this->manager->persist($task);
    $this->manager->flush();

    return $task;
  }

  public function searchConnectionFromPeople(People $people, string $type, $mainConnection = false): ?Connection
  {
    $connection =  $this->manager->getRepository(Connection::class)->findOneBy(['type' => $type, 'people' => $people]);
    if (!$connection && $mainConnection)
      $connection =  $this->manager->getRepository(Connection::class)->findOneBy(['type' => $type, 'people' => $this->domainService->getPeopleDomain()->getPeople()]);

    return $connection;
  }

  /**
   * Conversation types that may receive a customer channel message (WhatsApp).
   * "ia" is the AI front door; relationship/support are human handoff targets.
   */
  public const CONVERSATION_TYPES = ['ia', 'relationship', 'support'];

  /** Announce marker "conn:{id}": the connection the conversation came through (replies use it). */
  public const CONNECTION_MARKER = 'conn:';

  /**
   * Finds the open/pending conversation of a customer with a company in any
   * conversation type, so a task handed off from the AI keeps receiving messages.
   */
  public function findOpenConversationTask(People $provider, People $client, array $types = self::CONVERSATION_TYPES): ?Task
  {
    $statuses = [];
    foreach ($types as $type) {
      $statuses[] = $this->statusService->discoveryStatus('open', 'open', $type);
      $statuses[] = $this->statusService->discoveryStatus('pending', 'pending', $type);
    }

    return $this->manager->getRepository(Task::class)->findOneBy([
      'taskStatus' => $statuses,
      'provider' => $provider,
      'client' => $client,
      'type' => $types,
    ], ['id' => 'DESC']);
  }

  /**
   * Customer-channel reply for the task. Rules:
   * - only public interactions leave the system (private notes and the internal /ai chat never do);
   * - destinations come from announce (phones for WhatsApp, addresses for e-mail);
   * - the reply leaves through the connection the conversation came in ("conn:{id}"), else
   *   one of the company's connections (task type, then any), else the main domain.
   */
  public function notifyClient(TaskInteration $taskInteration): TaskInteration
  {
    if (!$this->notify) return $taskInteration;
    if ($taskInteration->getVisibility() !== 'public') return $taskInteration;

    $task = $taskInteration->getTask();
    $author = $taskInteration->getRegisteredBy();
    // The customer's own message is never echoed back to them.
    if ($author && $author->getId() === $task->getClient()?->getId()) return $taskInteration;

    // An employee answering the customer takes the conversation from the AI.
    if ($author && $author->getId() !== $task->getProvider()?->getId() && $task->getAiAgent()) {
      $task->setAiAgent(null);
      $this->manager->flush();
    }

    $announce = $task->getAnnounce(true);
    if (!array_is_list($announce)) return $taskInteration;

    $connection = $this->resolveNotifyConnection($task, $announce);
    if (!$connection) return $taskInteration;

    if (method_exists($connection, 'isEmail') && $connection->isEmail()) {
      foreach ($this->emailDestinations($announce) as $to) {
        $this->integrationService->addIntegration((string) json_encode([
          'connection_id' => $connection->getId(),
          'task_id' => $task->getId(),
          'to' => $to,
          'subject' => 'Re: ' . $task->getName(),
          'message' => $taskInteration->getBody(),
        ], JSON_UNESCAPED_UNICODE), 'EmailOutbound', null, null, $task->getProvider());
      }
      return $taskInteration;
    }

    $phone = $connection->getPhone();
    if (!$phone) return $taskInteration;
    $origin = $phone->getDdi() . $phone->getDdd() . $phone->getPhone();

    // task_id lets the sender report failures (e.g. outside Meta's 24h window) back to the team;
    // file_id sends the attachment (the official API uploads it as media).
    $file = $taskInteration->getFile();
    foreach ($this->phoneDestinations($announce) as $destination) {
      if ($origin == $destination) continue;
      $this->integrationService->addIntegration((string) json_encode([
        "action" => "sendMessage",
        "origin" => $origin,
        "destination" => $destination,
        "message" => $taskInteration->getBody(),
        "task_id" => $task->getId(),
        "file_id" => $file?->getId(),
      ]), 'WhatsApp', null, null, $task->getProvider());
    }

    return $taskInteration;
  }

  private function resolveNotifyConnection(Task $task, array $announce): ?Connection
  {
    $provider = $task->getProvider();
    foreach ($announce as $value) {
      if (is_string($value) && str_starts_with($value, self::CONNECTION_MARKER)) {
        $connection = $this->manager->getRepository(Connection::class)->find((int) substr($value, strlen(self::CONNECTION_MARKER)));
        if ($connection && $connection->getPeople()?->getId() === $provider?->getId()) return $connection;
      }
    }

    $wantsEmail = $this->phoneDestinations($announce) === [] && $this->emailDestinations($announce) !== [];
    foreach ($this->manager->getRepository(Connection::class)->findBy(['people' => $provider]) as $connection) {
      $isEmail = method_exists($connection, 'isEmail') && $connection->isEmail();
      if ($isEmail === $wantsEmail && $connection->getType() === $task->getType()) return $connection;
    }
    foreach ($this->manager->getRepository(Connection::class)->findBy(['people' => $provider]) as $connection) {
      $isEmail = method_exists($connection, 'isEmail') && $connection->isEmail();
      if ($isEmail === $wantsEmail) return $connection;
    }

    return $wantsEmail ? null : $this->searchConnectionFromPeople($provider, (string) $task->getType(), true);
  }

  /** @return list<string> */
  public function phoneDestinations(array $announce): array
  {
    $out = [];
    foreach ($announce as $value) {
      if (is_scalar($value) && preg_match('/^\d{10,15}$/', (string) $value)) $out[] = (string) $value;
    }
    return array_values(array_unique($out));
  }

  /** @return list<string> */
  public function emailDestinations(array $announce): array
  {
    $out = [];
    foreach ($announce as $value) {
      if (is_string($value) && filter_var($value, FILTER_VALIDATE_EMAIL)) $out[] = strtolower($value);
    }
    return array_values(array_unique($out));
  }

  /**
   * API reads (FilterCollectionByExtension): private interactions (internal notes, internal /ai chats)
   * are visible only to their author and to employees of the task's provider. A customer reading
   * their own ticket never sees the provider's internal notes. Contexts without a user (workers) are untouched.
   */
  public function securityFilter(QueryBuilder $queryBuilder, $resourceClass = null, $applyTo = null, $rootAlias = null): void
  {
    $me = $this->peopleService->getMyPeople();
    if (!$me) return;
    $companies = $this->peopleService->getMyCompanies();

    $queryBuilder
      ->leftJoin(sprintf('%s.task', $rootAlias), 'tiSecurityTask')
      ->andWhere(sprintf(
        "%s.visibility <> 'private' OR %s.registeredBy = :tiSecurityMe OR tiSecurityTask.provider IN (:tiSecurityCompanies)",
        $rootAlias,
        $rootAlias
      ))
      // Only interactions of tasks the user can see (same rule as TaskService::securityFilter).
      ->andWhere(sprintf(
        'tiSecurityTask.provider IN (:tiSecurityCompanies) OR tiSecurityTask.client IN (:tiSecurityCompanies)'
          . ' OR tiSecurityTask.taskFor IN (:tiSecurityCompanies) OR tiSecurityTask.client = :tiSecurityMe'
          . ' OR tiSecurityTask.taskFor = :tiSecurityMe OR tiSecurityTask.registeredBy = :tiSecurityMe OR %s.registeredBy = :tiSecurityMe',
        $rootAlias
      ))
      ->setParameter('tiSecurityMe', $me)
      ->setParameter('tiSecurityCompanies', $companies ?: [0]);
  }

  public function prePersist(TaskInteration $taskInteration): TaskInteration
  {
    if (!$taskInteration->getRegisteredBy())
      $taskInteration->setRegisteredBy($this->security->getToken()->getUser()->getPeople());

    // Requests (relationship/support): on the company side only the context's responders (or the
    // company itself, i.e. its AI) may write; the customer side always may. Background jobs pass.
    $task = $taskInteration->getTask();
    $author = $taskInteration->getRegisteredBy();
    $user = $this->security->getToken()?->getUser();
    if ($this->ticketRules && is_object($user) && TicketRulesService::isTicket($task) && $author
      && !$this->ticketRules->canReply($task, $author)) {
      throw new \Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException('Você não pode responder este chamado.');
    }

    return  $taskInteration;
  }

  public function postPersist(TaskInteration $taskInteration): TaskInteration
  {
    return $this->notifyClient($taskInteration);
  }
}
