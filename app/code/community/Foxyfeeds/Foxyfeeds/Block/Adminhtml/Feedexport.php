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
class Foxyfeeds_Foxyfeeds_Block_Adminhtml_Feedexport extends Mage_Adminhtml_Block_Widget_Grid_Container
{
    public function __construct()
    {
        $this->_blockGroup = 'foxyfeeds';
        $this->_controller = 'adminhtml_feedexport';
        $this->_headerText = Mage::helper('foxyfeeds')->__('FF Foxyfeeds Product Feed Export');

        parent::__construct();
        $this->_removeButton('add');

        $helper = Mage::helper('foxyfeeds');
        $this->_addButton('truncate_index_table', array(
            'label'     => $helper->__('FF Truncate Index Table'),
            'onclick'   => "
                if(confirm('{$helper->__('FF Warning, this will delete all entries and the data has to be reindexed. Proceed ?')}')) setLocation('{$this->getUrl('*/*/truncate')}')
            ",
            'class'     => 'delete'
        ));
    }
}