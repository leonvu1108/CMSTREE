<?php
/**
 * Copyright © Magento, Inc. All rights reserved.
 * See COPYING.txt for license details.
 */
namespace Likipe\Cmspage\Model\ResourceModel\Page;

use Magento\Framework\Data\Tree\Dbp;
use Magento\Framework\EntityManager\MetadataPool;
use Magento\Cms\Api\Data\PageInterface;
use Magento\Cms\Model\Page as CmsPage;
/**
 * @api
 * @SuppressWarnings(PHPMD.CouplingBetweenObjects)
 * @since 100.0.2
 */
class Tree extends Dbp
{
    const ID_FIELD = 'id';

    const PATH_FIELD = 'path';

    const ORDER_FIELD = 'order';

    const LEVEL_FIELD = 'level';

    /**
     * @var \Magento\Framework\Event\ManagerInterface
     */
    private $_eventManager;

    /**
     * @var \Magento\Catalog\Model\Attribute\Config
     */
    private $_attributeConfig;

    /**
     * @var \Magento\Cms\Model\ResourceModel\Page\Collection\Factory
     */
    private $_collectionFactory;

    /**
     * Pages resource collection
     *
     * @var Collection
     */
    protected $_collection;

    /**
     * Join URL rewrites data to collection flag
     *
     * @var boolean
     */
    protected $_joinUrlRewriteIntoCollection = false;

    /**
     * Inactive Pages ids
     *
     * @var array
     */
    protected $_inactivePageIds = null;

    /**
     * Store id
     *
     * @var integer
     */
    protected $_storeId = null;

    /**
     * @var \Magento\Framework\App\ResourceConnection
     */
    protected $_coreResource;

    /**
     * Store manager
     *
     * @var \Magento\Store\Model\StoreManagerInterface
     */
    protected $_storeManager;

    /**
     * Cache
     *
     * @var \Magento\Framework\App\CacheInterface
     */
    protected $_cache;


    /**
     * @var MetadataPool
     * @since 101.0.0
     */
    protected $metadataPool;

    protected function _construct()
    {
        $this->_init('cms_page', 'page_id');
    }

    /**
     * Tree constructor.
     * @param \Magento\Framework\App\CacheInterface $cache
     * @param \Magento\Store\Model\StoreManagerInterface $storeManager
     * @param \Magento\Framework\App\ResourceConnection $resource
     * @param \Magento\Framework\Event\ManagerInterface $eventManager
     * @param Collection\Factory $collectionFactory
     */
    public function __construct(
        \Magento\Framework\Model\Context $context,
        \Magento\Framework\Registry $registry,
        \Magento\Framework\App\Config\ScopeConfigInterface $scopeConfig,

        \Magento\Framework\App\CacheInterface $cache,
        \Magento\Store\Model\StoreManagerInterface $storeManager,
        \Magento\Framework\App\ResourceConnection $resource,
        \Magento\Framework\Event\ManagerInterface $eventManager,
        \Magento\Cms\Model\ResourceModel\Page\CollectionFactory $collectionFactory
    ) {
        $this->_cache = $cache;
        $this->_storeManager = $storeManager;
        $this->_coreResource = $resource;
        parent::__construct(
            $resource->getConnection('cms'),
            $resource->getTableName('cms_page'),
            [
                Dbp::ID_FIELD => 'page_id',
                Dbp::PATH_FIELD => 'path',
                Dbp::ORDER_FIELD => 'position',
                Dbp::LEVEL_FIELD => 'level'
            ],
        $context, $registry, $resource);
        $this->_eventManager = $eventManager;
        $this->_collectionFactory = $collectionFactory;
    }

    /**
     * Set store id
     *
     * @param integer $storeId
     * @return $this
     */
    public function setStoreId($storeId)
    {
        $this->_storeId = (int)$storeId;
        return $this;
    }

    /**
     * Return store id
     *
     * @return integer
     */
    public function getStoreId()
    {
        if ($this->_storeId === null) {
            $this->_storeId = $this->_storeManager->getStore()->getId();
        }
        return $this->_storeId;
    }

    /**
     * Add data to collection
     *
     * @param Collection $collection
     * @param boolean $sorted
     * @param array $exclude
     * @param boolean $toLoad
     * @param boolean $onlyActive
     * @return $this
     * @SuppressWarnings(PHPMD.CyclomaticComplexity)
     * @SuppressWarnings(PHPMD.NPathComplexity)
     */
    public function addCollectionData(
        $collection = null,
        $sorted = false,
        $exclude = [],
        $toLoad = true,
        $onlyActive = false
    ) {
        if ($collection === null) {
            $collection = $this->getCollection($sorted);
        } else {
            $this->setCollection($collection);
        }

        if (!is_array($exclude)) {
            $exclude = [$exclude];
        }

        $nodeIds = [];
        foreach ($this->getNodes() as $node) {
            if (!in_array($node->getId(), $exclude)) {
                $nodeIds[] = $node->getId();
            }
        }

        $collection->addIdFilter($nodeIds);

        if ($this->_joinUrlRewriteIntoCollection) {
            $collection->joinUrlRewrite();
            $this->_joinUrlRewriteIntoCollection = false;
        }

        if ($toLoad) {
            $collection->load();

            foreach ($collection as $page) {
                if ($this->getNodeById($page->getId())) {
                    $this->getNodeById($page->getId())->addData($page->getData());
                }
            }

            foreach ($this->getNodes() as $node) {
                if (!$collection->getItemById($node->getId()) && $node->getParent()) {
                    $this->removeNode($node);
                }
            }
        }

        return $this;
    }

