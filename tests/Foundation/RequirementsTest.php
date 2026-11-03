<?php

declare(strict_types=1);
namespace Cor\Mgmt\Tests\Foundation;
use Cor\Mgmt\Application\Requirements;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class RequirementsTest extends TestCase {
    #[DataProvider('versions')]
    public function testRequirements(string $php, string $wp, string $db, array $extensions, bool $multisite, array $expected): void {
        self::assertSame($expected, Requirements::errors($php, $wp, $db, $extensions, $multisite));
    }
    public static function versions(): array {
        return [
            'minimum'=>['8.2.0','6.3','10.11.0-MariaDB',['mbstring'],false,[]],
            'latest'=>['8.5.12','7.1.4','11.4.13-MariaDB-deb12',['mbstring'],false,[]],
            'mysql'=>['8.4','7.1','8.0.40',['mbstring'],false,[]],
            'mysql newer'=>['8.4','7.1','8.4.0',['mbstring'],false,[]],
            'maria compatibility prefix'=>['8.2','6.3','5.5.5-11.4.13-MariaDB',['mbstring'],false,[]],
            'old php'=>['8.1.99','6.3','11.4.13-MariaDB',['mbstring'],false,['php_version']],
            'old wp'=>['8.2','6.2.9','11.4.13-MariaDB',['mbstring'],false,['wordpress_version']],
            'old maria'=>['8.2','6.3','10.10.9-MariaDB',['mbstring'],false,['database_version']],
            'mysql expression default floor'=>['8.2','6.3','8.0.12',['mbstring'],false,['database_version']],
            'old mysql'=>['8.2','6.3','5.7.44',['mbstring'],false,['database_version']],
            'unknown database'=>['8.2','6.3','unknown',['mbstring'],false,['database_version']],
            'missing extension'=>['8.2','6.3','11.4.13-MariaDB',[],false,['mbstring']],
            'multisite deferred'=>['8.2','6.3','11.4.13-MariaDB',['mbstring'],true,['multisite']],
            'all failures'=>['7.4','5.9','5.7',[],true,['php_version','wordpress_version','mbstring','database_version','multisite']],
        ];
    }
}
