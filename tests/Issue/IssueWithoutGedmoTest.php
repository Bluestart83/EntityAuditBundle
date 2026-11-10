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

use Sonata\EntityAuditBundle\Tests\BaseTest;
use Sonata\EntityAuditBundle\Tests\Fixtures\Issue\SoftDeleteOrphanChild;
use Sonata\EntityAuditBundle\Tests\Fixtures\Issue\SoftDeleteOrphanParent;

/**
 * The bundle must keep working in an application that does not require gedmo/doctrine-extensions.
 */
final class IssueWithoutGedmoTest extends BaseTest
{
    private const GEDMO_EVENT_ARGS = 'Gedmo\SoftDeleteable\Event\PostSoftDeleteEventArgs';

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
        // Each test sets up the schema itself, after deciding whether Gedmo can be loaded.
    }

    /**
     * @runInSeparateProcess
     *
     * @preserveGlobalState disabled
     */
    public function testAuditWorksWhenGedmoIsNotInstalled(): void
    {
        $this->hideGedmoFromAutoloading();
        parent::setUp();
        static::assertFalse(class_exists(self::GEDMO_EVENT_ARGS), 'Gedmo must not be loadable in this process');

        $em = $this->getEntityManager();
        $parent = new SoftDeleteOrphanParent();
        $child = new SoftDeleteOrphanChild('child');
        $parent->addChild($child);
        $em->persist($parent);
        $em->flush();
        $id = $child->getId();

        $child->setName('renamed');
        $em->flush();

        $em->remove($child);
        $em->flush();

        static::assertSame(['INS', 'UPD', 'DEL'], array_column($this->fetchChildAuditRows($id), 'revtype'));
        static::assertFalse(class_exists(self::GEDMO_EVENT_ARGS), 'The audit must never load Gedmo itself');
    }

    /**
     * Wraps the registered autoloaders so that no class of the Gedmo namespace can be loaded, as in an
     * application that does not require gedmo/doctrine-extensions.
     */
    private function hideGedmoFromAutoloading(): void
    {
        $loaders = spl_autoload_functions();
        foreach ($loaders as $loader) {
            spl_autoload_unregister($loader);
        }

        spl_autoload_register(static function (string $class) use ($loaders): void {
            if (str_starts_with($class, 'Gedmo\\')) {
                return;
            }
            foreach ($loaders as $loader) {
                $loader($class);
                if (class_exists($class, false) || interface_exists($class, false) || trait_exists($class, false) || enum_exists($class, false)) {
                    return;
                }
            }
        });
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function fetchChildAuditRows(?int $id): array
    {
        return $this->getEntityManager()->getConnection()->fetchAllAssociative(
            'SELECT rev, revtype FROM soft_delete_orphan_child_audit WHERE id = ? ORDER BY rev',
            [$id]
        );
    }
}
