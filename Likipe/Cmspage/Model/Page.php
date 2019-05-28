<?php 
namespace Likipe\Cmspage\Model;


class Page extends \Magento\Cms\Model\Page
{
    /**
     * Id of page tree root
     */
    const TREE_ROOT_ID = 1;

    /**
     * Cms tree model
     *
     * @var \Magento\Cms\Model\ResourceModel\Cms\Tree
     */
    public $_treeModel = null;

    /**
     * @var PageRepositoryInterface
     */
    protected $pageRepository;

    /**
     * Cms tree factory
     *
     * @var \Magento\Cms\Model\ResourceModel\Cms\TreeFactory
     */
    public $_cmsTreeFactory;

    public function __construct(
        \Magento\Framework\Model\Context $context,
        \Magento\Framework\Registry $registry,
        \Likipe\Cmspage\Model\ResourceModel\Page\Tree $cmsTreeResource,
        \Likipe\Cmspage\Model\ResourceModel\Page\TreeFactory $cmsTreeFactory,
        \Magento\Cms\Api\PageRepositoryInterface $pageRepository,
        \Magento\Framework\App\Config\ScopeConfigInterface $scopeConfig,
        \Magento\Framework\Model\ResourceModel\AbstractResource $resource = null,
        \Magento\Framework\Data\Collection\AbstractDb $resourceCollection = null,

        array $data = []
    ) {
        $this->_scopeConfig = $scopeConfig;
        $this->_treeModel = $cmsTreeResource;
        $this->_cmsTreeFactory = $cmsTreeFactory;
        $this->pageRepository = $pageRepository;
        parent::__construct($context, $registry, $resource, $resourceCollection, $data);
    }

    /**
     * Retrieve page tree model
     *
     * @return \Likipe\Cmspage\Model\ResourceModel\Page\Tree
     */
    public function getTreeModel()
    {
        return $this->_cmsTreeFactory->create();
    }

    /**
     * Enter description here...
     *
     * @return \Magento\Cms\Model\ResourceModel\Page\Tree
     */
    public function getTreeModelInstance()
    {
        return $this->_treeModel;
    }
    /**
     * Move page
     *
     * @param  int $parentId new parent page id
     * @param  null|int $afterPageId page id after which we have put current page
     * @return $this
     * @throws \Magento\Framework\Exception\LocalizedException|\Exception
     */
    public function move($parentId, $afterPageId)
    {
        /**
         * Validate new parent page id. (page model is used for backward
         * compatibility in event params)
         */
        try {
            $parent =$this->pageRepository->getById($parentId, $this->getStoreId());
        } catch (NoSuchEntityException $e) {
            throw new \Magento\Framework\Exception\LocalizedException(
                __(
                    'Sorry, but we can\'t find the new parent page you selected.'
                ),
                $e
            );
        }

        if (!$this->getId()) {
            throw new \Magento\Framework\Exception\LocalizedException(
                __('Sorry, but we can\'t find the new page you selected.')
            );
        } elseif ($parent->getId() == $this->getId()) {
            throw new \Magento\Framework\Exception\LocalizedException(
                __(
                    'We can\'t move the page because the parent page name matches the child page name.'
                )
            );
        }

        /**
         * Setting affected page ids for third party engine index refresh
         */
        $this->setMovedPageId($this->getId());
        $oldParentId = $this->getParentId();
        $oldParentIds = $this->getParentIds();

        $eventParams = [
            $this->_eventObject => $this,
            'parent' => $parent,
            'page_id' => $this->getId(),
            'prev_parent_id' => $oldParentId,
            'parent_id' => $parentId,
        ];
        $moveComplete = false;
        $this->_getResource()->beginTransaction();
        try {
            $this->_eventManager->dispatch($this->_eventPrefix . '_move_before', $eventParams);
            $this->getResource()->changeParent($this, $parent, $afterPageId);
            $this->_eventManager->dispatch($this->_eventPrefix . '_move_after', $eventParams);
            $this->_getResource()->commit();
            $moveComplete = true;

            // Set data for indexer
            //$this->setAffectedPageIds([$this->getId(), $oldParentId, $parentId]);
        } catch (\Exception $e) {
            $this->_getResource()->rollBack();
            throw $e;
        }
        if ($moveComplete) {
           $this->_eventManager->dispatch('page_move', $eventParams);
        }

        return $this;
    }

    public function getParentIds()
    {
        return array_diff($this->getPathIds(), array($this->getId()));
    }

    public function getPathIds()
    {
        $ids = $this->getData('path_ids');
        if (is_null($ids)) {
            $ids = explode('/', $this->getPath());
            $this->setData('path_ids', $ids);
        }

        return $ids;
    }

    public function getParentPage()
    {
        if (!$this->hasData('parent_page')) {
            $this->setData('parent_page', Mage::getModel('cms/page')->load($this->getParentId()));
        }

        return $this->_getData('parent_page');
    }

    public function formatUrlKey($str)
    {
        $str = Mage::helper('core')->removeAccents($str);
        $urlKey = preg_replace('#[^0-9a-z]+#i', '-', $str);
        $urlKey = strtolower($urlKey);
        $urlKey = trim($urlKey, '-');

        return $urlKey;
    }

    public function loadRootByStoreId($storeId)
    {
        $rootId = $this->_getResource()->getStoreRootId($storeId);
        if ($rootId) {
            $this->load($rootId);
        }

        return $this;
    }

    public function getChildren()
    {
        return $this->getCollection()->addChildrenFilter($this);
    }

    public function getUrl()
    {
        return Mage::getBaseUrl() . $this->getIdentifier();
    }

    public function isRoot()
    {
        return 0 === (int) $this->getParentId();
    }

    public static function createDefaultStoreRootPage($storeId, $data = array())
    {
        $newRoot = Mage::getModel('cms/page')->setData(array(
            'title'         => Mage::helper('cms')->__('Home'),
            'root_template' => 'two_columns_right',
            'store_id'      => $storeId,
            'parent_id'     => 0,
            'level'         => 1,
        ))
        ->addData($data) // will override default data
        ->setCreateDefaultPermission(true)
        ->save();

        return $newRoot;
    }

    protected function _afterLoad()
    {
        parent::_afterLoad();
        $identifiers = explode('/', $this->getIdentifier());
        $this->setUrlKey(array_pop($identifiers));

        return $this;
    }

    public function getPageId()
    {
        if ($this->getPageId()) {
            return $this->getPageId();
        }

        return TREE_ROOT_ID;
    }
}