<?php
namespace Likipe\Cmspage\Block\Adminhtml\Cms\Page;

use Magento\Framework\View\Element\Template;
use Magento\Framework\Data\Tree\Node;
use Magento\Store\Model\Store;

class Tree extends Template
{
    /**
     * @var string
     */
    protected $_template = 'cms/page/tree.phtml';

    /**
     * @var \Magento\Backend\Model\Auth\Session
     */
    protected $_backendSession;

    /**
     * @var \Magento\Framework\DB\Helper
     */
    protected $_resourceHelper;

    /**
     * @var \Magento\Framework\Json\EncoderInterface
     */
    protected $_jsonEncoder;

    /**
     * Store manager
     *
     * @var StoreManagerInterface
     */
    protected $_storeManager;

    protected $_withChildrenCount;

    /**
     * Core registry
     *
     * @var \Magento\Framework\Registry
     */
    protected $_coreRegistry = null;

    protected $_pageTree = null;
    /**
     * @param \Magento\Backend\Block\Template\Context $context
     * @param \Likipe\Cmspage\Model\ResourceModel\Page\Tree $pageTree,
     * @param \Magento\Framework\Registry $registry
     * @param \Magento\Cms\Model\PageFactory $pageFactory,
     * @param \Magento\Framework\Json\EncoderInterface $jsonEncoder
     * @param \Magento\Framework\DB\Helper $resourceHelper
     * @param \Magento\Backend\Model\Auth\Session $backendSession
     * @param array $data
     */
    public function __construct(
        \Magento\Backend\Block\Template\Context $context,
        \Likipe\Cmspage\Model\ResourceModel\Page\Tree $pageTree,
        \Magento\Cms\Model\PageFactory $pageFactory,
        \Magento\Cms\Model\ResourceModel\Page\CollectionFactory $collectionFactory,
        \Magento\Framework\View\Model\PageLayout\Config\BuilderInterface $pageLayoutBuilder,
        \Magento\Framework\DB\Helper $resourceHelper,
        \Magento\Backend\Model\Auth\Session $backendSession,
        \Magento\Store\Model\StoreManagerInterface $storeManager,
        \Magento\Framework\Registry $registry,
        \Magento\Framework\Json\EncoderInterface $jsonEncoder,
        array $data = []
    ) {
        $this->_collectionFactory = $collectionFactory;
        $this->_cmsPageFactory = $pageFactory;
        $this->_resourceHelper = $resourceHelper;
        $this->pageLayoutBuilder = $pageLayoutBuilder;
        $this->_backendSession = $backendSession;
        $this->_storeManager = $storeManager;
        $this->_coreRegistry = $registry;
        $this->_pageTree = $pageTree;
        $this->_jsonEncoder = $jsonEncoder;
        $this->_withChildrenCount = true;
        parent::__construct($context, $data);
    }

    /**
     * @return void
     */
    protected function _construct()
    {
        parent::_construct();
        $this->setUseAjax(0);
    }

