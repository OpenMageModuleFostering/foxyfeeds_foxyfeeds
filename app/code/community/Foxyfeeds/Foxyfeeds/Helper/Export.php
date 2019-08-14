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
class Foxyfeeds_Foxyfeeds_Helper_Export extends Mage_Core_Helper_Abstract
{
    const MAX_ATTRIBUTE_COUNT_FOR_LIVE_EXPORT = 40;

    protected $_storeId;
    protected $_siteId;
    protected $_customerGroupId;
    protected $_mediaUrl;
    protected $_webUrl;
    protected $_allCat;
    protected $_limit;
    protected $_last;
    protected $_blankProduct;
    protected $_configurableAttributes = array();
    protected $_imageBaseUrl;
    protected $_maxAdditionalImages;
    protected $_exportFields;
    protected $_currencyChange;
    protected $_replaceFields;
    protected $_parents;
    protected $_backendModel;
    protected $_chunkSize;
    protected $_stockId;
    /** @var  $_eavEntity Mage_Eav_Model_Entity_Abstract */
    protected $_eavEntity;

    public function __construct() {
        $this->_init();
    }

    /**
     * Handle status event
     *
     */
    public function startExport() {
        ini_set('max_execution_time', 7200);
        $store = Mage::app()->getRequest()->getParam('store', null);
        try {
            $this->_storeId = Mage::app()->getStore($store)->getId();
        } catch(Exception $e) {
            $this->_handleStoreException();
            return;
        }

        // start the xml output right now to output the exported products as soon as
        // each product has been processed
        header('Content-Type: text/xml; charset=utf-8');
        echo '<?xml version="1.0"?>';
        echo '<root><catalog>';

        $this->_export();

        echo "</catalog></root>";
    }

    /**
     * Display an error message based on current export (and therefore display) method
     * if an exception has occured during Mage::app()->getStore().
     */
    protected function _handleStoreException() {
        // The exception thrown by Mage::app()->getStore() has an empty message ...
        $xml = new SimpleXMLElement('<root></root>');
        $xml->addChild('error', 'Error retrieving store.');
        header('Content-Type: text/xml; charset=utf-8');
        echo $xml->asXML();
        exit();
    }

    /**
     * Callback function used for the collection iterator.
     * The function receives an array containing the fetched row from the database.
     * Saves the product to export in the _productData array.
     * @param $args array
     */
    public function indexedExportCallback($args)
    {
        $row = $args['row'];
        echo $row['product_data'];
    }

