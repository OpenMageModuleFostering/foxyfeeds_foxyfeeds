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
 * @author          Bj�rn Wehner <info@foxyfeeds.com>
 * @license         <http://www.gnu.org/licenses/> GNU General Public License (GPL 3)
 * @link            http://www.foxyfeeds.com
 */
class Foxyfeeds_Foxyfeeds_Model_Adminhtml_Source_Trackingmethod
{
    const   TRACKING_METHOD_IMAGE   = 1;
    const   TRACKING_METHOD_JS      = 2;

    public function toOptionArray() {
        return array(
            array('value' => self::TRACKING_METHOD_IMAGE, 'label' => Mage::helper('foxyfeeds')->__('FF Image')),
            array('value' => self::TRACKING_METHOD_JS, 'label' => Mage::helper('foxyfeeds')->__('FF JavaScript')),
        );
    }
}