<?php 
namespace Likipe\Cmspage\Model\ResourceModel;

class Page extends \Magento\Cms\Model\ResourceModel\Page
{

    /**
     * @var Page\process
     */
    protected $process;

    /**
     * Move page to another parent node
     *
     * @param \Magento\Catalog\Model\Page $page
     * @param \Magento\Catalog\Model\Page $newParent
     * @param null|int $afterPageId
     * @return $this
     */
    public function changeParent(
        \Magento\Cms\Model\Page $page,
        \Magento\Cms\Model\Page $newParent,
        $afterPageId = null
    ) {
        $childrenCount = $this->getChildrenCount($page->getId()) + 1;
        $table = $this->getTable('cms_page');
        $connection = $this->getConnection();
        $levelFiled = $connection->quoteIdentifier('level');
        $pathField = $connection->quoteIdentifier('path');

        /**
         * Decrease children count for all old page parent pages
         */
        $connection->update(
            $table,
            ['children_count' => new \Zend_Db_Expr('children_count - ' . $childrenCount)],
            ['page_id IN(?)' => $page->getParentIds()]
        );

        /**
         * Increase children count for new page parents
         */
        $connection->update(
            $table,
            ['children_count' => new \Zend_Db_Expr('children_count + ' . $childrenCount)],
            ['page_id IN(?)' => $newParent->getPathIds()]
        );

        $position = $this->_processPositions($page, $newParent, $afterPageId);

        $newPath = sprintf('%s/%s', $newParent->getPath(), $page->getId());
        $newLevel = $newParent->getLevel() + 1;
        $levelDisposition = $newLevel - $page->getLevel();
        //$identifiers = explode('/', $page->getIdentifier());
        //$newIdentifier = trim($newParent->getIdentifier().'/'.array_pop($identifiers), '/');
        //$newPage->setIdentifier($newIdentifier);

        //$this->getProcess()->updateChildrenIdentifiers($page, $newIdentifier);
        /**
         * Update children nodes path
         */
        $connection->update(
            $table,
            [
                'path' => new \Zend_Db_Expr(
                    'REPLACE(' . $pathField . ',' . $connection->quote(
                        $page->getPath() . '/'
                    ) . ', ' . $connection->quote(
                        $newPath . '/'
                    ) . ')'
                ),
                'level' => new \Zend_Db_Expr($levelFiled . ' + ' . $levelDisposition)
            ],
            [$pathField . ' LIKE ?' => $page->getPath() . '/%']
        );
        /**
         * Update moved page data
         */
        $data = [
            'path' => $newPath,
            'level' => $newLevel,
            'position' => $position,
            'parent_id' => $newParent->getId(),
        ];
        $connection->update($table, $data, ['page_id = ?' => $page->getId()]);

        // Update page object to new data
        $page->addData($data);
        $page->unsetData('path_ids');

        return $this;
    }

    /**
     * Get chlden pages count
     *
     * @param int $pageId
     * @return int
     */
    public function getChildrenCount($pageId)
    {
        $select = $this->getConnection()->select()->from(
            $this->getTable('cms_page'),
            'children_count'
        )->where(
            'page_id = :page_id'
        );
        $bind = ['page_id' => $pageId];

        return $this->getConnection()->fetchOne($select, $bind);
    }

    /**
     * Process positions of old parent page children and new parent page children.
     * Get position for moved page
     *
     * @param \Magento\Cms\Model\Page $page
     * @param \Magento\Cms\Model\Page $newParent
     * @param null|int $afterCmsId
     * @return int
     */
    protected function _processPositions($page, $newParent, $afterPageId)
    {
        $table = $this->getTable('cms_page');
        $connection = $this->getConnection();
        $positionField = $connection->quoteIdentifier('position');

        $bind = ['position' => new \Zend_Db_Expr($positionField . ' - 1')];
        $where = [
            'parent_id = ?' => $page->getParentId(),
            $positionField . ' > ?' => $page->getPosition(),
        ];
        $connection->update($table, $bind, $where);

        /**
         * Prepare position value
         */
        if ($afterPageId) {
            $select = $connection->select()->from($table, 'position')->where('page_id = :page_id');
            $position = $connection->fetchOne($select, ['page_id' => $afterPageId]);
            $position += 1;
        } else {
            $position = 1;
        }

        $bind = ['position' => new \Zend_Db_Expr($positionField . ' + 1')];
        $where = ['parent_id = ?' => $newParent->getId(), $positionField . ' >= ?' => $position];
        $connection->update($table, $bind, $where);

        return $position;
    }