    /**
     * Export the products and return them as array.
     */
    protected function _export() {
        $flatEnabled = false;
        if(class_exists('Mage_Core_Model_App_Emulation')) {
            /* @var $flatHelper Mage_Catalog_Helper_Product_Flat */
            $flatHelper = Mage::helper('catalog/product_flat');
            /* @var $emulationModel Mage_Core_Model_App_Emulation */
            $emulationModel = Mage::getModel('core/app_emulation');
            if ($flatHelper) {
                $flatEnabled = method_exists($flatHelper, 'isAvailable') ? $flatHelper->isAvailable() : $flatHelper->isEnabled();
                if($flatEnabled) {
                    // Emulate admin environment to disable using flat model - otherwise we won't get global stats
                    // for all stores
                    $initialEnvironmentInfo = $emulationModel->startEnvironmentEmulation(0, Mage_Core_Model_App_Area::AREA_ADMINHTML);
                }
            }
        }

        $exportMethod = Mage::getStoreConfig('foxyfeeds_export/foxyfeeds_productfeed/foxyfeeds_export_method');

        // live export
        if(
            $exportMethod == Foxyfeeds_Foxyfeeds_Model_Adminhtml_Source_Exportmethod::EXPORT_METHOD_LIVE &&
            $this->getAttributeCount() <= Foxyfeeds_Foxyfeeds_Helper_Export::MAX_ATTRIBUTE_COUNT_FOR_LIVE_EXPORT
        ) {
            $collection = $this->getProductCollection($this->_storeId);

            // collection could not be loaded
            if($collection === null) {
                return null;
            }

            $chunkSize = $this->getChunkSize();
            $pages = ceil($collection->getSize() / $chunkSize);

            $collection->setPageSize($chunkSize);

            $backendModel = $this->getBackendModel();

            for($i = 1; $i <= $pages; $i++) {
                $collection->clear();
                $collection->setCurPage($i);
                foreach($collection as $item) {

                    $backendModel->afterLoad($item);
                    $productData = $this->getFullProductData($item);

                    $productXml = new SimpleXMLElement('<product></product>');
                    $this->productToXml($productData, $productXml);
                    $dom = dom_import_simplexml($productXml);
                    echo $dom->ownerDocument->saveXML($dom->ownerDocument->documentElement);
                }
            }
        } else { // indexed export
            $collection = Mage::getModel('foxyfeeds/feedexport_indexer')
                ->getCollection()
                ->addFieldToFilter('store_id', array('eq' => $this->_storeId));

            // output an error in case the collection is empty / no data found for store
            if($collection->getSize() == 0) {
                $xml = new SimpleXMLElement('<error></error>');
                $xml->addChild('message', 'No data found for store '.$this->_storeId.' in index table. Please reindex the data.');
                $dom = dom_import_simplexml($xml);
                echo $dom->ownerDocument->saveXML($dom->ownerDocument->documentElement);
                return;
            }

            $iterator = Mage::getSingleton('core/resource_iterator');
            $iterator->walk($collection->getSelect(), array(array($this, 'indexedExportCallback')));
        }

        // stop emulating admin store and set initial environment
        if ($flatEnabled) {
            $emulationModel->stopEnvironmentEmulation($initialEnvironmentInfo);
        }
    }

    /**
     * Check if another currency (other than the base currency) should be used. Displays an error if the
     * given currency could not be found.
     */
    protected function _initCurrencyChange() {
        $this->_currencyChange = null;
        $currencyCode = Mage::app()->getRequest()->getParam('currency', false);
        if ($currencyCode && $currencyCode != '') {
            $result = Mage::getModel('directory/currency')->getCurrencyRates(Mage::app()->getBaseCurrencyCode(), $currencyCode);
            if(count($result) === 0){
                $xml = new SimpleXMLElement('<root></root>');
                $xml->addChild('error', 'wrong currency');
                header('Content-Type: text/xml; charset=utf-8');
                echo $xml->asXML();
                exit();
            }
            $this->_currencyChange = $result[$currencyCode];
        }
    }

    /**
     * Get PHP's memory limit in bytes.
     * @return int|string
     */
    protected function getPhpMemoryLimitInBytes ()
    {
        $limit = ini_get('memory_limit');
        switch (substr ($limit, -1))
        {
            case 'M': case 'm': return (int)$limit * 1048576; // 1024 * 1024
            case 'K': case 'k': return (int)$limit * 1024;
            case 'G': case 'g': return (int)$limit * 1073741824; // 1024 * 1024 * 1024
            default: return $limit;
        }
    }

