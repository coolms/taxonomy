<?php

declare(strict_types=1);

namespace CoolMS\Taxonomy\Tests\Entity;

use CoolMS\Core\Timestampable\UpdatedAtProviderInterface;
use CoolMS\Taxonomy\Entity\TaxonomyNode;
use CoolMS\Taxonomy\Entity\TaxonomyTree;
use DateTimeImmutable;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

/**
 * Trees and nodes declare that they keep `updatedAt`: an application's timestampable listener sets it on every
 * update of an entity that provides it, and on no other. Both store the moment they are given and read it back.
 */
#[CoversClass(TaxonomyNode::class)]
#[CoversClass(TaxonomyTree::class)]
final class UpdatedAtIsProvidedTest extends TestCase
{
    #[Test]
    public function aNodeProvidesItsUpdatedAt(): void
    {
        $this->assertKeepsWhatItIsGiven(new TaxonomyNode('Node', 'node'));
    }

    #[Test]
    public function aTreeProvidesItsUpdatedAt(): void
    {
        $this->assertKeepsWhatItIsGiven(new TaxonomyTree('Tree', 'tree'));
    }

    private function assertKeepsWhatItIsGiven(object $entity): void
    {
        self::assertInstanceOf(UpdatedAtProviderInterface::class, $entity);
        $at = new DateTimeImmutable('2001-02-03 04:05:06');
        $entity->updatedAt = $at;
        self::assertEquals($at, $entity->updatedAt);
        self::assertNotNull($entity->updatedAtAsString);
    }
}