    /**
     * Process page data before saving
     *
     * @param AbstractModel $object
     * @return $this
     * @throws LocalizedException
     */
    protected function _beforeSave(\Magento\Framework\Model\AbstractModel $object)
    {
        parent::_beforeSave($object);
        /*
         * For two attributes which represent timestamp data in DB
         * we should make converting such as:
         * If they are empty we need to convert them into DB
         * type NULL so in DB they will be empty and not some default value
         */
        foreach (['custom_theme_from', 'custom_theme_to'] as $field) {
            $value = !$object->getData($field) ? null : $this->dateTime->formatDate($object->getData($field));
            $object->setData($field, $value);
        }

        if (!$this->isValidPageIdentifier($object)) {
            throw new LocalizedException(
                __('The page URL key contains capital letters or disallowed symbols.')
            );
        }

        if ($this->isNumericPageIdentifier($object)) {
            throw new LocalizedException(
                __('The page URL key cannot be made of only numbers.')
            );
        }

        if ($object->isObjectNew()) {
            if ($object->getPosition() === null) {
                $object->setPosition($this->_getMaxPosition($object->getPath()) + 1);
            }
            $path = explode('/', $object->getPath());
            $level = count($path)  - ($object->getId() ? 1 : 0);
            $toUpdateChild = array_diff($path, [$object->getId()]);

            if (!$object->hasPosition()) {
                $object->setPosition($this->_getMaxPosition(implode('/', $toUpdateChild)) + 1);
            }
            if (!$object->hasLevel()) {
                $object->setLevel($level);
            }
            if (!$object->hasParentId() && $level) {
                $object->setParentId($path[$level - 1]);
            }
            if (!$object->getId()) {
                $object->setPath($object->getPath() . '/');
            }

            $this->getConnection()->update(
                $this->getMainTable(),
                ['children_count' => new \Zend_Db_Expr('children_count+1')],
                ['page_id IN(?)' => $toUpdateChild]
            );
        }

        return  $this;
    }

    /**
     * Process page data after save page object
     * save related products ids and update path value
     *
     * @param \Magento\Framework\DataObject $object
     * @return $this
     */
    protected function _afterSave(\Magento\Framework\Model\AbstractModel $object)
    {
        /**
         * Add identifier for new page
         */
        if (substr($object->getPath(), -1) == '/') {
            $object->setPath($object->getPath() . $object->getId());
            $this->_savePath($object);
        }

        return parent::_afterSave($object);
    }

    protected function _beforeDelete(\Magento\Framework\Model\AbstractModel $object)
    {
        parent::_beforeDelete($object);
        $this->getProcess()->processDelete($object);
    }

    private function getProcess()
    {
        if (null === $this->process) {
            $this->process = \Magento\Framework\App\ObjectManager::getInstance()
                ->get(\Likipe\Cmspage\Model\ResourceModel\Page\Process::class);
        }

        return $this->process;
    }

    /**
     * Update path field
     *
     * @param \Magento\Cms\Model\Page $object
     * @return $this
     */
    protected function _savePath($object)
    {
        if ($object->getId()) {
            $this->getConnection()->update(
                $this->getMainTable(),
                ['path' => $object->getPath()],
                ['page_id = ?' => $object->getId()]
            );
            $object->unsetData('path_ids');
        }
        return $this;
    }

    /**
     * Get maximum position of child page by specific tree path
     *
     * @param string $path
     * @return int
     */
    protected function _getMaxPosition($path)
    {
        $connection = $this->getConnection();
        $positionField = $connection->quoteIdentifier('position');
        $level = count(explode('/', $path));
        $bind = ['c_level' => $level, 'c_path' => $path . '/%'];
        $select = $connection->select()->from(
            $this->getTable('cms_page'),
            'MAX(' . $positionField . ')'
        )->where(
            $connection->quoteIdentifier('path') . ' LIKE :c_path'
        )->where(
            $connection->quoteIdentifier('level') . ' = :c_level'
        );

        $position = $connection->fetchOne($select, $bind);
        if (!$position) {
            $position = 0;
        }
        return $position;
    }
}