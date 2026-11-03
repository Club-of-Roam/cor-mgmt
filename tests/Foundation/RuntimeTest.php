<?php

declare(strict_types=1);
namespace Cor\Mgmt\Tests\Foundation;
use Cor\Mgmt\Application\Contracts\Clock;
use Cor\Mgmt\Application\Runtime;
use Cor\Mgmt\Infrastructure\SecureRandom;
use Cor\Mgmt\Infrastructure\SystemClock;
use Cor\Mgmt\WordPress\Services;
use PHPUnit\Framework\TestCase;

final class RuntimeTest extends TestCase {
    protected function tearDown(): void { Services::replace(null); }
    public function testClockCanBeReplacedWithoutWordPress(): void {
        $original = Services::get();
        $clock = new class implements Clock { public function now(): int { return 1700000000; } };
        $runtime = new Runtime($clock, $original->random, $original->http, $original->mail, $original->logger);
        Services::replace($runtime);
        self::assertSame(1700000000, Services::get()->clock->now());
        self::assertSame($runtime, Services::get());
        Services::replace(null);
        self::assertNotSame($runtime, Services::get());
    }
    public function testSecureRandomProducesRequestedBytesAndRange(): void {
        $random = new SecureRandom();
        self::assertSame(32, strlen($random->bytes(32)));
        self::assertSame(7, $random->integer(7, 7));
        $value = $random->integer(10, 20);
        self::assertGreaterThanOrEqual(10, $value);
        self::assertLessThanOrEqual(20, $value);
    }
    public function testInvalidRandomRangeFails(): void {
        $this->expectException(\ValueError::class);
        (new SecureRandom())->integer(20, 10);
    }
    public function testSystemClockReturnsUnixSeconds(): void {
        $before = time();
        $now = (new SystemClock())->now();
        self::assertGreaterThanOrEqual($before, $now);
        self::assertLessThanOrEqual(time(), $now);
    }
}
