<?php
namespace Likipe\Cmspage\Plugin\Model\Page;


class DataProvider
{
    /**
     * Get data
     *
     * @return array
     */
    public function afterGetData(
        \Magento\Cms\Model\Page\DataProvider $subject,
        $result
    ) {
        if (is_array($result)) {
            foreach ($result as &$item) {
                if(isset($item['brand_bottom']) && ($item['brand_bottom'])) {
                    $item['brand_bottom'] = json_decode($item['brand_bottom']);
                }
            }
        }

        return $result;
    }
}