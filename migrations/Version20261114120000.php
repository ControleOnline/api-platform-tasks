<?php

declare(strict_types=1);

namespace DoctrineMigrations\Tasks;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/** AI agent assigned to a task (ia triage, relationship/CRM, support). NULL = humans only. */
final class Version20261114120000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'tasks.ai_agent: AI assigned to the conversation (provider key or auto)';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE `tasks` ADD COLUMN `ai_agent` VARCHAR(50) NULL DEFAULT NULL');
        $this->addSql('CREATE INDEX `idx_tasks_ai_agent` ON `tasks` (`ai_agent`)');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('DROP INDEX `idx_tasks_ai_agent` ON `tasks`');
        $this->addSql('ALTER TABLE `tasks` DROP COLUMN `ai_agent`');
    }
}
