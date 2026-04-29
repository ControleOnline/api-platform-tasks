<?php

namespace ControleOnline\Tests\Service;

use ControleOnline\Service\OpenOverdueOpportunitiesMaintenanceRoutine;
use ControleOnline\Service\OverdueOpportunityMaintenanceService;
use PHPUnit\Framework\TestCase;

class OpenOverdueOpportunitiesMaintenanceRoutineTest extends TestCase
{
    public function testExposesDefinitionForSchedulerCatalog(): void
    {
        $routine = new OpenOverdueOpportunitiesMaintenanceRoutine(
            $this->createMock(OverdueOpportunityMaintenanceService::class),
        );

        self::assertSame(
            [
                'key' => OpenOverdueOpportunitiesMaintenanceRoutine::ROUTINE_KEY,
                'title' => 'Oportunidades vencidas para aberto',
                'description' => 'Move oportunidades de CRM de pendente para aberto quando a data de retorno ja passou.',
                'defaultEnabled' => true,
                'defaultCronExpression' => '* * * * *',
            ],
            $routine->getDefinition(),
        );
    }

    public function testRunsMaintenanceServiceAndReturnsItsSummary(): void
    {
        $summary = [
            'referenceTime' => '2026-04-29T00:00:00+00:00',
            'updatedTotal' => 2,
            'taskIds' => [10, 11],
        ];

        $service = $this->createMock(OverdueOpportunityMaintenanceService::class);
        $service
            ->expects(self::once())
            ->method('openPendingOpportunities')
            ->willReturn($summary);

        $routine = new OpenOverdueOpportunitiesMaintenanceRoutine($service);

        self::assertSame(
            [
                'key' => OpenOverdueOpportunitiesMaintenanceRoutine::ROUTINE_KEY,
                'status' => 'success',
                'summary' => $summary,
            ],
            $routine->run(),
        );
    }
}