    /**
     * @return $this
     */
    protected function _prepareLayout()
    {
        $addUrl = $this->getUrl("*/*/new", ['_current' => false, 'id' => null, '_query' => false]);

            $this->addChild(
                'add_page_button', \Magento\Backend\Block\Widget\Button::class,
                [
                    'label' => __('Add Page'),
                    'onclick' => "addNew('" . $addUrl . "', false)",
                    'class' => 'add',
                    'id' => 'add_subpage_button',
                    'style' => $this->canAddSubPage() ? '' : 'display: none;'
                ]
            );


        return parent::_prepareLayout();
    }
    /**
     * Retrieve list of pages with name containing $namePart and their parents
     *
     * @param string $namePart
     * @return string
     */
    public function getSuggestedPagesJson($namePart)
    {
        $storeId = $this->getRequest()->getParam('store', $this->_getDefaultStoreId());

        /* @var $collection Collection */
        $collection = $this->_cmsPageFactory->create();

        $matchingNamesCollection = clone $collection;
        $escapedNamePart = $this->_resourceHelper->addLikeEscape(
            $namePart,
            ['position' => 'any']
        );
        $matchingNamesCollection->addAttributeToFilter(
            'name',
            ['like' => $escapedNamePart]
        )->addAttributeToFilter(
            'page_id',
            ['neq' => \Likipe\Cmspage\Model\Page::TREE_ROOT_ID]
        )->addAttributeToSelect(
            'path'
        )->setStoreId(
            $storeId
        );

        $shownCmsPagesIds = [];
        foreach ($matchingNamesCollection as $cmsPage) {
            foreach (explode('/', $cmsPage->getPath()) as $parentId) {
                $shownCmsPagesIds[$parentId] = 1;
            }
        }

        $collection->addAttributeToFilter(
            'page_id',
            ['in' => array_keys($shownPagesIds)]
        )->addAttributeToSelect(
            ['name', 'is_active', 'parent_id']
        )->setStoreId(
            $storeId
        );

        $cmsPageById = [
            \Likipe\Cmspage\Model\Page::TREE_ROOT_ID => [
                'id' => \Likipe\Cmspage\Model\Page::TREE_ROOT_ID,
                'children' => [],
            ],
        ];
        foreach ($collection as $cmsPage) {
            foreach ([$cmsPage->getId(), $cmsPage->getParentId()] as $cmsPageId) {
                if (!isset($cmsPageById[$cmsPageId])) {
                    $cmsPageById[$cmsPageId] = ['id' => $cmsPageId, 'children' => []];
                }
            }
            $cmsPageById[$cmsPage->getId()]['is_active'] = $cmsPage->getIsActive();
            $cmsPageById[$cmsPage->getId()]['label'] = $cmsPage->getName();
            $cmsPageById[$cmsPage->getParentId()]['children'][] = & $cmsPageById[$cmsPage->getId()];
        }

        return $this->_jsonEncoder->encode($cmsPageById[\Likipe\Cmspage\Model\Page::TREE_ROOT_ID]['children']);
    }

    /**
     * @return string
     */
    public function getAddRootButtonHtml()
    {
        return $this->getChildHtml('add_root_button');
    }

    /**
     * @return string
     */
    public function getAddPageButtonHtml()
    {
        return $this->getChildHtml('add_page_button');
    }

    /**
     * @return string
     */
    public function getExpandButtonHtml()
    {
        return $this->getChildHtml('expand_button');
    }

    /**
     * @return string
     */
    public function getCollapseButtonHtml()
    {
        return $this->getChildHtml('collapse_button');
    }

    /**
     * @return string
     */
    public function getStoreSwitcherHtml()
    {
        return $this->getChildHtml('store_switcher');
    }

    /**
     * @param bool|null $expanded
     * @return string
     */
    public function getLoadTreeUrl($expanded = null)
    {
        $params = ['_current' => true, 'id' => null, 'store' => null];
        if ($expanded === null && $this->_backendSession->getIsTreeWasExpanded() || $expanded == true) {
            $params['expand_all'] = true;
        }
        return $this->getUrl('*/*/pageJson', $params);
    }

    /**
     * @return string
     */
    public function getNodesUrl()
    {
        return $this->getUrl('cms/page/jsonTree');
    }

    /**
     * @return string
     */
    public function getSwitchTreeUrl()
    {
        return $this->getUrl(
            'cms/page/tree',
            ['_current' => true, 'store' => null, '_query' => false, 'id' => null, 'parent' => null]
        );
    }

    /**
     * @return bool
     * @SuppressWarnings(PHPMD.BooleanGetMethodName)
     */
    public function getIsWasExpanded()
    {
        return $this->_backendSession->getIsTreeWasExpanded();
    }

    /**
     * @return string
     */
    public function getMoveUrl()
    {
        return $this->getUrl('likipe_cmspage/page/move', ['store' => $this->getRequest()->getParam('store')]);
    }

    /**
     * @param mixed|null $parenNodePage
     * @return array
     */
    public function getTree($parenNodePage = null)
    {
        $rootArray = $this->_getNodeJson($this->getRoot($parenNodePage));
        $tree = isset($rootArray['children']) ? $rootArray['children'] : [];
        return $tree;
    }

    /**
     * @param mixed|null $parenNodePage
     * @return string
     */
    public function getTreeJson($parenNodePage = null)
    {
        $rootArray = $this->_getNodeJson($this->getRoot($parenNodePage));
        $json = $this->_jsonEncoder->encode(isset($rootArray['children']) ? $rootArray['children'] : []);
        return $json;
    }

