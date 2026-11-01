<?php

namespace ControleOnline\Tests\Service;

use ControleOnline\Entity\Task;
use ControleOnline\Repository\TaskRepository;
use ControleOnline\Service\OverdueOpportunityMaintenanceService;
use ControleOnline\Service\StatusService;
use Doctrine\ORM\EntityManagerInterface;
use PHPUnit\Framework\TestCase;

class OverdueOpportunityMaintenanceServiceTest extends TestCase
{
    public function testOpensOnlyOverduePendingOpportunitiesAndFlushesOnce(): void
    {
        $referenceTime = new \DateTimeImmutable('2026-04-29 00:00:00');
        $pendingStatus = new \stdClass();
        $openStatus = new \stdClass();

        $firstTask = $this->createTask(10, $pendingStatus);
        $secondTask = $this->createTask(11, $pendingStatus);

        $repository = $this->createMock(TaskRepository::class);
        $repository
            ->expects(self::once())
            ->method('findOverduePendingTasksByTypeAndStatus')
            ->with('relationship', $pendingStatus, $referenceTime)
            ->willReturn([$firstTask, $secondTask]);

        $statusService = $this->createMock(StatusService::class);
        $statusService
            ->expects(self::exactly(2))
            ->method('discoveryStatus')
            ->willReturnMap([
                ['pending', 'pending', 'relationship', $pendingStatus],
                ['open', 'open', 'relationship', $openStatus],
            ]);

        $manager = $this->createMock(EntityManagerInterface::class);
        $manager->expects(self::once())->method('flush');

        $service = new OverdueOpportunityMaintenanceService(
            $repository,
            $statusService,
            $manager,
        );

        $summary = $service->openPendingOpportunities($referenceTime);

        self::assertSame($openStatus, $firstTask->getTaskStatus());
        self::assertSame($openStatus, $secondTask->getTaskStatus());
        self::assertSame(
            $referenceTime->format(\DateTimeInterface::ATOM),
            $summary['referenceTime'],
        );
        self::assertSame(2, $summary['updatedTotal']);
        self::assertSame([10, 11], $summary['taskIds']);
    }

    public function testSkipsFlushWhenNoOpportunityNeedsTransition(): void
    {
        $referenceTime = new \DateTimeImmutable('2026-04-29 00:00:00');
        $pendingStatus = new \stdClass();
        $openStatus = new \stdClass();

        $repository = $this->createMock(TaskRepository::class);
        $repository
            ->expects(self::once())
            ->method('findOverduePendingTasksByTypeAndStatus')
            ->with('relationship', $pendingStatus, $referenceTime)
            ->willReturn([]);

        $statusService = $this->createMock(StatusService::class);
        $statusService
            ->expects(self::exactly(2))
            ->method('discoveryStatus')
            ->willReturnMap([
                ['pending', 'pending', 'relationship', $pendingStatus],
                ['open', 'open', 'relationship', $openStatus],
            ]);

        $manager = $this->createMock(EntityManagerInterface::class);
        $manager->expects(self::never())->method('flush');

        $service = new OverdueOpportunityMaintenanceService(
            $repository,
            $statusService,
            $manager,
        );

        self::assertSame(
            [
                'referenceTime' => $referenceTime->format(\DateTimeInterface::ATOM),
                'updatedTotal' => 0,
                'taskIds' => [],
            ],
            $service->openPendingOpportunities($referenceTime),
        );
    }

    private function createTask(int $id, object $status): Task
    {
        $task = new Task();
        $task->setTaskStatus($status);

        $reflectionProperty = new \ReflectionProperty(Task::class, 'id');
        $reflectionProperty->setAccessible(true);
        $reflectionProperty->setValue($task, $id);

        return $task;
    }
}
