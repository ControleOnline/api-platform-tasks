<?php

namespace ControleOnline\Service;

use ControleOnline\Entity\Connection;
use ControleOnline\Entity\People;
use ControleOnline\Entity\Task;
use ControleOnline\Entity\TaskInteration;
use ControleOnline\Messages\MessageInterface;
use Doctrine\ORM\EntityManagerInterface;
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
   * Inbound customer message for an AI front-door connection (connection.type = ia).
   * Reuses the customer's open conversation in ia|relationship|support (a task handed
   * off to a human keeps the thread); otherwise opens a new "ia" task.
   */
  public function addClientConversationInteration(MessageInterface $message, Connection $connection, string $name = ''): TaskInteration
  {
    $this->notify = false;
    $number = preg_replace('/\D/', '', $message->getOriginNumber());
    $phone = [
      'ddi' => substr($number, 0, 2),
      'ddd' => substr($number, 2, 2),
      'phone' => substr($number, 4)
    ];
    $client = $this->peopleService->discoveryPeople(null, null, $phone, $name, null);
    $provider = $connection->getPeople();

    $task = $this->findOpenConversationTask($provider, $client)
      ?? $this->discoveryOpenTask($provider, $client, $client, 'ia', $number);
    $task->addAnnounce($number);
    $task->addAnnounce(self::VIA_IA_MARKER);
    $task->setAlterDate(new \DateTime('now'));

    try {
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

  /** Non-numeric announce marker: the conversation entered through the company's "ia" connection. */
  public const VIA_IA_MARKER = 'via:ia';

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
   * - only numeric destinations are used (web /ai tasks keep a JSON object in announce);
   * - connection lookup: task type -> company "ia" connection (handoff keeps the same number) -> main domain.
   */
  public function notifyClient(TaskInteration $taskInteration): TaskInteration
  {
    if (!$this->notify) return $taskInteration;
    if ($taskInteration->getVisibility() !== 'public') return $taskInteration;

    $task = $taskInteration->getTask();
    $destinations = $this->extractPhoneDestinations($task->getAnnounce(true));
    if ($destinations === []) return $taskInteration;

    $connection = $this->resolveNotifyConnection($task);
    if (!$connection || !$connection->getPhone()) return $taskInteration;

    $phone = $connection->getPhone();
    $origin = $phone->getDdi() . $phone->getDdd() . $phone->getPhone();

    foreach ($destinations as $destination) {
      if ($origin == $destination) continue;
      $message = json_encode([
        "action" => "sendMessage",
        "origin" => $origin,
        "destination" => $destination,
        "message" => $taskInteration->getBody(),
      ]);
      $this->integrationService->addIntegration($message, 'WhatsApp', null, null, $task->getProvider());
    }

    return  $taskInteration;
  }

  private function resolveNotifyConnection(Task $task): ?Connection
  {
    $provider = $task->getProvider();
    $types = [(string) $task->getType()];
    // A conversation that started with the AI keeps its number after a handoff.
    if (in_array(self::VIA_IA_MARKER, $task->getAnnounce(true), true))
      array_unshift($types, 'ia');

    foreach (array_unique($types) as $type) {
      $connection = $this->searchConnectionFromPeople($provider, $type);
      if ($connection) return $connection;
    }

    return $this->searchConnectionFromPeople($provider, (string) $task->getType(), true);
  }

  /** @return list<string> */
  private function extractPhoneDestinations(array $announce): array
  {
    if (!array_is_list($announce)) return [];
    $out = [];
    foreach ($announce as $value) {
      $digits = preg_replace('/\D/', '', (string) $value);
      if (is_scalar($value) && strlen($digits) >= 10 && $digits === (string) $value)
        $out[] = $digits;
    }
    return array_values(array_unique($out));
  }

  public function prePersist(TaskInteration $taskInteration): TaskInteration
  {
    if (!$taskInteration->getRegisteredBy())
      $taskInteration->setRegisteredBy($this->security->getToken()->getUser()->getPeople());
    return  $taskInteration;
  }

  public function postPersist(TaskInteration $taskInteration): TaskInteration
  {
    return $this->notifyClient($taskInteration);
  }
}