    /**
     * Initialize the export.
     *
     * @param int $storeId
     */
    protected function _init() {
        // Initialize the admin application
        Mage::app('admin');

        $this->_blankProduct = array();
        $this->_blankProduct['entity_id'] = '';
        $this->_blankProduct['sku'] = '';
        $this->_blankProduct['parent_id'] = '';
        $this->_blankProduct['variationTheme'] = '';
        $this->_blankProduct['name'] = '';
        $this->_blankProduct['description'] = '';
        $this->_blankProduct['price'] = '';
        $this->_blankProduct['categories'] = '';
        $this->_blankProduct['manufacturer'] = '';
        $this->_blankProduct['manufacturer_name'] = '';
        $this->_blankProduct['ff_product_url'] = '';
        $this->_blankProduct['ff_image_url'] = '';
        $this->_blankProduct['color'] = '';
        $this->_blankProduct['weight'] = '';
        for($i = 1; $i <= Mage::getStoreConfig('foxyfeeds_export/foxyfeeds_productfeed/foxyfeeds_imagenumber'); $i++) {
            $this->_blankProduct['ff_additional_image_'.$i] = '';
        }

        $specialExportFields = unserialize(Mage::getStoreConfig('foxyfeeds_export/foxyfeeds_productfeed/foxyfeeds_specialexportfields'));
        if(!empty($specialExportFields)) {
            foreach($specialExportFields as $field) {
                if (!empty($field['name'])) {
                    $this->_blankProduct[preg_replace('/\W/', '', $field['name'])] = $field['value'];
                }
            }
        }

        $this->_customerGroupId = Mage_Customer_Model_Group::NOT_LOGGED_IN_ID;

        $this->_buildCategoryTree();
        $this->_initCurrencyChange();
        $this->_initConfigurableAttributes();

        /** @var  $mediaConfig Mage_Catalog_Model_Product_Media_Config */
        $mediaConfig = Mage::getSingleton('catalog/product_media_config');
        $this->_imageBaseUrl = $mediaConfig->getBaseMediaUrl();

        $this->_maxAdditionalImages = Mage::getStoreConfig('foxyfeeds_export/foxyfeeds_productfeed/foxyfeeds_imagenumber');
        $this->_exportFields = unserialize(Mage::getStoreConfig('foxyfeeds_export/foxyfeeds_productfeed/foxyfeeds_exportfields'));
        $this->_replaceFields = unserialize(Mage::getStoreConfig('foxyfeeds_export/foxyfeeds_productfeed/foxyfeeds_replacefields'));

        $this->_initChunkSize();

        $this->_parents = array();
    }

    /**
     * Initialize the chunk size for the collection based on PHP's memory limit.
     */
    protected function _initChunkSize() {
        $memoryLimit = $this->getPhpMemoryLimitInBytes();

        switch($memoryLimit) {
            case $memoryLimit <= 33554432: // 32M
                $this->_chunkSize = 10;
                break;
            case $memoryLimit <= 67108864: // 64M
                $this->_chunkSize = 25;
                break;
            case $memoryLimit <= 134217728: // 128M
                $this->_chunkSize = 50;
                break;
            case $memoryLimit <= 268435456: // 256M
                $this->_chunkSize = 75;
                break;
            default:
                $this->_chunkSize = 100;
        }
    }

    /**
     * Build the category tree.
     */
    protected function _buildCategoryTree() {
        $this->_allCat = array();

        $categoryCollection = Mage::getModel('catalog/category')->getCollection()
            ->addAttributeToSelect('name')
            ->addAttributeToSort('path');

        foreach($categoryCollection as $category) {
            $path = $this->_getCategory($category->getPath());
            if($path !== 0) {
                $this->_allCat[$category->getPath()] = str_replace('Root Catalog', 'Home', $path . '>' . $category->getName());
            } else {
                $this->_allCat[$category->getPath()] = str_replace('Root Catalog', 'Home', $category->getName());
            }
        }
    }

    /**
     * Get the category id from a path.
     * @param   $key
     * @return  int | string
     */
    protected function _getCategory($key) {
        $return = 0;
        if (strpos($key, '/') != false) {
            $tmpKey = substr($key, 0, strpos($key, strrchr($key, '/')));
            if (isset($this->_allCat[$tmpKey])) {
                $return = $this->_allCat[$tmpKey];
            } else {
                $return = $this->_getCategory($tmpKey);
            }
        }
        return $return;
    }

