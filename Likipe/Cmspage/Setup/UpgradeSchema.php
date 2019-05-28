<?php

namespace Likipe\Cmspage\Setup;

use Magento\Framework\Setup\ModuleContextInterface;
use Magento\Framework\Setup\SchemaSetupInterface;
use Magento\Framework\Setup\UpgradeSchemaInterface;

/**
 * @codeCoverageIgnore
 */
class UpgradeSchema implements UpgradeSchemaInterface
{

    /**
     * {@inheritdoc}
     */
    public function upgrade(SchemaSetupInterface $setup, ModuleContextInterface $context)
    {
        $installer = $setup;

        $installer->startSetup();

        if (version_compare($context->getVersion(), '1.0.1', '<')) {
            $tableName = $setup->getTable('cms_page');
            if ($setup->getConnection()->isTableExists($tableName) == true) {
                $connection = $installer->getConnection();
                $columns = [
                    'brand_list_active' =>
                        [
                            'type' => \Magento\Framework\DB\Ddl\Table::TYPE_INTEGER,
                            'nullable' => false,
                            'default' => 0,
                            'comment' => 'brand active'
                        ],
                    'brand_bottom' =>
                        [
                            'type' => \Magento\Framework\DB\Ddl\Table::TYPE_TEXT,
                            'length' => 255,
                            'input'         => 'multiselect',
                            'nullable' => false,
                            'comment' => 'brand list'
                        ],
                ];

                foreach ($columns as $name => $definition) {
                    $connection->addColumn($tableName, $name, $definition);
                }
            }
        }
        if (version_compare($context->getVersion(), '1.0.2', '<')) {
            $setup->getConnection()->dropColumn($setup->getTable('cms_page'), 'brand_html');
        }
        if (version_compare($context->getVersion(), '1.0.3', '<')) {
            $tableName = $setup->getTable('cms_page');
            if ($setup->getConnection()->isTableExists($tableName) == true) {
                $connection = $installer->getConnection();
                $columns = [
                    'left_menu' =>
                        [
                            'type' => \Magento\Framework\DB\Ddl\Table::TYPE_TEXT,
                            'length' => 255,
                            'input'         => 'multiselect',
                            'nullable' => false,
                            'comment' => 'left menu'
                        ],
                ];

                foreach ($columns as $name => $definition) {
                    $connection->addColumn($tableName, $name, $definition);
                }
            }
        }
        if (version_compare($context->getVersion(), '1.0.4', '<')) {
            $tableName = $setup->getTable('cms_page');
            if ($setup->getConnection()->isTableExists($tableName) == true) {
                $connection = $installer->getConnection();
                $columns = [
                    'parent_id' =>
                        [
                            'type' => \Magento\Framework\DB\Ddl\Table::TYPE_INTEGER,
                            'nullable' => false,
                            'default' => 0,
                            'comment' => 'Parent Cms ID'
                        ],
                    'path' =>
                        [
                            'type' => \Magento\Framework\DB\Ddl\Table::TYPE_TEXT,
                            'length' => 255,
                            'nullable' => false,
                            'comment' => 'Tree Path'
                        ],
                    'position' =>
                        [
                            'type' => \Magento\Framework\DB\Ddl\Table::TYPE_INTEGER,
                            'nullable' => false,
                            'default' => 0,
                            'comment' => 'Position'
                        ],
                    'level' =>
                        [
                            'type' => \Magento\Framework\DB\Ddl\Table::TYPE_INTEGER,
                            'nullable' => false,
                            'default' => 0,
                            'comment' => 'Tree level'
                        ],
                    'children_count' =>
                        [
                            'type' => \Magento\Framework\DB\Ddl\Table::TYPE_INTEGER,
                            'nullable' => false,
                            'default' => 0,
                            'comment' => 'Child Count'
                        ],
                ];

                foreach ($columns as $name => $definition) {
                    $connection->addColumn($tableName, $name, $definition);
                }
            }
        }
        if (version_compare($context->getVersion(), '1.0.5', '<')) {
            $tableName = $setup->getTable('cms_page');
            if ($setup->getConnection()->isTableExists($tableName) == true) {
                $connection = $installer->getConnection();
                $columns = [
                    'include_in_menu' =>
                        [
                            'type' => \Magento\Framework\DB\Ddl\Table::TYPE_INTEGER,
                            'nullable' => false,
                            'default' => 1,
                            'comment' => 'include_in_menu'
                        ],
                ];

                foreach ($columns as $name => $definition) {
                    $connection->addColumn($tableName, $name, $definition);
                }
            }
        }
        if (version_compare($context->getVersion(), '1.0.6', '<')) {
            $setup->getConnection()->dropColumn($setup->getTable('cms_page'), 'left_menu');
        }
    }
}