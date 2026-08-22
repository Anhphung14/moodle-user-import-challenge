<?php

declare(strict_types=1);

namespace Application\Tests\Unit\Service;

use Application\Domain\UserRecord;
use Application\Service\UserNormalizer;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class UserNormalizerTest extends TestCase
{
    #[DataProvider('recordsToNormalize')]
    public function testItNormalizesUserFields(
        UserRecord $input,
        UserRecord $expected,
    ): void {
        self::assertEquals($expected, (new UserNormalizer())->normalize($input));
    }

    /**
     * @return iterable<string, array{UserRecord, UserRecord}>
     */
    public static function recordsToNormalize(): iterable
    {
        yield 'lowercase names and uppercase email' => [
            new UserRecord(2, 'john', 'smith', 'JOHN@EXAMPLE.COM'),
            new UserRecord(2, 'John', 'Smith', 'john@example.com'),
        ];

        yield 'uppercase names' => [
            new UserRecord(5, 'JANE', 'DOE', 'JANE@EXAMPLE.COM'),
            new UserRecord(5, 'Jane', 'Doe', 'jane@example.com'),
        ];

        yield 'surrounding whitespace' => [
            new UserRecord(8, '  mary jane  ', "  o'connor  ", '  Mary@Example.Com  '),
            new UserRecord(8, 'Mary Jane', "O'Connor", 'mary@example.com'),
        ];

        yield 'unicode names' => [
            new UserRecord(11, '  nGUYỄN  ', '  thị ÁNH  ', '  USER@EXAMPLE.COM  '),
            new UserRecord(11, 'Nguyễn', 'Thị Ánh', 'user@example.com'),
        ];

        yield 'hyphenated names' => [
            new UserRecord(12, 'ANNE-MARIE', 'SMITH-JONES', 'ANNE@EXAMPLE.COM'),
            new UserRecord(12, 'Anne-Marie', 'Smith-Jones', 'anne@example.com'),
        ];

        yield 'empty values remain empty for later validation' => [
            new UserRecord(14, '  ', '', '  '),
            new UserRecord(14, '', '', ''),
        ];
    }

    public function testItDoesNotMutateTheOriginalRecord(): void
    {
        $original = new UserRecord(2, 'john', 'smith', 'JOHN@EXAMPLE.COM');

        $normalised = (new UserNormalizer())->normalize($original);

        self::assertNotSame($original, $normalised);
        self::assertSame('john', $original->name);
        self::assertSame('JOHN@EXAMPLE.COM', $original->email);
    }
}