    /**
     * Initialize the configurableAttributes array.
     * Array(
     *  [PRODUCT_ID] => ARRAY(
     *                      [ATTRIBUTE_CODE] => [FRONTEND_LABEL]
     *                  )
     * )
     */
    protected function _initConfigurableAttributes() {
        $this->_configurableAttributes = array();

        $connection = Mage::getSingleton('core/resource')->getConnection('core_read');

        $select = $connection->select()
            ->from(array('super_attribute' => Mage::getSingleton('core/resource')->getTableName('catalog/product_super_attribute')), array('attribute_id', 'product_id'))
            ->join(array('attribute' => Mage::getSingleton('core/resource')->getTableName('eav/attribute')),
                'attribute.attribute_id = super_attribute.attribute_id',
                array('attribute_code', 'frontend_label')
            );

        $result = $connection->fetchAll($select);

        foreach($result as $attribute) {
            if(!isset($this->_configurableAttributes[$attribute['product_id']])) {
                $this->_configurableAttributes[$attribute['product_id']] = array();
            }
            $this->_configurableAttributes[$attribute['product_id']][$attribute['attribute_code']] = $attribute['frontend_label'];
        }
    }

    /**
     * Get an array of attribute codes for all configurable attributes of a product ID.
     *
     * @param   int    $productId
     * @return  array
     */
    protected function _getConfigurableAttributes($productId) {
        $attributeOptions = array();
        if(isset($this->_configurableAttributes[$productId])) {
            foreach($this->_configurableAttributes[$productId] as $attributeCode => $label) {
                $attributeOptions[] = $label;
            }
        }
        return $attributeOptions;
    }

    /**
     * Get all attributes that need to be selected from the database.
     * @param $attributes array
     * @return array
     */
    protected function _getAttributesForSelect($attributes) {
        $attributesToSelect = array(
            'entity_id',
            'sku',
            'name',
            'description',
            'price',
            'manufacturer',
            'color',
            'weight',
            'media_gallery',
            'url_key',
            'url_path',
            'image',
            'type_id'
        );

        $attributeCodes = array();

        // save all available attribute codes
        foreach($attributes as $code => $attribute) {
            $attributeCodes[] = $code;
        }

        // add all attributes from the config if the attribute is a Magento attribute and it has
        // not already been added to the $attributesToSelect array
        foreach($this->_exportFields as $field) {
            $attrCode = $field['productattribute'];
            if(in_array($attrCode, $attributeCodes) && !in_array($attrCode, $attributesToSelect)) {
                $attributesToSelect[] = $attrCode;
            }
        }

        return $attributesToSelect;

    }

    /**
     * Sets store specific values for the export based on the parameter $storeId.
     * Returns false if an error occured / the store with id $storeId does not exist.
     * @param int $storeId
     * @return bool
     * @throws Mage_Core_Exception
     */
    protected function _setStoreValues($storeId) {
        try {
            $store = Mage::app()->getStore($storeId);
        } catch(Exception $e) {
            Mage::logException($e);
            return false;;
        }

        $this->_storeId = $store->getId();
        $this->_siteId = $store->getWebsiteId();
        $this->_webUrl = $store->getBaseUrl(Mage_Core_Model_Store::URL_TYPE_WEB);
        $this->_mediaUrl = $store->getBaseUrl(Mage_Core_Model_Store::URL_TYPE_MEDIA);

        $stockId = $store->getWebsite()->getStockId();
        $this->_stockId = (!empty($stockId)) ? $stockId : 1;

        return true;
    }

