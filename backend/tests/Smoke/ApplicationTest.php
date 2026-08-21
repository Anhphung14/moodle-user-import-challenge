<?php

declare(strict_types=1);

namespace Application\Tests\Smoke;

use Application\Application;
use PHPUnit\Framework\TestCase;

final class ApplicationTest extends TestCase
{
    public function testApplicationClassIsLoadedThroughComposer(): void
    {
        self::assertTrue(class_exists(Application::class));
    }
}
