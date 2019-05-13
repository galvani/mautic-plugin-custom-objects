<?php

declare(strict_types=1);

/*
* @copyright   2019 Mautic, Inc. All rights reserved
* @author      Mautic, Inc.
*
* @link        https://mautic.com
*
* @license     GNU/GPLv3 http://www.gnu.org/licenses/gpl-3.0.html
*/

namespace MauticPlugin\CustomObjectsBundle\Migrations;

use Mautic\CoreBundle\Exception\SchemaException;
use MauticPlugin\CustomObjectsBundle\Migration\AbstractMigration;
use Doctrine\DBAL\Schema\Schema;

class Version_0_0_8 extends AbstractMigration
{
    /**
     * @var string
     */
    private $table = 'custom_item_xref_custom_item';

    /**
     * {@inheritdoc}
     */
    protected function isApplicable(Schema $schema): bool
    {
        try {
            return $schema->getTable($this->concatPrefix($this->table))->hasColumn('parent_custom_item_id');
        } catch (SchemaException $e) {
            return false;
        }
    }

    /**
     * {@inheritdoc}
     */
    protected function up(): void
    {
        // There is no easy way to rename columns with primary and foreign keys and this table hasn't been used yet,
        // so let's delete it and create with proper column names.
        $this->addSql("DROP TABLE {$this->concatPrefix($this->table)}");
        $this->addSql("CREATE TABLE {$this->concatPrefix($this->table)} (
                custom_item_id_lower BIGINT UNSIGNED NOT NULL, 
                custom_item_id_higher BIGINT UNSIGNED NOT NULL, 
                date_added DATETIME NOT NULL COMMENT '(DC2Type:datetime)',
                PRIMARY KEY(custom_item_id_lower, custom_item_id_higher)
            ) DEFAULT CHARACTER SET utf8 COLLATE utf8_unicode_ci ENGINE = InnoDB
        ");

        $this->addSql($this->generateAlterTableForeignKeyStatement(
            $this->table,
            ['custom_item_id_lower'],
            'custom_item',
            ['id'],
            'ON DELETE CASCADE'
        ));

        $this->addSql($this->generateAlterTableForeignKeyStatement(
            $this->table,
            ['custom_item_id_higher'],
            'custom_item',
            ['id'],
            'ON DELETE CASCADE'
        ));
    }
}