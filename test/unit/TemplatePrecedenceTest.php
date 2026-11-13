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

namespace CommonDocTest\Aggregate;

use CommonDoc\Aggregate\ConfigProvider;
use CommonDoc\Aggregate\Render\ViewFactory;
use CommonDoc\ConfigProvider as CommondocConfigProvider;
use Laminas\ConfigAggregator\ConfigAggregator;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

use function is_array;
use function is_string;
use function str_ends_with;
use function str_replace;

/**
 * The precedence proof: two config providers, merged the way a consumer merges them.
 *
 * commondoc publishes `templates` with a document address and a layout; this package publishes the
 * same keys. Both arrive through `extra.laminas.config-provider`, and for a scalar map entry the
 * provider merged last wins. That ordering is the whole override mechanism, so it is asserted against
 * a real aggregator rather than against the array this package returns in isolation.
 */
#[CoversClass(ConfigProvider::class)]
final class TemplatePrecedenceTest extends TestCase
{
    private const array BOTH = [CommondocConfigProvider::class, ConfigProvider::class];

    private static function normalised(string $path): string
    {
        return str_replace(
            search : '\\',
            replace: '/',
            subject: $path,
        );
    }

    #[Test]
    public function commondocOnItsOwnResolvesTheDocumentAddressToItsOwnFile(): void
    {
        $map = $this->map(providers: [CommondocConfigProvider::class]);

        static::assertTrue(str_ends_with(
            haystack: self::normalised($map['commondoc::document'] ?? ''),
            needle  : '/templates/commondoc/document.phtml',
        ));
    }

    #[Test]
    public function entriesTheAggregateDoesNotOverrideSurviveTheMerge(): void
    {
        $map = $this->map(providers: self::BOTH);

        static::assertTrue(str_ends_with(
            haystack: self::normalised($map['commondoc::layout'] ?? ''),
            needle  : '/templates/commondoc/layout.phtml',
        ));
    }

    #[Test]
    public function theAggregateDocumentEntryReplacesTheCommondocOne(): void
    {
        $map = $this->map(providers: self::BOTH);

        static::assertSame(
            ViewFactory::defaults()[ViewFactory::DOCUMENT],
            $map[ViewFactory::DOCUMENT] ?? null,
        );
    }

    #[Test]
    public function theLayoutIsTheAggregatesOwn(): void
    {
        $templates = $this->templates(providers: self::BOTH);

        static::assertSame(ViewFactory::LAYOUT, $templates['layout'] ?? null);
        static::assertTrue(str_ends_with(
            haystack: self::normalised($this->mapped($templates, 'aggregate::layout')),
            needle  : '/templates/aggregate/layout.phtml',
        ));
    }

    /**
     * @param list<class-string> $providers
     *
     * @return array<string, mixed>
     */
    private function map(array $providers): array
    {
        $templates = $this->templates(providers: $providers);

        /** @var array<string, mixed> */
        return is_array($templates['map'] ?? null) ? $templates['map'] : [];
    }

    /**
     * @param array<string, mixed> $templates
     */
    private function mapped(array $templates, string $name): string
    {
        $map = is_array($templates['map'] ?? null) ? $templates['map'] : [];

        return is_string($map[$name] ?? null) ? $map[$name] : '';
    }

    /**
     * @param list<class-string> $providers
     *
     * @return array<string, mixed>
     */
    private function templates(array $providers): array
    {
        $config = new ConfigAggregator(providers: $providers)->getMergedConfig();

        /** @var array<string, mixed> */
        return is_array($config['templates'] ?? null) ? $config['templates'] : [];
    }
}
