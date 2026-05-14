<?php

namespace ControleOnline\Service;

class OpenOverdueOpportunitiesMaintenanceRoutine implements MaintenanceRoutineHandlerInterface
{
    public const ROUTINE_KEY = 'open_overdue_opportunities';

    public function __construct(
        private OverdueOpportunityMaintenanceService $maintenanceService,
    ) {}

    public function getDefinition(): array
    {
        return [
            'key' => self::ROUTINE_KEY,
            'title' => 'Oportunidades vencidas para aberto',
            'description' => 'Move oportunidades de CRM de pendente para aberto quando a data de retorno ja passou.',
            'defaultEnabled' => true,
            'defaultCronExpression' => '* * * * *',
        ];
    }

    public function run(): array
    {
        return [
            'key' => self::ROUTINE_KEY,
            'status' => 'success',
            'summary' => $this->maintenanceService->openPendingOpportunities(),
        ];
    }
}
