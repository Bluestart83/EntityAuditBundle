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

namespace SimpleThings\EntityAudit\EventListener;

use Doctrine\Common\EventSubscriber;
use Doctrine\DBAL\Schema\Column;
use Doctrine\DBAL\Schema\Schema;
use Doctrine\DBAL\Schema\Table;
use Doctrine\DBAL\Types\Type;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping\ClassMetadataInfo;
use Doctrine\ORM\Tools\Event\GenerateSchemaEventArgs;
use Doctrine\ORM\Tools\Event\GenerateSchemaTableEventArgs;
use Doctrine\ORM\Tools\ToolEvents;
use SimpleThings\EntityAudit\AuditConfiguration;
use SimpleThings\EntityAudit\AuditManager;
use SimpleThings\EntityAudit\Metadata\MetadataFactory;

/**
 * NEXT_MAJOR: do not implement EventSubscriber interface anymore.
 */
class CreateSchemaListener implements EventSubscriber
{
    private AuditConfiguration $config;

    private MetadataFactory $metadataFactory;

    /**
     * @var string[]
     */
    private array $defferedJoinTablesToCreate = [];

    public function __construct(AuditManager $auditManager)
    {
        $this->config = $auditManager->getConfiguration();
        $this->metadataFactory = $auditManager->getMetadataFactory();
    }

    /**
     * NEXT_MAJOR: remove this method.
     *
     * @return string[]
     */
    #[\ReturnTypeWillChange]
    public function getSubscribedEvents()
    {
        return [
            ToolEvents::postGenerateSchemaTable,
            ToolEvents::postGenerateSchema,
        ];
    }

    public function postGenerateSchemaTable(GenerateSchemaTableEventArgs $eventArgs): void
    {
        $cm = $eventArgs->getClassMetadata();

        if (!$this->metadataFactory->isAudited($cm->name)) {
            $audited = false;
            if ($cm->isInheritanceTypeJoined() && $cm->rootEntityName === $cm->name) {
                foreach ($cm->subClasses as $subClass) {
                    if ($this->metadataFactory->isAudited($subClass)) {
                        $audited = true;

                        break;
                    }
                }
            }
            if (!$audited) {
                return;
            }
        }

        $schema = $eventArgs->getSchema();

        $revisionsTable = $this->createRevisionsTable($schema);

        $entityTable = $eventArgs->getClassTable();
        $revisionTable = $schema->createTable(
            $this->config->getTablePrefix().$entityTable->getName().$this->config->getTableSuffix()
        );

        foreach ($entityTable->getColumns() as $column) {
            $this->addColumnToTable($column, $revisionTable);
        }
        $revisionTable->addColumn($this->config->getRevisionFieldName(), $this->config->getRevisionIdFieldType());
        $revisionTable->addColumn($this->config->getRevisionTypeFieldName(), Types::STRING, ['length' => 4]);
        if (!\in_array($cm->inheritanceType, [ClassMetadataInfo::INHERITANCE_TYPE_NONE, ClassMetadataInfo::INHERITANCE_TYPE_JOINED, ClassMetadataInfo::INHERITANCE_TYPE_SINGLE_TABLE], true)) {
            throw new \Exception(sprintf('Inheritance type "%s" is not yet supported', $cm->inheritanceType));
        }

        // ///////////////// Added
        $revisionTable->addColumn('validated', 'boolean')->setNotnull(true)->setDefault(false);
        $revisionTable->addIndex(['validated'], 'validated_'.md5($revisionTable->getName()).'_idx');

        $projectIdFieldName= $this->config->getProjectIdFieldName();
        if ($projectIdFieldName != null && $entityTable->hasColumn( $projectIdFieldName)) {
            //$projectFieldName= $this->config->getProjectFieldName();
            //$projectFieldId = $class->getAssociationMapping($projectFieldName)['joinColumns'][0]['name'] ?? null;
    
            $revisionTable->addIndex([$projectIdFieldName],  $projectIdFieldName.'_'.md5($revisionTable->getName()).'_idx');
        } 
		/////////////////////////////
		
        $primaryKey = $entityTable->getPrimaryKey();
        \assert($primaryKey !== null);
        $pkColumns = $primaryKey->getColumns();
        $pkColumns[] = $this->config->getRevisionFieldName();
        $revisionTable->setPrimaryKey($pkColumns);
        $revIndexName = $this->config->getRevisionFieldName().'_'.md5($revisionTable->getName()).'_idx';
        $revisionTable->addIndex([$this->config->getRevisionFieldName()], $revIndexName);

        foreach ($cm->associationMappings as $associationMapping) {
            if ($associationMapping['isOwningSide'] && isset($associationMapping['joinTable'])) {
                if (isset($associationMapping['joinTable']['name'])) {
                    if ($schema->hasTable($associationMapping['joinTable']['name'])) {
                        $this->createRevisionJoinTableForJoinTable($schema, $associationMapping['joinTable']['name']);
                    } else {
                        $this->defferedJoinTablesToCreate[] = $associationMapping['joinTable']['name'];
                    }
                }
            }
        }

        if (!$this->config->areForeignKeysDisabled()) {
            $this->createForeignKeys($revisionTable, $revisionsTable);
        }
    }

