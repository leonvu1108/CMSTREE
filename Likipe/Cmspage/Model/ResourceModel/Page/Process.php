<?php
namespace Likipe\Cmspage\Model\ResourceModel\Page;

use Magento\Cms\Model\Page;

class Process
{
    protected $_pageCollectionFactory;

    public function __construct(
        \Magento\Cms\Model\ResourceModel\Page\CollectionFactory $pageCollectionFactory
    ) {
        $this->_pageCollectionFactory = $pageCollectionFactory;
    }
    /**
     * @param Page $page
     * @return void
     */
    public function processDelete(Page $page)
    {
        /** @var \Magento\Page\Model\ResourceModel\Page $resourceModel */
        $resourceModel = $page->getResource();
        /**
         * Update children count for all parent pages
         */
        $parentIds = $page->getParentIds();
        if ($parentIds) {
            $childDecrease = $page->getChildrenCount() + 1;
            // +1 is itself
            $data = ['children_count' => new \Zend_Db_Expr('children_count - ' . $childDecrease)];
            $where = ['page_id IN(?)' => $parentIds];
            $resourceModel->getConnection()->update($resourceModel->getMainTable(), $data, $where);
        }
        $this->deleteChildren($page);
    }

    public function deleteChildren($curentPage)
    {
        $pages = $this->_pageCollectionFactory->create();
        $pages->addFieldToFilter('path', ['like' => $curentPage->getPath() . '/%']);

        //$childrenIds = $pages->getAllIds();
        if ($pages) {
            foreach ($pages as $page) {
                $oldPath = $page->getPath();
                $newPath = str_replace('/' . $curentPage->getId() . '/', '/', $oldPath);
                $page->setPath($newPath);
                $page->setParentId($curentPage->getParentId());
                $page->setLevel($curentPage->getLevel());
                $page->save();
            }
        }
    }


    public function updateChildrenIdentifiers(Page $page, $newIdentifier)
    {
        $table = $this->getTable('cms/page');
        $adapter = $this->_getWriteAdapter();
        $oldIdentifier = str_replace('/', '\/', $page->getIdentifier());
        $children = $this->_pageCollectionFactory->create()
            ->addFieldToFilter('path', array('like' => $page->getPath().'/%'));
        foreach ($children as $child) {

            $identifier = preg_replace("/^{$oldIdentifier}\/(.*)/i", "{$newIdentifier}/\$1", $child->getIdentifier());
            $child->setIdentifier($identifier);
            $child->save();

            $sql = "UPDATE {$table} SET "
                . $adapter->quoteInto('identifier = ? ', $identifier)
                . $adapter->quoteInto('WHERE page_id = ?', $child->getPageId());
            $adapter->query($sql);
        }
    }
}
