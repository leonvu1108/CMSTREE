<?php
namespace Likipe\Cmspage\Controller\Adminhtml\Page;

use Magento\Backend\App\Action;
use Magento\Cms\Model\Page;
use Magento\Framework\App\Request\DataPersistorInterface;
use Magento\Framework\Exception\LocalizedException;
use Magento\Cms\Controller\Adminhtml\Page\PostDataProcessor as PostDataProcessor;
class Save extends \Magento\Backend\App\Action
{
    /**
     * Authorization level of a basic admin session
     *
     * @see _isAllowed()
     */
    const ADMIN_RESOURCE = 'Magento_Cms::save';

    /**
     * @var PostDataProcessor
     */
    protected $dataProcessor;

    /**
     * @var DataPersistorInterface
     */
    protected $dataPersistor;

    /**
     * @var \Magento\Cms\Model\PageFactory
     */
    private $pageFactory;

    /**
     * @var \Magento\Cms\Api\PageRepositoryInterface
     */
    private $pageRepository;

    /**
     * @param Action\Context $context
     * @param PostDataProcessor $dataProcessor
     * @param DataPersistorInterface $dataPersistor
     * @param \Magento\Cms\Model\PageFactory $pageFactory
     * @param \Magento\Cms\Api\PageRepositoryInterface $pageRepository
     */
    public function __construct(
        Action\Context $context,
        PostDataProcessor $dataProcessor,
        DataPersistorInterface $dataPersistor,
        \Magento\Cms\Model\PageFactory $pageFactory = null,
        \Magento\Cms\Api\PageRepositoryInterface $pageRepository = null
    ) {
        $this->dataProcessor = $dataProcessor;
        $this->dataPersistor = $dataPersistor;
        $this->pageFactory = $pageFactory
            ?: \Magento\Framework\App\ObjectManager::getInstance()->get(\Magento\Cms\Model\PageFactory::class);
        $this->pageRepository = $pageRepository
            ?: \Magento\Framework\App\ObjectManager::getInstance()
                ->get(\Magento\Cms\Api\PageRepositoryInterface::class);
        parent::__construct($context);
    }
    /**
     * Save action
     *
     * @SuppressWarnings(PHPMD.CyclomaticComplexity)
     * @return \Magento\Framework\Controller\ResultInterface
     */
    public function execute()
    {
        $pagePostData = $this->getRequest()->getPostValue();
        $isNewPage = false;
        if (empty($pagePostData['page_id'])) {
            $isNewPage = true;
        }
        $parentId = isset($pagePostData['parent']) ? $pagePostData['parent'] : null;

        /** @var \Magento\Backend\Model\View\Result\Redirect $resultRedirect */
        $resultRedirect = $this->resultRedirectFactory->create();
        if ($pagePostData) {

            $pagePostData = $this->dataProcessor->filter($pagePostData);
            if (isset($pagePostData['is_active']) && $pagePostData['is_active'] === 'true') {
                $pagePostData['is_active'] = Page::STATUS_ENABLED;
            }
            if (empty($pagePostData['page_id'])) {
                $pagePostData['page_id'] = null;
            }

            /** @var \Magento\Cms\Model\Page $model */
            $model = $this->pageFactory->create();

            $id = $this->getRequest()->getParam('page_id');
            if ($id) {
                try {
                    $model = $this->pageRepository->getById($id);
                } catch (LocalizedException $e) {
                    $this->messageManager->addErrorMessage(__('This page no longer exists.'));
                    return $resultRedirect->setPath('*/*/');
                }
            }


            $model->setData($pagePostData);
            
            if ($parentId) {
                $model->setParentId($parentId);
            }
            if ($isNewPage) {
                $parentPage = $this->getParentPage($parentId);
                $model->setPath($parentPage->getPath());
            }

            $this->_eventManager->dispatch(
                'cms_page_prepare_save',
                ['page' => $model, 'request' => $this->getRequest()]
            );

            if (!$this->dataProcessor->validate($pagePostData)) {
                return $resultRedirect->setPath('*/*/edit', ['page_id' => $model->getId(), '_current' => true]);
            }

            try {
                $this->pageRepository->save($model);
                $this->messageManager->addSuccessMessage(__('You saved the page.'));
                $this->dataPersistor->clear('cms_page');
                return $resultRedirect->setPath('*/*/edit', ['page_id' => $model->getId(), '_current' => true]);

            } catch (LocalizedException $e) {
                $this->messageManager->addExceptionMessage($e->getPrevious() ?:$e);
            } catch (\Exception $e) {
                $this->messageManager->addExceptionMessage($e, __('Something went wrong while saving the page.'));
            }

            $this->dataPersistor->set('cms_page', $pagePostData);
            return $resultRedirect->setPath('*/*/edit', ['page_id' => $this->getRequest()->getParam('page_id')]);
        }
        return $resultRedirect->setPath('*/*/');
    }


    /**
     * Get parent page
     *
     * @param int $parentId
     *
     * @return \Magento\Cms\Model\Page
     */
    protected function getParentPage($parentId)
    {
        if (!$parentId) {
            $parentId = \Likipe\Cmspage\Model\Page::TREE_ROOT_ID;
        }
        return $this->_objectManager->create(\Magento\Cms\Model\Page::class)->load($parentId);
    }
}
