<?php
namespace Likipe\Cmspage\Block;

use Mage360\Brands\Api\BrandsRepositoryInterface;

class BrandHtml extends \Magento\Framework\View\Element\Template
{
    /**
     * @var \Magento\Cms\Model\Template\FilterProvider
     */
    protected $_filterProvider;

    /**
     * @var \Magento\Cms\Model\Page
     */
    protected $_page;

    public function __construct(
        \Magento\Framework\View\Element\Template\Context $context,
        BrandsRepositoryInterface $brandsRepository,
        \Magento\Cms\Model\Page $page,
        \Magento\Cms\Model\Template\FilterProvider $filterProvider,
        array $data = []
    )
    {
        parent::__construct($context, $data);
        $this->brandsRepository = $brandsRepository;
        // used singleton (instead factory) because there exist dependencies on \Magento\Cms\Helper\Page
        $this->_page = $page;
        $this->_filterProvider = $filterProvider;
    }

    /**
     * Prepare HTML content
     *
     * @return string
     */
    public function getBrandHtml($content)
    {
        $html = $this->_filterProvider->getPageFilter()->filter($content);
        return $html;
    }

    /**
     * Prepare HTML content
     *
     * @return string
     */
    public function getBrandById($id)
    {
        try {
            return $this->brandsRepository->getById($id);
        } catch (NoSuchEntityException $e) {
            return null;
        }
    }
}