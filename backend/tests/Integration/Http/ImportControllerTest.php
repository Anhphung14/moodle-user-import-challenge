<?php

declare(strict_types=1);

namespace Application\Tests\Integration\Http;

use Application\Csv\CsvUserParser;
use Application\Database\ConnectionFactory;
use Application\Database\SchemaManager;
use Application\Domain\ImportResult;
use Application\Http\HttpApplication;
use Application\Http\ImportController;
use Application\Http\Router;
use Application\Http\UploadedFile;
use Application\Repository\PostgresUserRepository;
use Application\Service\DatabaseDuplicateEmailDetector;
use Application\Service\DuplicateEmailDetector;
use Application\Service\UserImportService;
use Application\Service\UserNormalizer;
use Application\Service\UserValidator;
use PDO;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\TestCase;

#[Group('integration')]
final class ImportControllerTest extends TestCase
{
    private PDO $connection;

    private string $csvPath;

    protected function setUp(): void
    {
        if (!isset($_ENV['DATABASE_URL']) || $_ENV['DATABASE_URL'] === '') {
            self::markTestSkipped('DATABASE_URL is not configured.');
        }

        $this->connection = (new ConnectionFactory())->create();
        $this->connection->beginTransaction();
        (new SchemaManager($this->connection))->rebuild();
        $this->connection->exec(<<<'SQL'
            INSERT INTO users (name, surname, email)
            VALUES ('Existing', 'User', 'existing@example.com')
            SQL);
        $path = tempnam(sys_get_temp_dir(), 'moodle-api-import-');
        self::assertNotFalse($path);
        $this->csvPath = $path;
    }

    protected function tearDown(): void
    {
        if (isset($this->csvPath) && is_file($this->csvPath)) {
            unlink($this->csvPath);
        }

        if (isset($this->connection) && $this->connection->inTransaction()) {
            $this->connection->rollBack();
        }
    }

    public function testImportEndpointRevalidatesCsvAndPersistsOnlyValidUsers(): void
    {
        self::assertNotFalse(file_put_contents($this->csvPath, <<<'CSV'
            name,surname,email
            new,user,NEW@EXAMPLE.COM
            existing,user,existing@example.com
            invalid,user,not-an-email
            CSV));
        $repository = new PostgresUserRepository($this->connection);
        $service = new UserImportService(
            new CsvUserParser(),
            new UserNormalizer(),
            new UserValidator(),
            new DuplicateEmailDetector(),
            new DatabaseDuplicateEmailDetector($repository),
            $repository,
            $this->connection,
        );
        $router = new Router();
        $controller = new ImportController(
            fn(string $path): ImportResult => $service->import($path),
        );
        $router->add('POST', '/api/imports', $controller(...));
        $size = filesize($this->csvPath);
        self::assertIsInt($size);

        $response = (new HttpApplication($router))->handle('POST', '/api/imports', [
            'file' => new UploadedFile($this->csvPath, 'users.csv', $size, UPLOAD_ERR_OK),
        ]);

        self::assertSame(200, $response->status);
        self::assertSame(3, $response->body['data']['total']);
        self::assertSame(1, $response->body['data']['imported']);
        self::assertSame(2, $response->body['data']['rejected']);
        self::assertSame(2, (int) $this->connection->query('SELECT COUNT(*) FROM users')->fetchColumn());
        self::assertSame(
            1,
            (int) $this->connection
                ->query("SELECT COUNT(*) FROM users WHERE email = 'new@example.com'")
                ->fetchColumn(),
        );
    }
}