    /**
     * Get JSON of array of pages, that are breadcrumbs for specified page path
     *
     * @param string $path
     * @param string $javascriptVarName
     * @return string
     */
    public function getBreadcrumbsJavascript($path, $javascriptVarName)
    {
        if (empty($path)) {
            return '';
        }

        $pages = $this->_pageTree->setStoreId($this->getStore()->getId())->loadBreadcrumbsArray($path);
        if (empty($pages)) {
            return '';
        }
        foreach ($pages as $key => $page) {
            $page[$key] = $this->_getNodeJson($page);
        }
        return '<script>require(["prototype"], function(){' . $javascriptVarName . ' = ' . $this->_jsonEncoder->encode(
            $pages
        ) .
            ';' .
            ($this->canAddSubPage() ? '$("add_sub_button").show();' : '$("add_sub_button").hide();') .
            '});</script>';
    }

    /**
     * Get JSON of a tree node or an associative array
     *
     * @param Node|array $node
     * @param int $level
     * @return string
     * @SuppressWarnings(PHPMD.CyclomaticComplexity)
     * @SuppressWarnings(PHPMD.NPathComplexity)
     */
    protected function _getNodeJson($node, $level = 0)
    {
        // create a node from data array
        if (is_array($node)) {
            $node = new Node($node, 'page_id', new \Magento\Framework\Data\Tree());
        }

        $item = [];
        $item['text'] = $this->buildNodeName($node);

        $rootForStores = in_array($node->getEntityId(), $this->getRootIds());

        $item['id'] = $node->getId();
        $item['store'] = (int)$this->getStore()->getId();
        $item['path'] = $node->getData('path');

        $item['cls'] = 'folder ' . ($node->getIsActive() ? 'active-page' : 'no-active-page');
        //$item['allowDrop'] = ($level<3) ? true : false;
        $allowMove = $this->_isPageMoveable($node);
        $item['allowDrop'] = $allowMove;
        // disallow drag if it's first level and page is root of a store
        $item['allowDrag'] = $allowMove && ($node->getLevel() == 1 && $rootForStores ? false : true);

        if ((int)$node->getChildrenCount() > 0) {
            $item['children'] = [];
        }

        $isParent = $this->_isParentSelectedPage($node);

        if ($node->hasChildren()) {
            $item['children'] = [];
            if (!($this->getUseAjax() && $node->getLevel() > 1 && !$isParent)) {
                foreach ($node->getChildren() as $child) {
                    $item['children'][] = $this->_getNodeJson($child, $level + 1);
                }
            }
        }

        if ($isParent || $node->getLevel() < 2) {
            $item['expanded'] = true;
        }

        return $item;
    }

    /**
     * Get page name
     *
     * @param \Magento\Framework\DataObject $node
     * @return string
     */
    public function buildNodeName($node)
    {
        $result = $this->escapeHtml($node->getTitle());
        if ($this->_withChildrenCount) {
            $result .= ' (' . $node->getChildrenCount() . ')';
        }
        return $result;
    }

    /**
     * @param Node|array $node
     * @return bool
     */
    protected function _isPageMoveable($node)
    {
        $options = new \Magento\Framework\DataObject(['is_moveable' => true, 'page' => $node]);

        $this->_eventManager->dispatch('adminhtml_cms_page_tree_is_moveable', ['options' => $options]);

        return $options->getIsMoveable();
    }

    /**
     * @param Node|array $node
     * @return bool
     */
    protected function _isParentSelectedPage($node)
    {
        if ($node && $this->getPage()) {
            $pathIds = $this->getPage()->getPathIds();
            if (in_array($node->getId(), $pathIds)) {
                return true;
            }
        }

        return false;
    }

    /**
     * Check if page loaded by outside link to page edit
     *
     * @return boolean
     */
    public function isClearEdit()
    {
        return (bool)$this->getRequest()->getParam('clear');
    }

    /**
     * Check availability of adding root
     *
     * @return boolean
     */
    public function canAddRootPage()
    {
        $options = new \Magento\Framework\DataObject(['is_allow' => true]);
        $this->_eventManager->dispatch(
            'adminhtml_catalog_page_tree_can_add_root_cmspage',
            ['page' => $this->getPage(), 'options' => $options, 'store' => $this->getStore()->getId()]
        );

        return $options->getIsAllow();
    }