    /**
     * @param int $storeId
     * @param array $filterIds
     * @return Mage_Catalog_Model_Resource_Product_Collection|null
     * @throws Mage_Core_Exception
     */
    public function getProductCollection($storeId, array $filterIds = array()) {
        // Set the store values. If an error occurs, return null.
        if(!$this->_setStoreValues($storeId)) {
            return null;
        }

        /** @var  $collection Mage_Catalog_Model_Resource_Product_Collection */
        $collection = Mage::getModel('catalog/product')->getCollection();
        $this->_eavEntity = $collection->getEntity();
        $attributes = $collection->getEntity()->loadAllAttributes()->getAttributesByCode();

        $connection = $collection->getConnection();

        // addPriceData uses inner join to get the price data for a website_id and a customer_group id
        // so all products that are not associated to $this->_siteId and the customerGroupId (NOT LOGGED IN (0) by default)
        // are not included in this collection.
        // addPriceData is needed for getting the minimal price for bundels
        $collection
            ->addAttributeToSelect('*')
            ->addPriceData($this->_customerGroupId, $this->_siteId)
            ->joinField('qty',
                'cataloginventory/stock_item',
                'qty',
                'product_id=entity_id',
                $connection->quoteInto('{{table}}.stock_id=?', $this->_stockId),
                'left')
            ->joinField('min_sale_qty',
                'cataloginventory/stock_item',
                'min_sale_qty',
                'product_id=entity_id',
                $connection->quoteInto('{{table}}.stock_id=?', $this->_stockId),
                'left')
            ->joinField('max_sale_qty',
                'cataloginventory/stock_item',
                'max_sale_qty',
                'product_id=entity_id',
                $connection->quoteInto('{{table}}.stock_id=?', $this->_stockId),
                'left')
            ->joinField('is_in_stock',
                'cataloginventory/stock_item',
                'is_in_stock',
                'product_id=entity_id',
                $connection->quoteInto('{{table}}.stock_id=?', $this->_stockId),
                'left')
            ->setStoreId($this->_storeId);

        // add group price fields
        foreach($this->_exportFields as $field) {
            if(strpos($field['productattribute'], 'group_price') !== false) {
                $groupId = substr($field['productattribute'], 12);
                if(is_numeric($groupId)) {
                    $collection->joinField('group_price_'.$groupId,
                        'catalog/product_index_price',
                        'group_price',
                        'entity_id=entity_id',
                        $connection->quoteInto('{{table}}.customer_group_id=?', $groupId),
                        'left');
                }
            }
        }

        if(!empty($filterIds)) {
            $collection->addFieldToFilter('entity_id', array('in' => $filterIds));
        }

        $this->_backendModel = $collection->getResource()->getAttribute('media_gallery')->getBackend();

        return $collection;
    }

    /**
     * Get the url for the product based on the selected method in the system config or from the param $method.
     * @param Mage_Catalog_Model_Product $product
     * @param null|int $method
     * @return string
     */
    public function getProductUrl(Mage_Catalog_Model_Product $product, $method = null) {
        if($method === null) {
            $method = Mage::getStoreConfig('foxyfeeds_export/foxyfeeds_productfeed/product_url_generation_method');
        }

        switch($method) {
            case Foxyfeeds_Foxyfeeds_Model_Adminhtml_Source_Producturlgeneration::METHOD_URL_PATH:
                $url = $this->_webUrl . $product->getUrlPath();
                break;
            case Foxyfeeds_Foxyfeeds_Model_Adminhtml_Source_Producturlgeneration::METHOD_URL_KEY:
                $url = $this->_webUrl . $product->getUrlKey();
                break;
            case Foxyfeeds_Foxyfeeds_Model_Adminhtml_Source_Producturlgeneration::METHOD_URL_KEY_HTML:
                $url = $this->_webUrl . $product->getUrlKey() . '.html';
                break;
            case Foxyfeeds_Foxyfeeds_Model_Adminhtml_Source_Producturlgeneration::METHOD_GET_PRODUCT_URL:
                $url = $product->getProductUrl();
                break;
            case Foxyfeeds_Foxyfeeds_Model_Adminhtml_Source_Producturlgeneration::METHOD_GET_URL_IN_STORE:
                $url = $product->getUrlInStore($product, array('_store' => $this->_storeId));
                break;
            default:
                $url = '';
        }

        return $url;
    }

