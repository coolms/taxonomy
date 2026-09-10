# coolms/taxonomy

Taxonomy trees and the nodes inside them: entities, repository contracts and the
tree service. This is the domain half of the family and it depends on no
framework.

```bash
composer require coolms/taxonomy
```

## What is in it

| | |
|---|---|
| `Entity\TaxonomyTree` | a named tree, addressed by a unique `code` |
| `Entity\TaxonomyNode` | a node in one tree, addressed by a unique `slug` |
| `Repository\*Interface` | the two contracts a persistence adapter implements |
| `Service\TaxonomyTreeService` | move, reparent and read a subtree |

Nodes form a **nested set**: `lft`, `rgt` and `level` are maintained by the tree
service, and every subtree read is a range scan over them rather than a
recursive walk. `TaxonomyNode` implements `NestedSetNodeInterface` from
`coolms/core`, which is where the traversal itself lives.

## It carries no Doctrine attributes

Deliberately, and it is the reason `coolms/taxonomy-doctrine` exists. The
entities are plain classes; the ORM mapping is XML shipped beside the Doctrine
adapters. Install this package alone and nothing pulls the ORM in.

The one attribute you will find is `#[DiscriminatorValue]`, which is CoolMS's
own and not Doctrine's. It is what lets a consumer subclass a node.

## Subclassing a node across a package boundary

`TaxonomyNode` is the root of a JOINED inheritance and its discriminator map
names **only itself**. A subclass registers itself:

```php
use CoolMS\Entity\Doctrine\Attribute\DiscriminatorValue;
use CoolMS\Taxonomy\Entity\TaxonomyNode;

#[DiscriminatorValue('product_category')]
class ProductCategory extends TaxonomyNode
{
}
```

`CoolMS\Entity\Doctrine\Event\DiscriminatorValueSubscriber` reads that attribute
at `loadClassMetadata` and adds the child to the root's map.

⚠️ **Do not add your class to this package's discriminator map.** It reads as an
obviously incomplete map and completing it is the edit that breaks the package:
a published package cannot name a class from your application, and any consumer
installing it without your code fatals on the map. The parent is not supposed to
know its children.

## The slug shape is part of the contract

`TaxonomyNode::SLUG_PATTERN` is a lowercase letter followed by lowercase letters,
digits or hyphens. It is deliberately the entity-alias shape, so a node's slug is
a valid entity alias by construction and a consumer that subclasses nodes into
entity types needs no translation step.

## Requires

`coolms/core`, `coolms/entity`, `coolms/entity-doctrine` (for the discriminator
attribute), `coolms/rql`, `doctrine/collections`, `symfony/uid`.

## Family

- **`coolms/taxonomy`** - this package
- `coolms/taxonomy-doctrine` - XML mapping and the Doctrine repositories
- `coolms/taxonomy-bundle` - Symfony bundle, DI, console commands, API surface
