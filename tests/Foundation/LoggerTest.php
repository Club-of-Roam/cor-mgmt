<?php

declare(strict_types=1);
namespace Cor\Mgmt\Tests\Foundation;
use Cor\Mgmt\Infrastructure\WordPress\WordPressLogger;
use PHPUnit\Framework\Attributes\PreserveGlobalState;
use PHPUnit\Framework\Attributes\RunInSeparateProcess;
use PHPUnit\Framework\TestCase;

final class LoggerTest extends TestCase {
    #[RunInSeparateProcess]
    #[PreserveGlobalState(false)]
    public function testLoggerRetainsCodesButExcludesMessagesTracesAndAnonymousClassPaths(): void {
        define('WP_DEBUG_LOG',true);
        $path = tempnam(sys_get_temp_dir(),'cor-mgmt-log-test-');
        $previous = ini_get('error_log');
        ini_set('error_log',$path);
        try {
            foreach ([new \RuntimeException('synthetic-secret@example.invalid'),new class('synthetic-secret@example.invalid') extends \RuntimeException {}] as $error) {
                (new WordPressLogger())->error('provider_failed',$error);
            }
            $contents = file_get_contents($path);
            self::assertCount(2,file($path));
            self::assertStringContainsString('cor-mgmt: provider_failed',$contents);
            self::assertStringContainsString('[RuntimeException]',$contents);
            self::assertStringNotContainsString('synthetic-secret',$contents);
            self::assertStringNotContainsString(__FILE__,$contents);
            self::assertStringNotContainsString('@anonymous',$contents);
            self::assertStringNotContainsString('Stack trace',$contents);
        } finally { ini_set('error_log',$previous); unlink($path); }
    }
}