    /**
     * Get the current $item as array.
     * Returns Array(
     *      [ATTRIBUTE_CODE] => [VALUE]
     * )
     * @param   Mage_Catalog_Model_Product $item
     * @return  array
     */
    public function getFullProductData(Mage_Catalog_Model_Product $item) {
        $imageUrl = $this->_imageBaseUrl . $item->getImage();

        $isParent = 0;
        if ($item->getTypeId() == Mage_Catalog_Model_Product_Type::TYPE_CONFIGURABLE) {
            $isParent = 1;
        }

        $parentId = null;
        if ($item->getTypeId() == Mage_Catalog_Model_Product_Type::TYPE_SIMPLE) {
            $parentId = Mage::getModel('catalog/product_type_configurable')->getParentIdsByChild($item->getId());
            $parentId = (is_array($parentId) && !empty($parentId)) ? $parentId[0] : null;
        }

        $configurableAttributes = array();
        if($parentId && isset($this->_variationThemes[$parentId])) {
            $configurableAttributes = $this->_variationThemes[$parentId];
        } else if($isParent && $parentId === null) {
            $configurableAttributes = $this->_getConfigurableAttributes($item->getId());
            $configurableAttributes = (!empty($configurableAttributes)) ? implode('|', $configurableAttributes) : '';
            $this->_variationThemes[$item->getId()] = $configurableAttributes;
        }

        $colorText = ($this->_eavEntity->getAttribute('color')) ? $item->getAttributeText('color') : '';

        // set the value to the minimal price if the current product is a bundle, otherwise the product's price
        $calcPrice = ($item->getTypeId() == Mage_Catalog_Model_Product_Type::TYPE_BUNDLE) ? Mage::getModel('bundle/product_price')->getTotalPrices($item, 'min', true, false) : $item->getPrice();

        $rulePrice = Mage::getModel('catalogrule/rule')->calcProductPriceRule($item->setStoreId($this->_storeId),$calcPrice);
        $price = ($rulePrice) ? $rulePrice : $calcPrice;

        $product = $this->_blankProduct;

        $product['entity_id'] = $item->getId();
        $product['sku'] = $item->getSku();
        $product['parent_id'] = $parentId;
        $product['variationTheme'] = $configurableAttributes;
        $product['name'] = $this->_getCleanedStringForXml($item->getName());
        $product['description'] = $this->_getCleanedStringForXml($item->getDescription());

        $product['price'] = $price;
        if($this->_currencyChange) {
            $product['price'] = round($product['price']*$this->_currencyChange, 2);
        }

        $product['categories'] = $this->_getCategoryInformation($item);
        if($this->_eavEntity->getAttribute('manufacturer')) {
            $product['manufacturer'] = $this->_getCleanedStringForXml($item->getManufacturer());
            $product['manufacturer_name'] = $this->_getCleanedStringForXml($item->getAttributeText('manufacturer'));
        }
        $product['ff_product_url'] = $this->getProductUrl($item);
        $product['ff_image_url'] = $imageUrl;
        $product['color'] = ($colorText) ? $this->_getCleanedStringForXml($colorText) : null;
        $product['weight'] = $item->getWeight();

        $product = array_merge($product, $this->_getAdditionalImages($item));

        $product['is_parent'] = $isParent;

        $this->_addExportFields($product, $item);

        if(!empty($this->_replaceFields) && !$isParent && $parentId !== null) {
            $this->_replaceFields($product, $parentId);
        }

        return $product;
    }

