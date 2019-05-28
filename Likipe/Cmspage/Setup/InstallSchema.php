<?php
namespace Likipe\Cmspage\Setup;

use Magento\Framework\Setup\InstallSchemaInterface;
use Magento\Framework\Setup\ModuleContextInterface;
use Magento\Framework\Setup\SchemaSetupInterface;

class InstallSchema implements InstallSchemaInterface
{
    /**
     * Add Secondary Custom Content
     */
    public function install(SchemaSetupInterface $setup, ModuleContextInterface $context)
    {
        $installer = $setup;
        $installer->startSetup();
            $tableName = $setup->getTable('cms_page');
            if ($setup->getConnection()->isTableExists($tableName) == true) {
                $connection = $installer->getConnection();
                $columns = [
                    'box_active' =>
                        [
                            'type' => \Magento\Framework\DB\Ddl\Table::TYPE_INTEGER,
                            'nullable' => false,
                            'default' => 0,
                            'comment' => 'Box active'
                        ],
                    'brand_html' =>
                        [
                            'type' => \Magento\Framework\DB\Ddl\Table::TYPE_TEXT,
                            'length' => '64k',
                            'nullable' => false,
                            'comment' => 'Brand html'
                        ],
                    'block_banner_top' =>
                        [
                            'type' => \Magento\Framework\DB\Ddl\Table::TYPE_TEXT,
                            'length' => 255,
                            'nullable' => false,
                            'comment' => 'block_banner_top'
                        ],
                ];

                foreach ($columns as $name => $definition) {
                    $connection->addColumn($tableName, $name, $definition);
                }
            }

        $installer->endSetup();
    }
}