    public function postGenerateSchema(GenerateSchemaEventArgs $eventArgs): void
    {
        $schema = $eventArgs->getSchema();
        $this->createRevisionsTable($schema);

        foreach ($this->defferedJoinTablesToCreate as $defferedJoinTableToCreate) {
            $this->createRevisionJoinTableForJoinTable($schema, $defferedJoinTableToCreate);
        }
    }

    private function createForeignKeys(Table $relatedTable, Table $revisionsTable): void
    {
        $revisionForeignKeyName = $this->config->getRevisionFieldName().'_'.md5($relatedTable->getName()).'_fk';
        $primaryKey = $revisionsTable->getPrimaryKey();
        \assert($primaryKey !== null);

        $relatedTable->addForeignKeyConstraint(
            $revisionsTable,
            [$this->config->getRevisionFieldName()],
            $primaryKey->getColumns(),
            [],
            $revisionForeignKeyName
        );
    }

    /**
     * Copies $column to another table. All its options are copied but notnull and autoincrement which are set to false.
     */
    private function addColumnToTable(Column $column, Table $targetTable): void
    {
        $columnName = $column->getName();

        $targetTable->addColumn(
            $columnName,
            Type::getTypeRegistry()->lookupName($column->getType())
        );

        $targetColumn = $targetTable->getColumn($columnName);
        $targetColumn->setLength($column->getLength());
        $targetColumn->setPrecision($column->getPrecision());
        $targetColumn->setScale($column->getScale());
        $targetColumn->setUnsigned($column->getUnsigned());
        $targetColumn->setFixed($column->getFixed());
        $targetColumn->setDefault($column->getDefault());
        $targetColumn->setColumnDefinition($column->getColumnDefinition());
        $targetColumn->setComment($column->getComment());
        $targetColumn->setPlatformOptions($column->getPlatformOptions());

        $targetColumn->setNotnull(false);
        $targetColumn->setAutoincrement(false);
    }

    /**
     * Table that list main revisions numbers
     */
    private function createRevisionsTable(Schema $schema): Table
    {
        $revisionsTableName = $this->config->getRevisionTableName();

        if ($schema->hasTable($revisionsTableName)) {
            return $schema->getTable($revisionsTableName);
        }

        $revisionsTable = $schema->createTable($revisionsTableName);
        $revisionsTable->addColumn('id', $this->config->getRevisionIdFieldType(), [
            'autoincrement' => true,
        ]);
        $revisionsTable->addColumn('timestamp', Types::DATETIME_MUTABLE);
        //$revisionsTable->addColumn('username', Types::STRING)->setNotnull(false);
        $revisionsTable->setPrimaryKey(['id']);

        // ///////////////// Added
        $revisionsTable->addColumn('user_id', 'integer')->setNotnull(false);
        $projectField = $this->config->getProjectFieldName();
        $revisionsTable->addColumn( $projectField, 'integer')->setNotnull(false);
        $revisionsTable->addIndex([ $projectField],  $projectField.'_'.md5($revisionsTable->getName()).'_idx');

        // /////////////////
        return $revisionsTable;
    }

    private function createRevisionJoinTableForJoinTable(Schema $schema, string $joinTableName): void
    {
        $joinTable = $schema->getTable($joinTableName);
        $revisionJoinTableName = $this->config->getTablePrefix().$joinTable->getName().$this->config->getTableSuffix();

        if ($schema->hasTable($revisionJoinTableName)) {
            return;
        }

        $typeRegistry = Type::getTypeRegistry();
        $revisionJoinTable = $schema->createTable(
            $this->config->getTablePrefix().$joinTable->getName().$this->config->getTableSuffix()
        );
        foreach ($joinTable->getColumns() as $column) {
            /* @var Column $column */
            $revisionJoinTable->addColumn(
                $column->getName(),
                $typeRegistry->lookupName($column->getType()),
                ['notnull' => false, 'autoincrement' => false]
            );
        }
        $revisionJoinTable->addColumn($this->config->getRevisionFieldName(), $this->config->getRevisionIdFieldType());
        $revisionJoinTable->addColumn($this->config->getRevisionTypeFieldName(), 'string', ['length' => 4]);

        $pk = $joinTable->getPrimaryKey();
        $pkColumns = $pk !== null ? $pk->getColumns() : [];
        $pkColumns[] = $this->config->getRevisionFieldName();
        $revisionJoinTable->setPrimaryKey($pkColumns);
        $revIndexName = $this->config->getRevisionFieldName().'_'.md5($revisionJoinTable->getName()).'_idx';
        $revisionJoinTable->addIndex([$this->config->getRevisionFieldName()], $revIndexName);
    }
}
