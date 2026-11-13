<?php

declare(strict_types=1);

/**
 * This file is part of the common-doc/aggregate-renderer package.
 *
 * Copyright (c) 2026 Joey Smith <jsmith@webinertia.net>
 * and contributors.
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace CommonDocTest\Aggregate\Config;

use CommonDoc\Aggregate\Build\ReasonCode;
use CommonDoc\Aggregate\Config\SiteConfig;
use CommonDoc\Aggregate\Config\SiteConfigReader;
use CommonDoc\Aggregate\Exception\InvalidSiteConfigException;
use CommonDoc\Aggregate\Version\Strategy;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\CoversMethod;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

use function bin2hex;
use function file_put_contents;
use function glob;
use function is_dir;
use function json_encode;
use function mkdir;
use function random_bytes;
use function rmdir;
use function sprintf;
use function str_repeat;
use function sys_get_temp_dir;
use function unlink;

use const DIRECTORY_SEPARATOR;
use const JSON_THROW_ON_ERROR;

#[CoversClass(SiteConfigReader::class)]
#[CoversMethod(SiteConfigReader::class, 'read')]
final class SiteConfigReaderTest extends TestCase
{
    private string $directory;

    #[Test]
    public function normalisesAPathWrittenWithEitherSeparator(): void
    {
        $configuration                        = $this->configuration();
        $configuration['output']['directory'] = 'sub\\dir';

        static::assertStringEndsWith(
            'sub' . DIRECTORY_SEPARATOR . 'dir',
            $this->read($configuration)->outputDirectory,
        );
    }

    #[Test]
    public function readsAMinimalConfigurationAndSuppliesTheDefaults(): void
    {
        $config = $this->read($this->configuration());

        static::assertSame('common-doc', $config->organization);
        static::assertSame('COMMONDOC_TOKEN', $config->tokenEnvVar);
        static::assertSame('docs.example.com', $config->hostname);
        static::assertSame(Strategy::Ref, $config->strategy);
        static::assertSame([], $config->templateMap);
    }

    #[Test]
    public function readsATemplateMap(): void
    {
        static::assertTrue(mkdir($this->directory . DIRECTORY_SEPARATOR . 'templates'));
        file_put_contents(
            $this->directory . DIRECTORY_SEPARATOR . 'templates' . DIRECTORY_SEPARATOR . 'page.php',
            data: '<?php',
        );

        $configuration              = $this->configuration();
        $configuration['templates'] = ['map' => ['page' => 'templates/page.php']];

        static::assertSame(['page' => 'templates/page.php'], $this->read($configuration)->templateMap);
    }

    #[Test]
    public function readsATemplateMapOfMoreThanOneEntry(): void
    {
        static::assertTrue(mkdir($this->directory . DIRECTORY_SEPARATOR . 'templates'));

        foreach (['page.php', 'error.php'] as $template) {
            file_put_contents(
                $this->directory . DIRECTORY_SEPARATOR . 'templates' . DIRECTORY_SEPARATOR . $template,
                data: '<?php',
            );
        }

        $configuration              = $this->configuration();
        $configuration['templates'] = ['map' => [
            'page'  => 'templates/page.php',
            'error' => 'templates/error.php',
        ]];

        static::assertSame(
            ['page' => 'templates/page.php', 'error' => 'templates/error.php'],
            $this->read($configuration)->templateMap,
        );
    }

    #[Test]
    public function readsTheDeclaredStrategy(): void
    {
        $configuration             = $this->configuration();
        $configuration['versions'] = ['strategy' => 'tree'];

        static::assertSame(Strategy::Tree, $this->read($configuration)->strategy);
    }

    #[Test]
    public function rejectsADocumentNestedBeyondTheDecodingDepthAsInvalidJson(): void
    {
        $path = $this->directory . DIRECTORY_SEPARATOR . 'site.json';
        file_put_contents($path, data: str_repeat(
            string: '{"a":',
            times : 512,
        ) . '1'
            . str_repeat(
                string: '}',
                times : 512,
            ));

        $this->assertRejected('site.json', static fn(): SiteConfig => new SiteConfigReader()->read($path));
    }

    #[Test]
    public function rejectsADocumentNestedWithinTheDecodingDepthByItsUnknownTopLevelKey(): void
    {
        $path = $this->directory . DIRECTORY_SEPARATOR . 'site.json';
        file_put_contents($path, data: str_repeat(
            string: '{"a":',
            times : 511,
        ) . '1'
            . str_repeat(
                string: '}',
                times : 511,
            ));

        $this->assertRejected('a', static fn(): SiteConfig => new SiteConfigReader()->read($path));
    }

    #[Test]
    public function rejectsAFileThatCannotBeRead(): void
    {
        $path = $this->directory . DIRECTORY_SEPARATOR . 'absent.json';

        try {
            new SiteConfigReader()->read($path);

            static::fail('A configuration file that cannot be read must not be accepted.');
        } catch (InvalidSiteConfigException $exception) {
            static::assertSame('site.json', $exception->key);
            static::assertSame(ReasonCode::InvalidSiteConfig, $exception->reasonCode);
            static::assertSame(
                sprintf('Invalid site configuration at "site.json": "%s" cannot be read.', $path),
                $exception->getMessage(),
            );
        }
    }

    #[Test]
    public function rejectsAFileThatDoesNotHoldAJsonObject(): void
    {
        $path = $this->directory . DIRECTORY_SEPARATOR . 'site.json';
        file_put_contents($path, data: '42');

        try {
            new SiteConfigReader()->read($path);

            static::fail('A configuration file must hold a JSON object.');
        } catch (InvalidSiteConfigException $exception) {
            static::assertSame('site.json', $exception->key);
            static::assertSame(ReasonCode::InvalidSiteConfig, $exception->reasonCode);
            static::assertSame(
                'Invalid site configuration at "site.json": the file does not hold a JSON object.',
                $exception->getMessage(),
            );
        }
    }

    #[Test]
    public function rejectsAFileThatIsNotValidJson(): void
    {
        $path = $this->directory . DIRECTORY_SEPARATOR . 'site.json';
        file_put_contents($path, data: '{"organization":');

        $this->assertRejected('site.json', static fn(): SiteConfig => new SiteConfigReader()->read($path));
    }

    #[Test]
    public function rejectsAHostnameCarryingAScheme(): void
    {
        $configuration         = $this->configuration();
        $configuration['site'] = ['hostname' => 'https://docs.example.com'];

        $this->assertRejected('site.hostname', fn(): SiteConfig => $this->read($configuration));
    }

    #[Test]
    public function rejectsAKeyTheSchemaDoesNotDeclare(): void
    {
        $configuration           = $this->configuration();
        $configuration['colour'] = 'blue';

        $this->assertRejected('colour', fn(): SiteConfig => $this->read($configuration));
    }

    #[Test]
    public function rejectsAMissingKey(): void
    {
        $configuration         = $this->configuration();
        $configuration['site'] = [];

        $this->assertRejected('site.hostname', fn(): SiteConfig => $this->read($configuration));
    }

    #[Test]
    public function rejectsAMissingSection(): void
    {
        $configuration = $this->configuration();
        unset($configuration['organization']);

        $this->assertRejected('organization', fn(): SiteConfig => $this->read($configuration));
    }

    #[Test]
    public function rejectsAnUnknownKeyInsideTheOrganizationSection(): void
    {
        $configuration                         = $this->configuration();
        $configuration['organization']['logo'] = 'logo.svg';

        $this->assertRejected('organization.logo', fn(): SiteConfig => $this->read($configuration));
    }

    #[Test]
    public function rejectsAnUnknownKeyInsideTheOutputSection(): void
    {
        $configuration                       = $this->configuration();
        $configuration['output']['compress'] = true;

        $this->assertRejected('output.compress', fn(): SiteConfig => $this->read($configuration));
    }

    #[Test]
    public function rejectsAnUnknownKeyInsideTheSiteSection(): void
    {
        $configuration                 = $this->configuration();
        $configuration['site']['base'] = '/docs';

        $this->assertRejected('site.base', fn(): SiteConfig => $this->read($configuration));
    }

    #[Test]
    public function rejectsAnUnknownKeyInsideTheTemplatesSection(): void
    {
        $configuration              = $this->configuration();
        $configuration['templates'] = ['directory' => 'templates'];

        $this->assertRejected('templates.directory', fn(): SiteConfig => $this->read($configuration));
    }

    #[Test]
    public function rejectsAnUnknownKeyInsideTheVersionsSection(): void
    {
        $configuration             = $this->configuration();
        $configuration['versions'] = ['strategy' => 'tree', 'cadence' => 'weekly'];

        $this->assertRejected('versions.cadence', fn(): SiteConfig => $this->read($configuration));
    }

    #[Test]
    public function rejectsAStrategyThatIsNotAMember(): void
    {
        $configuration             = $this->configuration();
        $configuration['versions'] = ['strategy' => 'branch'];

        $this->assertRejected('versions.strategy', fn(): SiteConfig => $this->read($configuration));
    }

    #[Test]
    public function rejectsATemplateMapValueThatIsADirectory(): void
    {
        static::assertTrue(mkdir(
            $this->directory . DIRECTORY_SEPARATOR . 'templates' . DIRECTORY_SEPARATOR . 'page.php',
            recursive: true,
        ));

        $configuration              = $this->configuration();
        $configuration['templates'] = ['map' => ['page' => 'templates/page.php']];

        $this->assertRejected('templates.map.page', fn(): SiteConfig => $this->read($configuration));
    }

    #[Test]
    public function rejectsATemplateMapValueThatIsNotAReadableFile(): void
    {
        $configuration              = $this->configuration();
        $configuration['templates'] = ['map' => ['page' => 'templates/absent.php']];

        $this->assertRejected('templates.map.page', fn(): SiteConfig => $this->read($configuration));
    }

    #[Test]
    public function rejectsATokenVariableNameThatIsNotAnEnvironmentVariable(): void
    {
        $configuration                 = $this->configuration();
        $configuration['organization'] = ['name' => 'common-doc', 'tokenEnvVar' => '1-not-a-name'];

        $this->assertRejected('organization.tokenEnvVar', fn(): SiteConfig => $this->read($configuration));
    }

    #[Test]
    public function resolvesRelativePathsAgainstTheConfigurationDirectory(): void
    {
        $config = $this->read($this->configuration());

        static::assertSame($this->directory . DIRECTORY_SEPARATOR . 'build', $config->outputDirectory);
        static::assertSame(
            $this->directory . DIRECTORY_SEPARATOR . 'build' . DIRECTORY_SEPARATOR . 'report.json',
            $config->reportPath,
        );
        static::assertSame(
            $this->directory . DIRECTORY_SEPARATOR . '.cache' . DIRECTORY_SEPARATOR . 'multi-repo',
            $config->cacheDirectory,
        );
    }

    protected function setUp(): void
    {
        $this->directory = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'aggregate-renderer-' . bin2hex(random_bytes(8));

        static::assertTrue(mkdir($this->directory, permissions: 0o777, recursive: true));
    }

    protected function tearDown(): void
    {
        if (is_dir($this->directory)) {
            $this->removeDirectory($this->directory);
        }
    }

    /**
     * @param callable(): mixed $action
     */
    private function assertRejected(string $key, callable $action): void
    {
        try {
            $action();
        } catch (InvalidSiteConfigException $exception) {
            static::assertSame($key, $exception->key);
            static::assertSame(ReasonCode::InvalidSiteConfig, $exception->reasonCode);

            return;
        }

        static::fail(sprintf('The configuration was expected to be rejected at "%s".', $key));
    }

    /**
     * @return array<string, mixed>
     */
    private function configuration(): array
    {
        return [
            'organization' => ['name' => 'common-doc', 'tokenEnvVar' => 'COMMONDOC_TOKEN'],
            'output'       => ['directory' => 'build', 'reportPath' => 'build/report.json'],
            'site'         => ['hostname' => 'docs.example.com'],
        ];
    }

    /**
     * @param array<string, mixed> $configuration
     */
    private function read(array $configuration): SiteConfig
    {
        $path = $this->directory . DIRECTORY_SEPARATOR . 'site.json';
        file_put_contents($path, json_encode($configuration, JSON_THROW_ON_ERROR));

        return new SiteConfigReader()->read($path);
    }

    private function removeDirectory(string $directory): void
    {
        $entries = glob($directory . DIRECTORY_SEPARATOR . '*');

        foreach (false === $entries ? [] : $entries as $entry) {
            if (is_dir($entry)) {
                $this->removeDirectory($entry);

                continue;
            }

            unlink($entry);
        }

        rmdir($directory);
    }
}
