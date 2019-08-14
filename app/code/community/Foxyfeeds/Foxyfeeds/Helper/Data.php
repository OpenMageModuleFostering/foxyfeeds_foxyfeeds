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
 * @subpackage		helper
 * @copyright       Copyright (c) 2013 <info@foxyfeeds.com> - www.foxyfeeds.com
 * @author          Björn Wehner <info@foxyfeeds.com>
 * @license         <http://www.gnu.org/licenses/> GNU General Public License (GPL 3)
 * @link            http://www.foxyfeeds.com
 */

class Foxyfeeds_Foxyfeeds_Helper_Data extends Mage_Core_Helper_Abstract {

	public function __construct($root = 'root') {

	}

	public function createXml() {
		if (Mage::getStoreConfig('foxyfeeds_export/foxyfeeds_productfeed/foxyfeeds_useExport')) {
			$password = Mage::getStoreConfig('foxyfeeds_export/foxyfeeds_productfeed/foxyfeeds_password');
            $paramPassword = Mage::app()->getRequest()->getParam('password', false);
			if ($password == '' || ($paramPassword AND $paramPassword == $password)) {
                Mage::helper('foxyfeeds/export')->startExport();
				exit();
			}
		}
		return;
	}

    public function getAllStoreIds() {
        $storeIds = array();

        /** @var  $website Mage_Core_Model_Website */
        foreach(Mage::app()->getWebsites() as $website) {
            $storeIds = array_merge($storeIds, $website->getStoreIds());
        }

        return $storeIds;
    }
}

?>