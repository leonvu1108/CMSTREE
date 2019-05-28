<?php
namespace Likipe\Cmspage\Block\Adminhtml\Page\Edit;

class SaveButton extends \Magento\Cms\Block\Adminhtml\Page\Edit\SaveButton
{
    public function getButtonData()
    {
        return [
            'label' => __('Save Page'),
            'class' => 'save primary',
            'data_attribute' => [
                'mage-init' => ['button' => ['event' => 'save']],
                'form-role' => 'save',
            ],
            'sort_order' => 90,
        ];
    }
}
