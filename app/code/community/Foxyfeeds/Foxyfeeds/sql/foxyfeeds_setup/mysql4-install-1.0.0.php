<?php
/**
 *
 * NOTICE OF LICENSE
 *
 * This source file is subject to the GNU General Public License (GPL 3)
 * that is bundled with this package in the file LICENSE.txt
 *
 * DISCLAIMER
 *
 * Do not edit or add to this file if you wish to upgrade Foxyfeeds_Foxyfeeds to newer
 * versions in the future. If you wish to customize Foxyfeeds_Foxyfeeds for your
 * needs please refer to http://www.foxyfeeds.com for more information.
 *
 * @category        Foxyfeeds
 * @package         Foxyfeeds_Foxyfeeds
 * @copyright       Copyright (c) 2012 <info@foxyfeeds.com> - www.foxyfeeds.com
 * @author          Björn Wehner <info@foxyfeeds.com>
 * @license         <http://www.gnu.org/licenses/> GNU General Public License (GPL 3)
 * @link            http://www.foxyfeeds.com
 */

$installer = $this;

$installer->startSetup();
$adapter = $installer->getConnection();

/**
 * Create table 'foxyfeeds/product_feed_export_index'
 */
$table = $adapter->newTable($installer->getTable('foxyfeeds/product_feed_export_index'))
    ->addColumn('product_id', Varien_Db_Ddl_Table::TYPE_INTEGER, 10, array(
        'unsigned'      => true,
        'nullable'      => false,
        'primary'       => true,
    ), 'product_id')
    ->addColumn('store_id', Varien_Db_Ddl_Table::TYPE_INTEGER, 5, array(
        'nullable'      => false,
        'unsigned'      => true,
        'primary'       => true,
    ), 'store_id')
    ->addColumn('sku', Varien_Db_Ddl_Table::TYPE_VARCHAR, 255, array(
        'nullable'      => false,
    ), 'sku')
    ->addColumn('created_at', Varien_Db_Ddl_Table::TYPE_DATETIME, null, array(
        'nullable'      => false,
    ), 'created_at')
    ->addColumn('product_data', Varien_Db_Ddl_Table::TYPE_TEXT, null, array(
        'nullable'       => false,
    ), 'product_data')
    ->addForeignKey('FK_FF_FEED_EXPORT_PRODUCT_ID',
        'product_id',
        $installer->getTable('catalog/product'),
        'entity_id',
        Varien_Db_Ddl_Table::ACTION_CASCADE,
        Varien_Db_Ddl_Table::ACTION_CASCADE
    )
    ->addForeignKey('FK_FF_FEED_EXPORT_STORE_ID',
        'store_id',
        $installer->getTable('core/store'),
        'store_id',
        Varien_Db_Ddl_Table::ACTION_CASCADE,
        Varien_Db_Ddl_Table::ACTION_CASCADE
    )
    ->addIndex('IDX_FF_FEED_EXPORT_PRODUCT_ID', 'product_id')
    ->addIndex('IDX_FF_FEED_EXPORT_STORE_ID', 'store_id')
    ->addIndex('IDX_FF_FEED_EXPORT_SKU', 'sku');
$adapter->createTable($table);

$installer->endSetup();