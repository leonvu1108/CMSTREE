<?php
namespace Likipe\Cmspage\Model\Config\Source;

use Mage360\Brands\Model\Brands as BrandsModel;
use Mage360\Brands\Model\ResourceModel\Brands\CollectionFactory as BrandsCollectionFactory;
use Mage360\Brands\Model\ResourceModel\Brands\Collection;
use Magento\Store\Model\ScopeInterface;

class ListBrands extends \Magento\Eav\Model\Entity\Attribute\Source\AbstractSource
{

    /**
     * @var BrandsCollectionFactory
     */
    public $brandsCollectionFactory;

    public function __construct(
        BrandsCollectionFactory $brandsCollectionFactory
    ) {
        $this->brandsCollectionFactory = $brandsCollectionFactory;
    }

    /**
     * return brands collection
     *
     * @return CollectionFactory
     */
    public function getBrands()
    {
        $collection = $this->brandsCollectionFactory->create()
            ->addFieldToSelect('*')
            ->addFieldToFilter('is_active', 1)
            ->setOrder('name', 'ASC');
        return $collection;
    }

    /**
     * getAllOptions
     *
     * @return array
     */
    public function getAllOptions()
    {
        if ($this->_options === null) {
            $this->_options = [['label' => 'Select brand', 'value' => '0']];
            foreach ($this->getBrands() as $brand) {
                $this->_options[] = [
                    'label' => $brand->getName(),
                    'value' => $brand->getBrandId()
                ];
            }
        }


        return $this->_options;
    }
}