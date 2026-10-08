<?php declare(strict_types=1);

namespace Swag\SearchLogger\Migration;

use Doctrine\DBAL\Connection;
use Shopware\Core\Framework\Migration\MigrationStep;

class Migration1700000001ReplaceTermIndex extends MigrationStep
{
    public function getCreationTimestamp(): int
    {
        return 1700000001;
    }

    public function update(Connection $connection): void
    {
        // Alten Index löschen, falls vorhanden
        $indexes = $connection->fetchAllAssociative('SHOW INDEX FROM swag_search_log');
        foreach ($indexes as $index) {
            if (($index['Key_name'] ?? '') === 'idx.term') {
                $connection->executeStatement('DROP INDEX idx.term ON swag_search_log');
                break;
            }
        }

        // Neuen zusammengesetzten Index anlegen, falls nicht vorhanden
        $indexes = $connection->fetchAllAssociative('SHOW INDEX FROM swag_search_log');
        foreach ($indexes as $index) {
            if (($index['Key_name'] ?? '') === 'idx_search_log_lookup') {
                return;
            }
        }

        $connection->executeStatement(
            'CREATE INDEX idx_search_log_lookup ON swag_search_log (term, sales_channel_id, created_at)'
        );
    }

    public function updateDestructive(Connection $connection): void
    {
        // Beim Rückgängigmachen den neuen Index entfernen und den alten wiederherstellen
        $indexes = $connection->fetchAllAssociative('SHOW INDEX FROM swag_search_log');
        foreach ($indexes as $index) {
            if (($index['Key_name'] ?? '') === 'idx_search_log_lookup') {
                $connection->executeStatement('DROP INDEX idx_search_log_lookup ON swag_search_log');
                break;
            }
        }

        $connection->executeStatement('CREATE INDEX idx.term ON swag_search_log (term)');
    }
}
