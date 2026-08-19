<?php

namespace ControleOnline\Command;

use ControleOnline\Service\DatabaseSwitchService;
use ControleOnline\Service\LoggerService;
use ControleOnline\Service\OverdueOpportunityMaintenanceService;
use ControleOnline\Service\SkyNetService;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Lock\LockFactory;

#[AsCommand(
    name: 'app:crm:open-overdue-opportunities',
    description: 'Move oportunidades pendentes para aberto quando a data de retorno ja passou.',
)]
class OpenOverdueOpportunitiesCommand extends DefaultCommand
{
    public function __construct(
        LockFactory $lockFactory,
        DatabaseSwitchService $databaseSwitchService,
        LoggerService $loggerService,
        SkyNetService $skyNetService,
        private OverdueOpportunityMaintenanceService $maintenanceService,
    ) {
        $this->lockFactory = $lockFactory;
        $this->databaseSwitchService = $databaseSwitchService;
        $this->loggerService = $loggerService;
        $this->skyNetService = $skyNetService;

        parent::__construct('app:crm:open-overdue-opportunities');
    }

    protected function configure(): void
    {
        $this->setDescription(
            'Move oportunidades do CRM de pendente para aberto quando a data de retorno expira.'
        );
    }

    protected function runCommand(): int
    {
        $summary = $this->maintenanceService->openPendingOpportunities();

        $this->addLog(
            sprintf(
                '[app:crm:open-overdue-opportunities] Executado em %s | atualizadas=%d',
                (string) ($summary['referenceTime'] ?? ''),
                (int) ($summary['updatedTotal'] ?? 0),
            ),
            0,
            'maintenance'
        );

        return Command::SUCCESS;
    }
}
