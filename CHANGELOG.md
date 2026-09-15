# Changelog

All notable changes to `coolms/taxonomy` are recorded here.

The format follows [Keep a Changelog](https://keepachangelog.com/en/1.1.0/).

## Unreleased

### Added

**A test suite.** `phpunit.xml.dist`, the `tests/` namespace and the dev
dependency, copied from the field family this package was built after. CI
runs the suite outright: the step that printed "no tests in this package
yet" and exited green is gone, so an empty suite now fails the build
instead of reporting success over nothing. The first tests here are the
ones the application had been carrying for this package: `TaxonomyNodeSlugTest`, `TaxonomyTreeServiceMoveTest`.

## 2.0.0-alpha1 - 2026-09-10

**A pre-release. It carries no compatibility promise.** Composer will not install
it under default stability; require it with `@alpha` or set
`minimum-stability: dev` with `prefer-stable: true`.

### Added

- `Entity\TaxonomyTree` and `Entity\TaxonomyNode`, with their interfaces. Nodes
  form a nested set (`lft`, `rgt`, `level`) and implement
  `NestedSetNodeInterface` from `coolms/core`.
- `Repository\TaxonomyNodeRepositoryInterface` and
  `Repository\TaxonomyTreeRepositoryInterface` - the two contracts a persistence
  adapter implements. `coolms/taxonomy-doctrine` provides one.
- `Service\TaxonomyTreeService` and its interface: move, reparent, read a
  subtree.

### Extracted from the application, with two changes that were not cosmetic

Both were invisible while the code lived in the application, and both are the
reason this package can be installed on its own.

- **The entities no longer name a repository class.**
  `#[ORM\Entity(repositoryClass: ...)]` pointed at an infrastructure class in the
  application, so the domain depended on the application it was being extracted
  from. It was never the binding mechanism - the DI extension aliases the
  interface to the concrete repository - so the attribute was decorative and
  removing it changed no behaviour.
- **The entities carry no Doctrine attributes at all.** The mapping moved to XML
  in `coolms/taxonomy-doctrine`. Installing this package pulls in no ORM.

### The discriminator map names only `TaxonomyNode`

`TaxonomyNode` is the root of a JOINED inheritance and a consumer may subclass
it. The subclass declares itself with `#[DiscriminatorValue]`; the parent is
never edited for the child's sake. See the README - completing the map is the
change that makes this package unpublishable.

### Why `coolms/entity` is constrained to `^2.0.0-alpha3`

`CoolMS\Entity\Attribute\DiscriminatorValue` moved into `coolms/entity` and
first shipped in **v2.0.0-alpha3**. Earlier releases resolve cleanly and then
fail the moment anything reflects the attribute, because PHP resolves an
attribute class lazily: the entity autoloads, `getAttributes()` returns an entry,
and only `newInstance()` throws.

⚠️ So `^2.0` would have been wrong in the quiet way. It installs and breaks on
first boot, and no `class_exists` check finds it.

~~Until alpha3 existed this was expressed as
`"conflict": {"coolms/entity": "<=2.0.0-alpha2"}`~~ -- a constraint can only name
a release that exists, so the conflict stood in for the floor until the floor
could be written. It has been removed now that it can.