    /**
     * Get the additional images for a product
     * @param Mage_Catalog_Model_Product $product
     * @return array
     */
    protected function _getAdditionalImages(Mage_Catalog_Model_Product $product) {
        $counter = 1;
        $productData = array();
        $mediaGalleryImages = array();
        if($product->getMediaGalleryImages()) {
            $mediaGalleryImages = $product->getMediaGalleryImages();
        }
        foreach ($mediaGalleryImages as $image) {
            // break if the maximum amount of additional images has been reached
            if ($counter > $this->_maxAdditionalImages) {
                break;
            }

            // ignore the base image; it has already been added
            if ($image->getFile() == $product->getImage()) {
                continue;
            }

            $productData['ff_additional_image_' . $counter] = $this->_imageBaseUrl . $image->getFile();
            $counter++;
        }

        return $productData;
    }

    /**
     * Add all fields to a product that need to be exported
     * @param $product
     * @param Mage_Catalog_Model_Product $item
     */
    protected function _addExportFields(&$product, Mage_Catalog_Model_Product $item) {
        foreach($this->_exportFields as $field) {
            $code = $field['productattribute'];
            if(strpos($code, 'group_price') !== false) {
                $groupId = substr($code, 12);
                $customerGroup = Mage::getModel('customer/group')->load($groupId);
                $groupCode = str_replace(' ', '_', $customerGroup->getCustomerGroupCode());
                $product['group_price_'.$groupCode] = $item->getData('group_price_'.$customerGroup->getId());
            } else {
                switch($code) {
                    // ignore
                    case 'parent_id':
                        break;
                    case 'qty':
                        $product[$code] = $item->getQty();
                        break;
                    case 'stock_status':
                        $product[$code] = $item->getIsInStock();
                        break;
                    case 'min_sale_qty':
                    case 'max_sale_qty':
                    case 'tax_class_id':
                        $product[$code] = $item->getData($code);
                        break;
                    default:
                        $attributeText = ($item->getResource()->getAttribute($code)) ? $item->getAttributeText($code) : false;
                        if(is_array($attributeText)) {
                            $attributeText = implode(',',$attributeText);
                        }
                        $product[$code] = ($attributeText) ? $this->_getCleanedStringForXml($attributeText) : $this->_getCleanedStringForXml($item->getData($code));
                }
            }
        }
    }

    /**
     * Replace all selected fields with the parent's values.
     * @param $product
     * @param $parentId
     */
    protected function _replaceFields(&$product, $parentId) {
        $parent = $this->_getParentById($parentId);
        if($parent !== null && $parent->getId()) {
            $parentImages = $this->_getAdditionalImages($parent);
            foreach($this->_replaceFields as $field) {
                $code = $field['productattribute'];
                if(strpos($code, 'group_price') !== false) {
                    $groupId = substr($code, 12);
                    $customerGroup = Mage::getModel('customer/group')->load($groupId);
                    $groupPrices = $parent->getData('group_price');
                    foreach($groupPrices as $groupPrice) {
                        if($groupPrice['cust_group'] == $groupId) {
                            $groupCode = str_replace(' ', '_', $customerGroup->getCustomerGroupCode());
                            $product['group_price_'.$groupCode] = $groupPrice['price'];
                        }
                    }
                } else if(strpos($code, 'ff_additional_image') !== false && isset($parentImages[$code])) {
                    $product[$code] = $parentImages[$code];
                } else {
                    switch($code) {
                        case 'categories':
                            $product['categories'] = $this->_getCategoryInformation($parent);
                            break;
                        case 'ff_product_url':
                            $product[$code] = $this->getProductUrl($parent);
                            break;
                        case 'ff_image_url':
                            $product[$code] = $this->_imageBaseUrl . $parent->getImage();
                            break;
                        case 'qty':
                            $product[$code] = $parent->getStockItem()->getQty();
                            break;
                        case 'stock_status':
                            $product[$code] = $parent->getStockItem()->getIsInStock();
                            break;
                        case 'min_sale_qty':
                            $product[$code] = $parent->getStockItem()->getMinSaleQty();
                            break;
                        case 'max_sale_qty':
                            $product[$code] = $parent->getStockItem()->getMaxSaleQty();
                            break;
                        case 'tax_class_id':
                            $product[$code] = $parent->getData($code);
                            break;
                        default:
                            $attributeText = ($parent->getResource()->getAttribute($code)) ? $parent->getAttributeText($code) : false;
                            if(is_array($attributeText)) {
                                $attributeText = implode(',',$attributeText);
                            }
                            $product[$code] = ($attributeText) ? $this->_getCleanedStringForXml($attributeText) : $this->_getCleanedStringForXml($parent->getData($code));
                    }
                }
            }
        }
    }

