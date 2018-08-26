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
     * Starts transaction if [autoStartTransaction] has been defined
     */
    public function isTransactional($operation)
    {
        if ($this->hasProperty('autoStartTransaction')) {
            return $this->autoStartTransaction;
        }

        return parent::isTransactional($operation);
    }

}