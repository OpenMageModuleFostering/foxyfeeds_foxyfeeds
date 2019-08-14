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
class Foxyfeeds_Foxyfeeds_Block_Adminhtml_Feedexport_View extends Mage_Adminhtml_Block_Widget_Form_Container
{
    public function __construct()
    {
        parent::__construct();

        $this->_objectId = 'product_id';
        $this->_blockGroup = 'foxyfeeds';
        $this->_controller = 'adminhtml_feedexport';
        $this->_mode = 'view';

        $this->_removeButton('save');
        $this->_removeButton('delete');
        $this->_removeButton('reset');
    }

    public function getHeaderText()
    {
        $productId = $this->getRequest()->getParam('product_id');
        $storeId = $this->getRequest()->getParam('store', 1);
        $data = Mage::getResourceModel('foxyfeeds/feedexport_indexer')->getDataForProductIdAndStoreId($productId, $storeId);
        Mage::register('ff_product_feed_data', $data);
        return Mage::helper('foxyfeeds')->__('FF Showing feed export data for product %s and store %s - Created at: %s', $productId, $storeId, $data['created_at']);
    }

    public function getBackUrl()
    {
        $storeId = $this->getRequest()->getParam('store');
        return $this->getUrl('*/*/', array('store' => $storeId));
    }
}