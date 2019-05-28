<?php
/**
 *
 * Copyright © Magento, Inc. All rights reserved.
 * See COPYING.txt for license details.
 */
namespace Likipe\Cmspage\Controller\Adminhtml\Page;

class Add extends \Likipe\Cmspage\Controller\Adminhtml\Page
{
    /**
     * Forward factory for result
     *
     * @var \Magento\Backend\Model\View\Result\ForwardFactory
     */
    protected $resultForwardFactory;

    /**
     * Add page constructor
     *
     * @param \Magento\Backend\App\Action\Context $context
     * @param \Magento\Backend\Model\View\Result\ForwardFactory $resultForwardFactory
     */
    public function __construct(
        \Magento\Backend\App\Action\Context $context,
        \Magento\Backend\Model\View\Result\ForwardFactory $resultForwardFactory
    ) {
        parent::__construct($context);
        $this->resultForwardFactory = $resultForwardFactory;
    }

    /**
     * Add new page form
     *
     * @return \Magento\Backend\Model\View\Result\Forward
     */
    public function execute()
    {
        $parentId = (int)$this->getRequest()->getParam('parent');

        $page = $this->_initPage(true);
        if (!$page || !$parentId || $page->getId()) {
            /** @var \Magento\Backend\Model\View\Result\Redirect $resultRedirect */
            $resultRedirect = $this->resultRedirectFactory->create();
            return $resultRedirect->setPath('cms/*/', ['_current' => true, 'id' => null]);
        }

        /**
         * Check if there are data in session (if there was an exception on saving page)
         */
        $pageData = $this->_getSession()->getPageData(true);
        if (is_array($pageData)) {
            unset($pageData['image']);
            $page->addData($pageData);
        }

        $resultPageFactory = $this->_objectManager->get(\Magento\Framework\View\Result\PageFactory::class);
        /** @var \Magento\Backend\Model\View\Result\Page $resultPage */
        $resultPage = $resultPageFactory->create();

        if ($this->getRequest()->getQuery('isAjax')) {
            return $this->ajaxRequestResponse($page, $resultPage);
        }

        $resultPage->setActiveMenu('Magento_Cms::cms_page');
        $resultPage->getConfig()->getTitle()->prepend(__('New Page'));
        $resultPage->addBreadcrumb(__('Manage cms Pages'), __('Manage Pages'));

        $block = $resultPage->getLayout()->getBlock('cms.wysiwyg.js');
        if ($block) {
            $block->setStoreId(0);
        }

        return $resultPage;
    }
}
