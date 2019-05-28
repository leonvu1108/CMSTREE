<?php 
namespace Likipe\cmspage\Plugin\Controller\Adminhtml\Page;

class PostDataProcessor
{
    public function aroundFilter(
        \Magento\Cms\Controller\Adminhtml\Page\PostDataProcessor $subject,
        \Closure $proceed,
        $data
    ) {

        if(isset($data['brand_bottom']) && ($data['brand_bottom'])) {
            $data['brand_bottom'] = json_encode($data['brand_bottom']);
        }

        return $proceed($data);
    }
}