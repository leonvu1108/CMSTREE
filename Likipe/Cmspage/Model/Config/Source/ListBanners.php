<?php
namespace Likipe\Cmspage\Model\Config\Source;

use Mageplaza\BetterSlider\Model\SliderFactory as SliderModelFactory;
use Mageplaza\BetterSlider\Model\BannerFactory as BannerModelFactory;
use \Magento\Framework\View\Element\Template\Context;

class ListBanners extends \Magento\Eav\Model\Entity\Attribute\Source\AbstractSource
{
    protected $sliderFactory;
    protected $bannerFactory;

    public function __construct(

        SliderModelFactory $sliderFactory,
        BannerModelFactory $bannerFactory
    )
    {
        $this->sliderFactory = $sliderFactory;
        $this->bannerFactory = $bannerFactory;
    }

    /**
     * getAllOptions
     *
     * @return array
     */
    public function getAllOptions()
    {
        if ($this->_options === null) {
            $this->_options = [['label' => 'Select banner brand', 'value' => '']];

            foreach ($this->getSliders() as $slider) {
                $this->_options[] = [
                    'label' => $slider->getName(),
                    'value' => $slider->getId()
                ];
            }
        }
        return $this->_options;
    }

    public function getSliders()
    {
        $model = $this->sliderFactory->create();
        if($model){
            $banners = $model->getSlidersCollection();
            return $banners;
        } else{
            return null;
        }

    }
}