<?php

declare(strict_types=1);

namespace Application\Tests\Unit\Database;

use Application\Database\Exception\SchemaManagementException;
use Application\Database\SchemaManager;
use PDO;
use PHPUnit\Framework\TestCase;

final class SchemaManagerTest extends TestCase
{
    public function testCreateRejectsUnreadableSchemaFile(): void
    {
        $connection = $this->createStub(PDO::class);
        $manager = new SchemaManager($connection, __DIR__ . '/missing-schema.sql');

        $this->expectException(SchemaManagementException::class);
        $this->expectExceptionMessage('The users schema file is not readable.');

        $manager->create();
    }
}
