<?php

namespace lhs\Yii2SaveRelationsBehavior;

trait SaveRelationsTrait
{

    public function load($data, $formName = null)
    {
        $loaded = parent::load($data, $formName);
        if ($loaded && $this->hasMethod('loadRelations')) {
            $this->loadRelations($data);
        }
        return $loaded;
    }
}