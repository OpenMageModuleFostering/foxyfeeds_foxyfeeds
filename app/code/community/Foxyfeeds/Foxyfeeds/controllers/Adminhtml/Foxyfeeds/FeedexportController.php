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
class Foxyfeeds_Foxyfeeds_Adminhtml_Foxyfeeds_FeedexportController extends Mage_Adminhtml_Controller_Action
{
    public function indexAction() {
        $this->loadLayout();
        $this->renderLayout();
    }

    public function viewAction(){
        $storeId = $this->getRequest()->getParam('store', false);
        if($storeId === false) {
            Mage::getSingleton('adminhtml/session')->addNotice(
                Mage::helper('foxyfeeds')->__('FF Please specify store.')
            );
            $this->_redirect('*/*/');
            return;
        }
        $this->loadLayout();
        $this->renderLayout();
    }

    public function truncateAction() {
        $helper = Mage::helper('foxyfeeds');

        Mage::getModel('foxyfeeds/feedexport_indexer')->truncateIndexTable();

        /** @var  $process Mage_Index_Model_Indexer */
        $process = Mage::getSingleton('index/indexer')->getProcessByCode('foxyfeeds_feed_export');
        $process->changeStatus(Mage_Index_Model_Process::STATUS_REQUIRE_REINDEX);

        Mage::getSingleton('adminhtml/session')->addSuccess($helper->__('FF The data has been deleted. Please reindex the export data.'));
        $this->_redirect('*/*/');
    }
}