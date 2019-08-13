<?php

namespace lhs\Yii2SaveRelationsBehavior;

//this trait can flatten incomming post into single depth array. it can be used with unclead mutli input together
trait SaveRelationsFormTrait
{
    public function load($data, $formName = null)
    {
        $loaded = parent::load($data, $formName);

        if ($loaded && $this->hasMethod('loadRelations')) {
            /** @var SaveRelationsBehavior $relationsSaver */
            $relationsSaver = $this->getBehavior('saveRelations');

            if($relationsSaver->relationKeyName === SaveRelationsBehavior::RELATION_KEY_FORM_NAME) {
                $scope = $formName === null ? $this->formName() : $formName;
                $relationData = $data;
                unset($relationData[$scope]);

                foreach ($relationsSaver->relations as $relation) {
                    if(isset($relationData[$this->_getRelationKeyName($relation)])) {
                        continue;
                    }
                    if(isset($data[$scope][$relation])) {
                        $relationData[$this->_getRelationKeyName($relation)] = $data[$scope][$relation];
                    }
                }
            } else {
                $relationData = $data;
            }

            $this->loadRelations($relationData);
        }
        return $loaded;
    }

    /**
     * @param $relationName string
     * @return mixed
     */
    private function _getRelationKeyName($relationName)
    {
        /** @var ActiveQuery $relation */
        $relation = $this->getRelation($relationName);
        $modelClass = $relation->modelClass;
        /** @var ActiveQuery $relationalModel */
        $relationalModel = new $modelClass;
        return $relationalModel->formName();
    }
}
