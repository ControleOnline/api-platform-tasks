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
    $task = $this->discoveryOpenTask($connection->getPeople(), $registredBy, $type, $number);

    $this->automationMessagesService->receiveMessage($message, $connection,$task);

    return $this->addInteration($registredBy, $message, $task, $type, 'public');

  }

  public function addInteration(People $registredBy, MessageInterface $message, Task $task, string $type, ?string $visibility = 'private')
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

  public function discoveryOpenTask(People $provider, People $registredBy, string $type, ?string $announce = null): Task
  {

    $openStatus = $this->statusService->discoveryStatus('open', 'open', $type);
    $pendingStatus = $this->statusService->discoveryStatus('pending', 'pending', $type);

    $task = $this->manager->getRepository(Task::class)->findOneBy([
      'taskStatus' => [$openStatus, $pendingStatus],
      'provider' => $provider,
      'registeredBy' => $registredBy,
      'type' => $type
    ]);

    if (!$task) {
      $task = new Task();
      $task->setRegisteredBy($registredBy);
      $task->setProvider($provider);
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

  public function notifyClient(TaskInteration $taskInteration): TaskInteration
  {
    if (!$this->notify) return $taskInteration;
    $task = $taskInteration->getTask();
    $connection = $this->searchConnectionFromPeople($task->getProvider(), $task->getType(), true);
    if (!$connection) return $taskInteration;

    $phone = $connection->getPhone();
    $origin = $phone->getDdi() . $phone->getDdd() . $phone->getPhone();

    foreach ($task->getAnnounce(true) as $destination) {
      if ($origin != $destination) {
        $message = json_encode([
          "action" => "sendMessage",
          "origin" => $origin,
          "destination" => $destination,
          "message" => $taskInteration->getBody(),
          //"file" => $taskInteration->getFile()
        ]);
        $this->integrationService->addIntegration($message, 'WhatsApp', null, null, $task->getProvider());
      }
    }

    return  $taskInteration;
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