    /**
     * Adds the values of the $product array to the $xml structure.
     * @param   array               $product
     * @param   SimpleXMLElement    $xml
     */
    public function productToXml(array $product, SimpleXMLElement $xml) {
        foreach($product as $code => $value) {
            if(is_array($value)) {
                $node = $xml->addChild($code);
                $this->productToXml($value, $node);
            } else if (is_string($value) || is_numeric($value) || is_bool($value) || is_null($value)) {
                $xml->addChild($code, htmlspecialchars($value));
            }
        }
    }

    /**
     * Get the category information for a product.
     * @param Mage_Catalog_Model_Product $product
     * @return string
     */
    protected function _getCategoryInformation(Mage_Catalog_Model_Product $product) {
        $arrPath = array();

        /** @var  $category Mage_Catalog_Model_Category */
        foreach($product->getCategoryCollection() as $category) {
            if(isset($this->_allCat[$category->getPath()])) {
                $arrPath[] = $this->_allCat[$category->getPath()];
            }
        }

        return ltrim(implode(',', $arrPath), '>');
    }

    /**
     * Get a product by its id. Save the loaded product to an array to cache it.
     * It is used for parent products only so these products do not need to be loaded by each
     * of its child products.
     * @param $parentId
     * @return mixed
     */
    protected function _getParentById($parentId) {
        if(!isset($this->_parents[$parentId])) {
            $parent = Mage::getModel('catalog/product')->load($parentId);
            $this->_parents[$parentId] = ($parent->getId()) ? $parent : null;
        }
        return $this->_parents[$parentId];
    }

    /**
     * Clean a string for usage in XML.
     * @param $string
     * @return mixed
     */
    protected function _getCleanedStringForXml($string) {
        return $this->_utf8ForXml(html_entity_decode($string));
    }

    /**
     * Replace all UTF-8 characters that are not allowed in XML with a space.
     * @param $string
     * @return mixed
     */
    protected function _utf8ForXml($string) {
        return preg_replace ('/[^\x{0009}\x{000a}\x{000d}\x{0020}-\x{D7FF}\x{E000}-\x{FFFD}]+/u', ' ', $string);
    }

    /**
     * @return mixed
     */
    public function getStoreId()
    {
        return $this->_storeId;
    }

    public function setStoreVariables($storeId) {
        try {
            $store = Mage::app()->getStore($storeId);
            $this->_storeId = $storeId;
            $this->_siteId = $store->getWebsiteId();
            $this->_webUrl = $store->getBaseUrl(Mage_Core_Model_Store::URL_TYPE_WEB);
            $this->_mediaUrl = $store->getBaseUrl(Mage_Core_Model_Store::URL_TYPE_MEDIA);
        } catch(Exception $e) {
            Mage::logException($e);
        }
    }

    /**
     * @return mixed
     */
    public function getChunkSize()
    {
        if(empty($this->_chunkSize)) {
            $this->_initChunkSize();
        }
        return $this->_chunkSize;
    }

    /**
     * @param mixed $chunkSize
     */
    public function setChunkSize($chunkSize)
    {
        $this->_chunkSize = $chunkSize;
    }

    /**
     * @return mixed
     */
    public function getAttributeCount()
    {
        return (!empty($this->_exportFields)) ? count($this->_exportFields) : 0;
    }

    /**
     * @return mixed
     */
    public function getBackendModel()
    {
        return $this->_backendModel;
    }
}