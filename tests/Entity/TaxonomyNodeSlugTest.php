<?php

declare(strict_types=1);

namespace CoolMS\Taxonomy\Tests\Entity;

use CoolMS\Taxonomy\Entity\TaxonomyNode;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

/**
 * The taxonomy-node slug-format rule: a machine-readable slug must be a
 * lowercase letter followed by lowercase letters, digits or hyphens -- the same
 * shape a DynamicEntityType alias requires.
 */
#[CoversClass(TaxonomyNode::class)]
final class TaxonomyNodeSlugTest extends TestCase
{
    /** @return iterable<string, array{string}> */
    public static function validSlugs(): iterable
    {
        yield 'lowercase word' => ['category'];
        yield 'kebab-case' => ['news-and-events'];
        yield 'letter then digit' => ['a1'];
        yield 'digits and hyphens' => ['x-2026-01'];
    }

    /** @return iterable<string, array{string}> */
    public static function invalidSlugs(): iterable
    {
        yield 'empty' => [''];
        yield 'single char (too short)' => ['a'];
        yield 'leading digit' => ['1category'];
        yield 'leading hyphen' => ['-category'];
        yield 'uppercase' => ['Category'];
        yield 'underscore' => ['news_events'];
        yield 'inner space' => ['news events'];
        yield 'trailing space' => ['news '];
        yield 'slash' => ['news/events'];
        yield 'non-ascii' => ['naïve'];
    }

    #[Test]
    #[DataProvider('validSlugs')]
    public function acceptsWellFormedSlugs(string $slug): void
    {
        self::assertTrue(TaxonomyNode::isValidSlug($slug));
    }

    #[Test]
    #[DataProvider('invalidSlugs')]
    public function rejectsMalformedSlugs(string $slug): void
    {
        self::assertFalse(TaxonomyNode::isValidSlug($slug));
    }
}