    /**
     * Add inactive pages ids
     *
     * @param mixed $ids
     * @return $this
     */
    public function addInactivePagesIds($ids)
    {
        if (!is_array($this->_inactivePageIds)) {
            $this->_initInactivePagesIds();
        }
        $this->_inactivePageIds = array_merge($ids, $this->_inactivePageIds);
        return $this;
    }

    /**
     * Retrieve inactive pages ids
     *
     * @return $this
     */
    protected function _initInactivePagesIds()
    {
        $this->_inactivePageIds = [];
        $this->_eventManager->dispatch('cms_page_tree_init_inactive_page_ids', ['tree' => $this]);
        return $this;
    }

    /**
     * Retrieve inactive pages ids
     *
     * @return array
     */
    public function getInactivePagesIds()
    {
        if (!is_array($this->_inactivePageIds)) {
            $this->_initInactivePagesIds();
        }

        return $this->_inactivePageIds;
    }

    /**
     * Return disable page ids
     *
     * @param Collection $collection
     * @param array $allIds
     * @return array
     */
    protected function _getDisabledIds($collection, $allIds)
    {
        $storeId = $this->_storeManager->getStore()->getId();
        $this->_inactiveItems = $this->getInactivePagesIds();

        $disabledIds = [];

        foreach ($allIds as $id) {
            $parents = $this->getNodeById($id)->getPath();
            foreach ($parents as $parent) {
                if (!$this->_getItemIsActive($parent->getId(), $storeId)) {
                    $disabledIds[] = $id;
                    continue;
                }
            }
        }
        return $disabledIds;
    }

    /**
     * Check is page items active
     *
     * @param int $id
     * @return boolean
     * @SuppressWarnings(PHPMD.BooleanGetMethodName)
     */
    protected function _getItemIsActive($id)
    {
        if (!in_array($id, $this->_inactiveItems)) {
            return true;
        }
        return false;
    }

    /**
     * Get pages collection
     *
     * @param boolean $sorted
     * @return Collection
     */
    public function getCollection($sorted = false)
    {
        if ($this->_collection === null) {
            $this->_collection = $this->_getDefaultCollection($sorted);
        }
        return $this->_collection;
    }

    /**
     * Clean unneeded collection
     *
     * @param Collection|array $object
     * @return void
     */
    protected function _clean($object)
    {
        if (is_array($object)) {
            foreach ($object as $obj) {
                $this->_clean($obj);
            }
        }
        unset($object);
    }

    /**
     * Enter description here...
     *
     * @param Collection $collection
     * @return $this
     */
    public function setCollection($collection)
    {
        if ($this->_collection !== null) {
            $this->_clean($this->_collection);
        }
        $this->_collection = $collection;
        return $this;
    }

    /**
     * Enter description here...
     *
     * @param boolean $sorted
     * @return Collection
     */
    protected function _getDefaultCollection($sorted = false)
    {
        $this->_joinUrlRewriteIntoCollection = true;
        $collection = $this->_collectionFactory->create();

        if ($sorted) {
            if (is_string($sorted)) {
                // $sorted is supposed to be attribute name
                $collection->addAttributeToSort($sorted);
            } else {
                $collection->addAttributeToSort('name');
            }
        }

        return $collection;
    }

    /**
     * Move tree after
     *
     * @return $this
     */
    protected function _afterMove()
    {
        $this->_cache->clean([\Magento\Cms\Model\Page::CACHE_TAG]);
        return $this;
    }

    /**
     * Load array of page parents
     *
     * @param string $path
     * @param bool $addCollectionData
     * @param bool $withRootNode
     * @return array
     */
    public function loadBreadcrumbsArray($path, $addCollectionData = true, $withRootNode = false)
    {
        $pathIds = explode('/', $path);
        if (!$withRootNode) {
            array_shift($pathIds);
        }
        $result = [];
        if (!empty($pathIds)) {
            if ($addCollectionData) {
                $select = $this->_createCollectionDataSelect(false);
            } else {
                $select = clone $this->_select;
            }
            $select->where(
                'e.entity_id IN(?)',
                $pathIds
            )->order(
                $this->_conn->getLengthSql('e.path') . ' ' . \Magento\Framework\DB\Select::SQL_ASC
            );
            $result = $this->_conn->fetchAll($select);
            $this->_updateAnchorProductCount($result);
        }
        return $result;
    }

}
