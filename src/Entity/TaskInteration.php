<?php

namespace ControleOnline\Entity;

use Symfony\Component\Serializer\Attribute\Groups;

use ApiPlatform\Metadata\ApiResource;
use ApiPlatform\Metadata\ApiFilter;
use ApiPlatform\Metadata\Get;
use ApiPlatform\Metadata\GetCollection;
use ApiPlatform\Metadata\Post;
use ApiPlatform\Doctrine\Orm\Filter\SearchFilter;
use Doctrine\ORM\Mapping as ORM;
use ControleOnline\Entity\People;
use ControleOnline\Entity\Task;
use ControleOnline\Entity\File;
use ControleOnline\Repository\TaskInterationRepository;
use ControleOnline\Listener\LogListener;
use DateTime;
use DateTimeInterface;

#[ORM\Table(name: 'task_interations')]
#[ORM\EntityListeners([LogListener::class])]
#[ORM\Entity(repositoryClass: TaskInterationRepository::class)]
#[ApiResource(
    formats: ['jsonld', 'json', 'html', 'jsonhal', 'csv' => 'text/csv'],
    normalizationContext: ['groups' => ['task_interaction:read']],
    denormalizationContext: ['groups' => ['task_interaction:write']],
    security: "is_granted('ROLE_CLIENT')",
    operations: [
        new GetCollection(security: "is_granted('ROLE_CLIENT')"),
        new Get(security: "is_granted('ROLE_CLIENT')"),
        new Post(security: "is_granted('ROLE_ADMIN') or is_granted('ROLE_CLIENT')")
    ]
)]
#[ApiFilter(SearchFilter::class, properties: [
    'task' => 'exact',
    'task.id' => 'exact',
    'task.taskFor' => 'exact',
    'registeredBy' => 'exact',
    'type' => 'exact',
    'visibility' => 'exact',
    'read' => 'exact'
])]
class TaskInteration
{
    #[ORM\Column(type: 'integer', nullable: false)]
    #[ORM\Id]
    #[ORM\GeneratedValue(strategy: 'IDENTITY')]
    #[Groups(['task_interaction:read'])]
    private $id;

    #[ORM\Column(type: 'string', length: 50, nullable: false)]
    #[Groups(['task_interaction:read', 'task_interaction:write'])]
    private $type;

    #[ORM\Column(name: 'visibility', type: 'string', length: 50, nullable: false)]
    #[Groups(['task_interaction:read', 'task_interaction:write'])]
    private $visibility;

    #[ORM\Column(type: 'string', nullable: true)]
    #[Groups(['task_interaction:read', 'task_interaction:write'])]
    private $body;

    #[ORM\ManyToOne(targetEntity: People::class)]
    #[ORM\JoinColumn(name: 'registered_by_id', referencedColumnName: 'id', nullable: false)]
    #[Groups(['task_interaction:read', 'task_interaction:write'])]
    private $registeredBy;

    #[ORM\ManyToOne(targetEntity: Task::class)]
    #[ORM\JoinColumn(name: 'task_id', referencedColumnName: 'id', nullable: true)]
    #[Groups(['task_interaction:read', 'task_interaction:write'])]
    private $task;

    #[ORM\ManyToOne(targetEntity: File::class)]
    #[ORM\JoinColumn(name: 'file_id', referencedColumnName: 'id', nullable: true)]
    #[Groups(['task_interaction:read', 'task_interaction:write'])]
    private $file;

    #[ORM\Column(name: 'created_at', type: 'datetime', nullable: false, columnDefinition: 'DATETIME')]
    #[Groups(['task_interaction:read', 'task_interaction:write'])]
    private $createdAt;

    #[ORM\Column(name: 'read', type: 'integer', nullable: false)]
    #[Groups(['task_interaction:read', 'task_interaction:write'])]
    private $read;

    public function __construct()
    {
        $this->createdAt = new DateTime('now');
        $this->visibility = 'private';
        $this->read = false;
    }

    public function getId()
    {
        return $this->id;
    }

    public function getType()
    {
        return $this->type;
    }

    public function setType($type): self
    {
        $this->type = $type;
        return $this;
    }

    public function getBody()
    {
        return $this->body;
    }

    public function setBody($body)
    {
        $this->body = $body;
        return $this;
    }

    public function getRegisteredBy()
    {
        return $this->registeredBy;
    }

    public function setRegisteredBy($registeredBy): self
    {
        $this->registeredBy = $registeredBy;
        return $this;
    }

    public function getTask()
    {
        return $this->task;
    }

    public function setTask($task): self
    {
        $this->task = $task;
        return $this;
    }

    public function getFile()
    {
        return $this->file;
    }

    public function setFile($file): self
    {
        $this->file = $file;
        return $this;
    }

    public function getCreatedAt()
    {
        return $this->createdAt;
    }

    public function getVisibility()
    {
        return $this->visibility;
    }

    public function setVisibility($visibility): self
    {
        $this->visibility = $visibility;
        return $this;
    }

    public function getRead()
    {
        return $this->read;
    }

    public function setRead($read)
    {
        $this->read = $read;
        return $this;
    }
}
