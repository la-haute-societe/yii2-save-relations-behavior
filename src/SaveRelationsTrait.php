<?php

namespace lhs\Yii2SaveRelationsBehavior;

/**
 * Trait SaveRelationsTrait
 * @package lhs\Yii2SaveRelationsBehavior
 *
 * @mixin SaveRelationsBehavior
 */
trait SaveRelationsTrait
{

    public function load($data, $formName = null)
    {
        $loaded = parent::load($data, $formName);
        if ($loaded && $this->hasMethod('loadRelations')) {
            $this->_prepareLoadData($data);

            $this->loadRelations($data);
        }
        return $loaded;
    }

    /**
     * @param $data
     */
    private function _prepareLoadData(&$data) {
        $scope = $formName === null ? $this->formName() : $formName;

        foreach ($this->relations as $key => $value) {
            if (is_int($key)) {
                $relationName = $value;
            } else {
                $relationName = $key;
            }

            if(!isset($data[$scope][$relationName])) {
                continue;
            }

            $relation = $this->getRelation($relationName);

            if(!$relation) {
                continue;
            }

            $modelClass = $relation->modelClass;
            /** @var ActiveQuery $relationalModel */
            $relationalModel = new $modelClass;
            $keyName = $relationalModel->formName();

            $data[$keyName] = $data[$scope][$relationName];

            unset($data[$scope][$relationName]);
        }
    }
}
