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
class Foxyfeeds_Foxyfeeds_Model_Adminhtml_Source_Producturlgeneration
{
    const   METHOD_URL_PATH         = 1;
    const   METHOD_URL_KEY          = 2;
    const   METHOD_URL_KEY_HTML     = 3;
    const   METHOD_GET_PRODUCT_URL  = 4;
    const   METHOD_GET_URL_IN_STORE = 5;

    public function toOptionArray() {
        return array(
            array('value' => self::METHOD_URL_PATH, 'label' => Mage::helper('foxyfeeds')->__("FF base url + url_path")),
            array('value' => self::METHOD_URL_KEY, 'label' => Mage::helper('foxyfeeds')->__("FF base url + url_key")),
            array('value' => self::METHOD_URL_KEY_HTML, 'label' => Mage::helper('foxyfeeds')->__("FF base url + url_key + .html")),
            array('value' => self::METHOD_GET_PRODUCT_URL, 'label' => Mage::helper('foxyfeeds')->__("FF Method getProductUrl")),
            array('value' => self::METHOD_GET_URL_IN_STORE, 'label' => Mage::helper('foxyfeeds')->__("FF Method getUrlInStore")),
        );
    }
}