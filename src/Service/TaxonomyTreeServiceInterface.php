<?php

declare(strict_types=1);

namespace CoolMS\Taxonomy\Service;

use CoolMS\Taxonomy\Entity\TaxonomyNodeInterface;
use CoolMS\Taxonomy\Entity\TaxonomyTreeInterface;

interface TaxonomyTreeServiceInterface
{
    /**
     * Insert a new node into the tree.
     * The node's $tree must be set before calling this method.
     * If $parent is null, the node becomes a root node within its tree.
     */
    public function insertNode(TaxonomyNodeInterface $node, ?TaxonomyNodeInterface $parent): void;

    /**
     * Move an existing node (and its entire subtree) to a new parent.
     * If $newParent is null, the node becomes a root node.
     * Cross-tree moves are forbidden.
     */
    public function moveNode(TaxonomyNodeInterface $node, ?TaxonomyNodeInterface $newParent): void;

    /**
     * Remove a node and its entire subtree, then close the gap in the nested set indexes.
     */
    public function removeNode(TaxonomyNodeInterface $node): void;

    /**
     * Rebuild nested set indexes for the entire tree.
     * Useful for recovering from an inconsistent state.
     */
    public function rebuildTree(TaxonomyTreeInterface $tree): void;
}
