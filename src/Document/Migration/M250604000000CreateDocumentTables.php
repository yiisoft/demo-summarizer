<?php

declare(strict_types=1);

namespace App\Document\Migration;

use App\Document\Infrastructure\DocumentSchemaSql;
use Yiisoft\Db\Migration\MigrationBuilder;
use Yiisoft\Db\Migration\RevertibleMigrationInterface;

final class M250604000000CreateDocumentTables implements RevertibleMigrationInterface
{
    public function up(MigrationBuilder $b): void
    {
        $b->execute(DocumentSchemaSql::CREATE_DOCUMENTS);
        $b->execute(DocumentSchemaSql::CREATE_PROCESSING_EVENTS);
        $b->execute(DocumentSchemaSql::CREATE_DOCUMENTS_STATUS_INDEX);
        $b->execute(DocumentSchemaSql::CREATE_PROCESSING_EVENTS_DOCUMENT_INDEX);
    }

    public function down(MigrationBuilder $b): void
    {
        $b->execute(DocumentSchemaSql::DROP_PROCESSING_EVENTS_DOCUMENT_INDEX);
        $b->execute(DocumentSchemaSql::DROP_DOCUMENTS_STATUS_INDEX);
        $b->execute(DocumentSchemaSql::DROP_PROCESSING_EVENTS);
        $b->execute(DocumentSchemaSql::DROP_DOCUMENTS);
    }
}