    /**
     * Check availability of adding sub page
     *
     * @return boolean
     */
    public function canAddSubPage()
    {
        $options = new \Magento\Framework\DataObject(['is_allow' => true]);

        return $options->getIsAllow();
    }

    /**
     * @return bool
     */
    public function hasStoreRootPage()
    {
        $root = $this->getRoot();
        if ($root && $root->getId()) {
            return true;
        }
        return false;
    }

    /**
     * @return Store
     */
    public function getStore()
    {
        $storeId = (int)$this->getRequest()->getParam('store');
        return $this->_storeManager->getStore($storeId);
    }

    /**
     * @param mixed|null $parentNodePage
     * @param int $recursionLevel
     * @return Node|array|null
     * @SuppressWarnings(PHPMD.CyclomaticComplexity)
     */
    public function getRoot($parenNodePage = null, $recursionLevel = 3)
    {
        if ($parenNodePage !== null && $parenNodePage->getId()) {
            return $this->getNode($parenNodePage, $recursionLevel);
        }
        $root = $this->_coreRegistry->registry('root');

        if ($root === null) {
            $rootId = \Likipe\Cmspage\Model\Page::TREE_ROOT_ID;

            $tree = $this->_pageTree->load(null, $recursionLevel);

            if ($this->getPage()) {
                $tree->loadEnsuredNodes($this->getPage(), $tree->getNodeById($rootId));
            }

            $tree->addCollectionData($this->getPageCollection());

            $root = $tree->getNodeById($rootId);

            if ($root && $rootId != \Likipe\Cmspage\Model\Page::TREE_ROOT_ID) {
                $root->setIsVisible(true);
            } elseif ($root && $root->getId() == \Likipe\Cmspage\Model\Page::TREE_ROOT_ID) {
                $root->setName(__('Root'));
            }

            $this->_coreRegistry->register('root', $root);
        }

        return $root;
    }

    /**
     * @return int
     */
    protected function _getDefaultStoreId()
    {
        return \Magento\Store\Model\Store::DEFAULT_STORE_ID;
    }

    /**
     * @return \Magento\Framework\Model\ResourceModel\Db\Collection\AbstractCollection
     */
    public function getPageCollection()
    {
        $storeId = $this->getRequest()->getParam('store', $this->_getDefaultStoreId());
        $collection = $this->getData('page_collection');

        if ($collection === null) {
            $collection = $this->_collectionFactory->create();
            $this->setData('page_collection', $collection);
        }
        return $collection;
    }

    /**
     * @param mixed $parenNodePage
     * @param int $recursionLevel
     * @return Node
     */
    public function getNode($parenNodePage, $recursionLevel = 2)
    {
        $nodeId = $parenNodePage->getId();
        $node = $this->_pageTree->loadNode($nodeId);
        $node->loadChildren($recursionLevel);

        if ($node && $nodeId != \Likipe\Cmspage\Model\Page::TREE_ROOT_ID) {
            $node->setIsVisible(true);
        } elseif ($node && $node->getId() == \Likipe\Cmspage\Model\Page::TREE_ROOT_ID) {
            $node->setName(__('Root'));
        }

        $this->_pageTree->addCollectionData($this->getPageCollection());

        return $node;
    }

    /**
     * @param array $args
     * @return string
     */
    public function getSaveUrl(array $args = [])
    {
        $params = ['_current' => false, '_query' => false, 'store' => $this->getStore()->getId()];
        $params = array_merge($params, $args);
        return $this->getUrl('catalog/*/save', $params);
    }

    /**
     * @return string
     */
    public function getEditUrl()
    {
        return $this->getUrl(
            'cms/page/edit',
            ['store' => null, '_query' => false, 'id' => null, 'parent' => null]
        );
    }

    /**
     * Return ids of root pages as array
     *
     * @return array
     */
    public function getRootIds()
    {
        $ids = $this->getData('root_ids');
        if ($ids === null) {
            $ids = [\Likipe\Cmspage\Model\Page::TREE_ROOT_ID];
        }
        return $ids;
    }
    
    public function getPageId()
    {
        // 1. Get ID and create model
        $id = $this->getRequest()->getParam('page_id');
        $model = $this->_cmsPageFactory->create();

        // 2. Initial checking
        if ($id) {
            $model->load($id);
            if ($model->getId()) {
                return $model->getId();
            }
        }

        return \Likipe\Cmspage\Model\Page::TREE_ROOT_ID;
    }

}