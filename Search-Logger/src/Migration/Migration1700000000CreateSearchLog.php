<?php declare(strict_types=1);

namespace Swag\SearchLogger\Migration;

use Doctrine\DBAL\Connection;
use Shopware\Core\Framework\Migration\MigrationStep;

class Migration1700000000CreateSearchLog extends MigrationStep
{
    public function getCreationTimestamp(): int
    {
        return 1700000000;
    }

    public function update(Connection $connection): void
    {
        // Prüfen, ob die Tabelle bereits existiert
        $schemaManager = $connection->createSchemaManager();
        if ($schemaManager->tablesExist(['swag_search_log'])) {
            return;
        }

        $connection->executeStatement('
            CREATE TABLE `swag_search_log` (
                `id` BINARY(16) NOT NULL,
                `term` VARCHAR(255) NOT NULL,
                `result_count` INT NOT NULL DEFAULT 0,
                `sales_channel_id` BINARY(16) NULL,
                `created_at` DATETIME(3) NOT NULL,
                `updated_at` DATETIME(3) NULL,
                PRIMARY KEY (`id`),
                KEY `idx.term` (`term`)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
        ');
    }

    public function updateDestructive(Connection $connection): void
    {
        $connection->executeStatement('DROP TABLE IF EXISTS `swag_search_log`');
    }
}
