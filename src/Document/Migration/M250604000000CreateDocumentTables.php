<?php

declare(strict_types=1);

namespace App\Document\Migration;

use App\Document\Infrastructure\DocumentSchemaSql;
use Yiisoft\Db\Migration\MigrationBuilder;
use Yiisoft\Db\Migration\RevertibleMigrationInterface;

/**
 * Creates and reverts the document workflow database tables.
 */
final class M250604000000CreateDocumentTables implements RevertibleMigrationInterface
{
    /**
     * Applies document workflow schema changes.
     *
     * @param MigrationBuilder $b Migration builder.
     */
    public function up(MigrationBuilder $b): void
    {
        $b->execute(DocumentSchemaSql::CREATE_DOCUMENTS);
        $b->execute(DocumentSchemaSql::CREATE_PROCESSING_EVENTS);
        $b->execute(DocumentSchemaSql::CREATE_DOCUMENTS_STATUS_INDEX);
        $b->execute(DocumentSchemaSql::CREATE_PROCESSING_EVENTS_DOCUMENT_INDEX);
    }

    /**
     * Reverts document workflow schema changes.
     *
     * @param MigrationBuilder $b Migration builder.
     */
    public function down(MigrationBuilder $b): void
    {
        $b->execute(DocumentSchemaSql::DROP_PROCESSING_EVENTS_DOCUMENT_INDEX);
        $b->execute(DocumentSchemaSql::DROP_DOCUMENTS_STATUS_INDEX);
        $b->execute(DocumentSchemaSql::DROP_PROCESSING_EVENTS);
        $b->execute(DocumentSchemaSql::DROP_DOCUMENTS);
    }
}
