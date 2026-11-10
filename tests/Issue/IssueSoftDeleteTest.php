<?php

declare(strict_types=1);

/*
 * This file is part of the Sonata Project package.
 *
 * (c) Thomas Rabaix <thomas.rabaix@sonata-project.org>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Sonata\EntityAuditBundle\Tests\Issue;

use Gedmo\SoftDeleteable\SoftDeleteableListener;
use Sonata\EntityAuditBundle\Tests\BaseTest;
use Sonata\EntityAuditBundle\Tests\Fixtures\Issue\SoftDeleteOrphanChild;
use Sonata\EntityAuditBundle\Tests\Fixtures\Issue\SoftDeleteOrphanParent;

/**
 * A Gedmo soft delete must be audited whatever the order of the onFlush listeners.
 *
 * StofDoctrineExtensionsBundle registers the SoftDeleteableListener before the audit listener,
 * so by the time the audit onFlush runs, Gedmo has already turned the scheduled deletion
 * into an extra update of the deletedAt field.
 */
final class IssueSoftDeleteTest extends BaseTest
{
    protected $schemaEntities = [
        SoftDeleteOrphanParent::class,
        SoftDeleteOrphanChild::class,
    ];

    protected $auditedEntities = [
        SoftDeleteOrphanParent::class,
        SoftDeleteOrphanChild::class,
    ];

    protected function setUp(): void
    {
        // Listeners and schema are set up by setUpWithListenerOrder(), each test picks the order.
    }

    /**
     * @return iterable<string, array{bool}>
     */
    public static function provideListenerOrder(): iterable
    {
        yield 'soft delete listener before audit' => [true];
        yield 'audit before soft delete listener' => [false];
    }

    /**
     * @dataProvider provideListenerOrder
     */
    public function testOrphanRemovalSoftDeleteIsAudited(bool $softDeleteFirst): void
    {
        $this->setUpWithListenerOrder($softDeleteFirst);
        $em = $this->getEntityManager();
        [$parent, $child] = $this->createParentWithChild();

        $parent->removeChild($child);
        $em->flush();

        $this->assertSoftDeleteRecorded($child);
    }

    /**
     * @dataProvider provideListenerOrder
     */
    public function testRemoveSoftDeleteIsAudited(bool $softDeleteFirst): void
    {
        $this->setUpWithListenerOrder($softDeleteFirst);
        $em = $this->getEntityManager();
        [, $child] = $this->createParentWithChild();

        $em->remove($child);
        $em->flush();

        $this->assertSoftDeleteRecorded($child);
    }

    /**
     * @dataProvider provideListenerOrder
     */
    public function testHardDeleteOfSoftDeletedEntityWritesOneDeletion(bool $softDeleteFirst): void
    {
        $this->setUpWithListenerOrder($softDeleteFirst);
        $em = $this->getEntityManager();
        [, $child] = $this->createParentWithChild();
        $id = $child->getId();

        $em->remove($child);
        $em->flush();
        $softDeleteRows = \count($this->fetchChildAuditRows($id));

        $em->remove($child);
        $em->flush();

        $newRows = \array_slice($this->fetchChildAuditRows($id), $softDeleteRows);
        static::assertCount(1, $newRows);
        static::assertSame('DEL', $newRows[0]['revtype']);
        static::assertSame(0, (int) $em->getConnection()->fetchOne('SELECT COUNT(*) FROM soft_delete_orphan_child'));
    }

    /**
     * @dataProvider provideListenerOrder
     */
    public function testPlainUpdateIsUnchanged(bool $softDeleteFirst): void
    {
        $this->setUpWithListenerOrder($softDeleteFirst);
        $em = $this->getEntityManager();
        [, $child] = $this->createParentWithChild();

        $child->setName('renamed');
        $em->flush();

        $rows = $this->fetchChildAuditRows($child->getId());
        static::assertSame(['INS', 'UPD'], array_column($rows, 'revtype'));
        static::assertSame('renamed', $rows[1]['name']);
        static::assertNull($rows[1]['deleted_at']);
    }

    private function setUpWithListenerOrder(bool $softDeleteFirst): void
    {
        $eventManager = $this->getEntityManager()->getEventManager();

        if ($softDeleteFirst) {
            $eventManager->addEventSubscriber(new SoftDeleteableListener());
        }

        parent::setUp();

        if (!$softDeleteFirst) {
            $eventManager->addEventSubscriber(new SoftDeleteableListener());
        }
    }

    /**
     * @return array{SoftDeleteOrphanParent, SoftDeleteOrphanChild}
     */
    private function createParentWithChild(): array
    {
        $em = $this->getEntityManager();

        $parent = new SoftDeleteOrphanParent();
        $child = new SoftDeleteOrphanChild('child');
        $parent->addChild($child);

        $em->persist($parent);
        $em->flush();

        return [$parent, $child];
    }

    private function assertSoftDeleteRecorded(SoftDeleteOrphanChild $child): void
    {
        $em = $this->getEntityManager();
        $id = $child->getId();
        static::assertNotNull($id);

        static::assertNotNull(
            $em->getConnection()->fetchOne('SELECT deleted_at FROM soft_delete_orphan_child WHERE id = ?', [$id]),
            'Gedmo must have soft deleted the row'
        );

        $rows = $this->fetchChildAuditRows($id);
        static::assertSame(['INS', 'DEL'], array_column($rows, 'revtype'));
        static::assertNotNull($rows[1]['deleted_at']);
        static::assertSame('child', $rows[1]['name']);
        static::assertSame(2, (int) $em->getConnection()->fetchOne('SELECT COUNT(*) FROM revisions'), 'No empty revision');

        $audited = $this->getAuditManager()->createAuditReader($em)->find(SoftDeleteOrphanChild::class, $id, (int) $rows[1]['rev']);
        static::assertNotNull($audited);
        static::assertInstanceOf(\DateTimeInterface::class, $audited->getDeletedAt());
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function fetchChildAuditRows(?int $id): array
    {
        return $this->getEntityManager()->getConnection()->fetchAllAssociative(
            'SELECT rev, revtype, name, deleted_at FROM soft_delete_orphan_child_audit WHERE id = ? ORDER BY rev',
            [$id]
        );
    }
}
