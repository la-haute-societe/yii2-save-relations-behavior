<?php

namespace lhs\Yii2SaveRelationsBehavior;

trait SaveRelationsTrait
{

    /**
     * Populates the relations with input data.
     */
    public function load($data, $formName = null)
    {
        $loaded = parent::load($data, $formName);
        if ($loaded && $this->hasMethod('loadRelations')) {
            $this->loadRelations($data);
        }
        return $loaded;
    }

    /**
     * Auto start transaction if model has relations
     */
    public function isTransactional($operation)
    {
        if ($this->hasProperty('relations')) {
            return count($this->relations) > 0;
        }

        return parent::isTransactional($operation);
    }

}