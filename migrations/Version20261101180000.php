<?php

declare(strict_types=1);

namespace DoctrineMigrations\Tasks;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Align client_id nullable for AI chats (type=ia). Status seed already in Version20261101170000.
 */
final class Version20261101180000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Make tasks.client_id nullable for type=ia chats';
    }

    public function up(Schema $schema): void
    {
        $this->addSql("ALTER TABLE tasks MODIFY COLUMN client_id INT NULL DEFAULT NULL");
    }

    public function down(Schema $schema): void
    {
        // no-op: cannot force NOT NULL with existing nulls
    }
}
