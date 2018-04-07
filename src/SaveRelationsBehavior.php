<?php

namespace lhs\Yii2SaveRelationsBehavior;

use RuntimeException;
use Yii;
use yii\base\Behavior;
use yii\base\Component;
use yii\base\Exception;
use yii\base\ModelEvent;
use yii\base\UnknownPropertyException;
use yii\db\ActiveQuery;
use yii\db\BaseActiveRecord;
use yii\db\Exception as DbException;
use yii\db\Transaction;
use yii\helpers\ArrayHelper;
use yii\helpers\Inflector;
use yii\helpers\VarDumper;

/**
 * This Active Record Behavior allows to validate and save the Model relations when the save() method is invoked.
 * List of handled relations should be declared using the $relations parameter via an array of relation names.
 * @author albanjubert
 */
class SaveRelationsBehavior extends Behavior
{

    public $relations = [];
    private $_relations = [];
    private $_oldRelationValue = []; // Store initial relations value
    private $_newRelationValue = []; // Store update relations value
    private $_relationsToDelete = [];
    private $_relationsSaveStarted = false;
    private $_transaction;


    private $_relationsScenario = [];
    private $_relationsExtraColumns = [];
    private $_relationsCascadeDelete = [];

    /**
     * @param $relationName
     * @param int|null $i
     * @return string
     */
    protected static function prettyRelationName($relationName, $i = null)
    {
        return Inflector::camel2words($relationName, true) . (is_null($i) ? '' : " #{$i}");
    }

    //private $_relationsCascadeDelete = []; //TODO

    /**
     * @inheritdoc
     */
    public function init()
    {
        parent::init();
        $allowedProperties = ['scenario', 'extraColumns', 'cascadeDelete'];
        foreach ($this->relations as $key => $value) {
            if (is_int($key)) {
                $this->_relations[] = $value;
            } else {
                $this->_relations[] = $key;
                if (is_array($value)) {
                    foreach ($value as $propertyKey => $propertyValue) {
                        if (in_array($propertyKey, $allowedProperties)) {
                            $this->{'_relations' . ucfirst($propertyKey)}[$key] = $propertyValue;
                        } else {
                            throw new UnknownPropertyException('The relation property named ' . $propertyKey . ' is not supported');
                        }
                    }
                }
            }
        }
    }

    /**
     * @inheritdoc
     */
    public function events()
    {
        return [
            BaseActiveRecord::EVENT_BEFORE_VALIDATE => 'beforeValidate',
            BaseActiveRecord::EVENT_AFTER_INSERT    => 'afterSave',
            BaseActiveRecord::EVENT_AFTER_UPDATE    => 'afterSave',
            BaseActiveRecord::EVENT_BEFORE_DELETE   => 'beforeDelete',
            BaseActiveRecord::EVENT_AFTER_DELETE    => 'afterDelete'
        ];
    }

    /**
     * Check if the behavior is attached to an Active Record
     * @param Component $owner
     * @throws RuntimeException
     */
    public function attach($owner)
    {
        if (!($owner instanceof BaseActiveRecord)) {
            throw new RuntimeException('Owner must be instance of yii\db\BaseActiveRecord');
        }
        parent::attach($owner);
    }

    /**
     * Override canSetProperty method to be able to detect if a relation setter is allowed.
     * Setter is allowed if the relation is declared in the `relations` parameter
     * @param string $name
     * @param boolean $checkVars
     * @return boolean
     */
    public function canSetProperty($name, $checkVars = true)
    {
        /** @var BaseActiveRecord $owner */
        $owner = $this->owner;
        $relation = $owner->getRelation($name, false);
        if (in_array($name, $this->_relations) && !is_null($relation)) {
            return true;
        }
        return parent::canSetProperty($name, $checkVars);
    }

    /**
     * Override __set method to be able to set relations values either by providing a model instance,
     * a primary key value or an associative array
     * @param string $name
     * @param mixed $value
     * @throws \yii\base\InvalidArgumentException
     */
    public function __set($name, $value)
    {
        /** @var BaseActiveRecord $owner */
        $owner = $this->owner;
        if (in_array($name, $this->_relations)) {
            Yii::debug("Setting {$name} relation value", __METHOD__);
            /** @var ActiveQuery $relation */
            $relation = $owner->getRelation($name);
            if (!isset($this->_oldRelationValue[$name])) {
                if ($owner->isNewRecord) {
                    if ($relation->multiple === true) {
                        $this->_oldRelationValue[$name] = [];
                    } else {
                        $this->_oldRelationValue[$name] = null;
                    }
                } else {
                    $this->_oldRelationValue[$name] = $owner->{$name};
                }
            }
            if ($relation->multiple === true) {
                $this->setMultipleRelation($name, $value);
            } else {
                $this->setSingleRelation($name, $value);
            }
        }
    }

    /**
     * Set the named single relation with the given value
     * @param $name
     * @param $value
     * @throws \yii\base\InvalidArgumentException
     */
    protected function setSingleRelation($name, $value)
    {
        /** @var BaseActiveRecord $owner */
        $owner = $this->owner;
        /** @var ActiveQuery $relation */
        $relation = $owner->getRelation($name);
        if (!($value instanceof $relation->modelClass)) {
            $value = $this->processModelAsArray($value, $relation);
        }
        $this->_newRelationValue[$name] = $value;
        $owner->populateRelation($name, $value);
    }

    /**
     * Set the named multiple relation with the given value
     * @param $name
     * @param $value
     * @throws \yii\base\InvalidArgumentException
     */
    protected function setMultipleRelation($name, $value)
    {
        /** @var BaseActiveRecord $owner */
        $owner = $this->owner;
        /** @var ActiveQuery $relation */
        $relation = $owner->getRelation($name);
        $newRelations = [];
        if (!is_array($value)) {
            if (!empty($value)) {
                $value = [$value];
            } else {
                $value = [];
            }
        }
        foreach ($value as $entry) {
            if ($entry instanceof $relation->modelClass) {
                $newRelations[] = $entry;
            } else {
                // TODO handle this with one DB request to retrieve all models
                $newRelations[] = $this->processModelAsArray($entry, $relation);
            }
        }
        $this->_newRelationValue[$name] = $newRelations;
        $owner->populateRelation($name, $newRelations);
    }

    /**
     * Get a BaseActiveRecord model using the given $data parameter.
     * $data could either be a model ID or an associative array representing model attributes => values
     * @param mixed $data
     * @param \yii\db\ActiveQuery $relation
     * @return BaseActiveRecord
     */
    protected function processModelAsArray($data, $relation)
    {
        /** @var BaseActiveRecord $modelClass */
        $modelClass = $relation->modelClass;
        $fks = $this->_getRelatedFks($data, $relation, $modelClass);
        return $this->_loadOrCreateRelationModel($data, $fks, $modelClass);
    }

    /**
     * Before the owner model validation, save related models.
     * For `hasOne()` relations, set the according foreign keys of the owner model to be able to validate it
     * @param ModelEvent $event
     * @throws DbException
     * @throws \yii\base\InvalidConfigException
     */
    public function beforeValidate(ModelEvent $event)
    {
        if ($this->_relationsSaveStarted === false && !empty($this->_oldRelationValue)) {
            /* @var $model BaseActiveRecord */
            $model = $this->owner;
            if ($this->saveRelatedRecords($model, $event)) {
                // If relation is has_one, try to set related model attributes
                foreach ($this->_relations as $relationName) {
                    if (array_key_exists($relationName, $this->_oldRelationValue)) { // Relation was not set, do nothing...
                        /** @var ActiveQuery $relation */
                        $relation = $model->getRelation($relationName);
                        if ($relation->multiple === false && !empty($model->{$relationName})) {
                            Yii::debug("Setting foreign keys for {$relationName}", __METHOD__);
                            foreach ($relation->link as $relatedAttribute => $modelAttribute) {
                                if ($model->{$modelAttribute} !== $model->{$relationName}->{$relatedAttribute}) {
                                    $model->{$modelAttribute} = $model->{$relationName}->{$relatedAttribute};
                                }
                            }
                        }
                    }
                }
            }
        }
    }

    /**
     * For each related model, try to save it first.
     * If set in the owner model, operation is done in a transactional way so if one of the models should not validate
     * or be saved, a rollback will occur.,
     * This is done during the before validation process to be able to set the related foreign keys.
     * @param BaseActiveRecord $model
     * @param ModelEvent $event
     * @return bool
     * @throws DbException
     * @throws \yii\base\InvalidConfigException
     */
    protected function saveRelatedRecords(BaseActiveRecord $model, ModelEvent $event)
    {
        $this->startTransactionForModel($model);
        try {
            foreach ($this->_relations as $relationName) {
                if (array_key_exists($relationName, $this->_oldRelationValue)) { // Relation was not set, do nothing...
                    /** @var ActiveQuery $relation */
                    $relation = $model->getRelation($relationName);
                    if (!empty($model->{$relationName})) {
                        if ($relation->multiple === false) {
                            $this->_prepareHasOneRelation($model, $relationName, $event);
                        } else {
                            $this->_prepareHasManyRelation($model, $relationName);
                        }
                    }
                }
            }
            if (!$event->isValid) {
                throw new Exception('One of the related model could not be validated');
            }
        } catch (Exception $e) {
            Yii::warning(get_class($e) . ' was thrown while saving related records during beforeValidate event: ' . $e->getMessage(), __METHOD__);
            $this->_rollback();
            $model->addError($model->formName(), $e->getMessage());
            $event->isValid = false; // Stop saving, something went wrong
            return false;
        }
        return true;
    }

    /**
     * Validate a relation model and add an error message to owner model attribute if needed
     * @param string $prettyRelationName
     * @param string $relationName
     * @param BaseActiveRecord $relationModel
     */
    protected function validateRelationModel($prettyRelationName, $relationName, BaseActiveRecord $relationModel)
    {
        /** @var BaseActiveRecord $model */
        $model = $this->owner;
        if (!is_null($relationModel) && ($relationModel->isNewRecord || count($relationModel->getDirtyAttributes()))) {
            if (array_key_exists($relationName, $this->_relationsScenario)) {
                $relationModel->setScenario($this->_relationsScenario[$relationName]);
            }
            Yii::debug("Validating {$prettyRelationName} relation model using " . $relationModel->scenario . ' scenario', __METHOD__);
            if (!$relationModel->validate()) {
                $this->_addError($relationModel, $model, $relationName, $prettyRelationName);
            }

        }
    }

    /**
     * Attach errors to owner relational attributes
     * @param $relationModel
     * @param $owner
     * @param $relationName
     * @param $prettyRelationName
     */
    private function _addError($relationModel, $owner, $relationName, $prettyRelationName)
    {
        foreach ($relationModel->errors as $attributeErrors) {
            foreach ($attributeErrors as $error) {
                $owner->addError($relationName, "{$prettyRelationName}: {$error}");
            }
        }
    }

    /**
     * Rollback transaction if any
     * @throws DbException
     */
    private function _rollback()
    {
        if (($this->_transaction instanceof Transaction) && $this->_transaction->isActive) {
            $this->_transaction->rollBack(); // If anything goes wrong, transaction will be rolled back
            Yii::info('Rolling back', __METHOD__);
        }
    }

    /**
     * Link the related models.
     * If the models have not been changed, nothing will be done.
     * Related records will be linked to the owner model using the BaseActiveRecord `link()` method.
     * @throws Exception
     */
    public function afterSave()
    {
        if ($this->_relationsSaveStarted === false) {
            /** @var BaseActiveRecord $owner */
            $owner = $this->owner;
            $this->_relationsSaveStarted = true;
            // Populate relations with updated values
            foreach ($this->_newRelationValue as $name => $value) {
                $owner->populateRelation($name, $value);
            }
            try {
                foreach ($this->_relations as $relationName) {
                    if (array_key_exists($relationName, $this->_oldRelationValue)) { // Relation was not set, do nothing...
                        Yii::debug("Linking {$relationName} relation", __METHOD__);
                        /** @var ActiveQuery $relation */
                        $relation = $owner->getRelation($relationName);
                        if ($relation->multiple === true) { // Has many relation
                            $this->_afterSaveHasManyRelation($relationName);
                        } else { // Has one relation
                            $this->_afterSaveHasOneRelation($relationName);
                        }
                        unset($this->_oldRelationValue[$relationName]);
                    }
                }
            } catch (Exception $e) {
                Yii::warning(get_class($e) . ' was thrown while saving related records during afterSave event: ' . $e->getMessage(), __METHOD__);
                $this->_rollback();
                /***
                 * Sadly mandatory because the error occurred during afterSave event
                 * and we don't want the user/developper not to be aware of the issue.
                 ***/
                throw $e;
            }
            $owner->refresh();
            $this->_relationsSaveStarted = false;
            if (($this->_transaction instanceof Transaction) && $this->_transaction->isActive) {
                $this->_transaction->commit();
            }
        }
    }

    /**
     * Get the list of owner model relations in order to be able to delete them after its deletion
     */
    public function beforeDelete()
    {
        $model = $this->owner;
        foreach ($this->_relationsCascadeDelete as $relationName => $params) {
            if ($params === true) {
                $relation = $model->getRelation($relationName);
                if (!empty($model->{$relationName})) {
                    if ($relation->multiple === true) { // Has many relation
                        $this->_relationsToDelete = ArrayHelper::merge($this->_relationsToDelete, $model->{$relationName});
                    } else {
                        $this->_relationsToDelete[] = $model->{$relationName};
                    }
                }
            }
        }
    }

    /**
     * Delete related models marked as to be deleted
     * @throws Exception
     */
    public function afterDelete()
    {
        /** @var BaseActiveRecord $modelToDelete */
        foreach ($this->_relationsToDelete as $modelToDelete) {
            try {
                if (!$modelToDelete->delete()) {
                    throw new DbException('Could not delete the related record: ' . $modelToDelete::className() . '(' . VarDumper::dumpAsString($modelToDelete->primaryKey) . ')');
                }
            } catch (Exception $e) {
                Yii::warning(get_class($e) . ' was thrown while deleting related records during afterDelete event: ' . $e->getMessage(), __METHOD__);
                $this->_rollback();
                throw $e;
            }
        }
    }

    /**
     * Return array of columns to save to the junction table for a related model having a many-to-many relation.
     * @param string $relationName
     * @param BaseActiveRecord $model
     * @return array
     * @throws \RuntimeException
     */
    private function _getJunctionTableColumns($relationName, $model)
    {
        $junctionTableColumns = [];
        if (array_key_exists($relationName, $this->_relationsExtraColumns)) {
            if (is_callable($this->_relationsExtraColumns[$relationName])) {
                $junctionTableColumns = $this->_relationsExtraColumns[$relationName]($model);
            } elseif (is_array($this->_relationsExtraColumns[$relationName])) {
                $junctionTableColumns = $this->_relationsExtraColumns[$relationName];
            }
            if (!is_array($junctionTableColumns)) {
                throw new RuntimeException(
                    'Junction table columns definition must return an array, got ' . gettype($junctionTableColumns)
                );
            }
        }
        return $junctionTableColumns;
    }

    /**
     * Compute the difference between two set of records using primary keys "tokens"
     * If third parameter is set to true all initial related records will be marked for removal even if their
     * properties did not change. This can be handy in a many-to-many relation involving a junction table.
     * @param BaseActiveRecord[] $initialRelations
     * @param BaseActiveRecord[] $updatedRelations
     * @param bool $forceSave
     * @return array
     */
    private function _computePkDiff($initialRelations, $updatedRelations, $forceSave = false)
    {
        // Compute differences between initial relations and the current ones
        $oldPks = ArrayHelper::getColumn($initialRelations, function (BaseActiveRecord $model) {
            return implode('-', $model->getPrimaryKey(true));
        });
        $newPks = ArrayHelper::getColumn($updatedRelations, function (BaseActiveRecord $model) {
            return implode('-', $model->getPrimaryKey(true));
        });
        if ($forceSave) {
            $addedPks = $newPks;
            $deletedPks = $oldPks;
        } else {
            $identicalPks = array_intersect($oldPks, $newPks);
            $addedPks = array_values(array_diff($newPks, $identicalPks));
            $deletedPks = array_values(array_diff($oldPks, $identicalPks));
        }
        return [$addedPks, $deletedPks];
    }

    /**
     * Populates relations with input data
     * @param array $data
     */
    public function loadRelations($data)
    {
        /** @var BaseActiveRecord $model */
        $model = $this->owner;
        foreach ($this->_relations as $relationName) {
            /** @var ActiveQuery $relation */
            $relation = $model->getRelation($relationName);
            $modelClass = $relation->modelClass;
            /** @var ActiveQuery $relationalModel */
            $relationalModel = new $modelClass;
            $formName = $relationalModel->formName();
            if (array_key_exists($formName, $data)) {
                $model->{$relationName} = $data[$formName];
            }
        }
    }

    /**
     * @param $relationName
     * @throws DbException
     */
    public function _afterSaveHasManyRelation($relationName)
    {
        /** @var BaseActiveRecord $owner */
        $owner = $this->owner;
        /** @var ActiveQuery $relation */
        $relation = $owner->getRelation($relationName);

        // Process new relations
        $existingRecords = [];
        /** @var ActiveQuery $relationModel */
        foreach ($owner->{$relationName} as $i => $relationModel) {
            if ($relationModel->isNewRecord) {
                if (!empty($relation->via)) {
                    if ($relationModel->validate()) {
                        $relationModel->save();
                    } else {
                        $this->_addError($relationModel, $owner, $relationName, self::prettyRelationName($relationName, $i));
                        throw new DbException('Related record ' . self::prettyRelationName($relationName, $i) . ' could not be saved.');
                    }
                }
                $junctionTableColumns = $this->_getJunctionTableColumns($relationName, $relationModel);
                $owner->link($relationName, $relationModel, $junctionTableColumns);
            } else {
                $existingRecords[] = $relationModel;
            }
            if (count($relationModel->dirtyAttributes)) {
                if ($relationModel->validate()) {
                    $relationModel->save();
                } else {
                    $this->_addError($relationModel, $owner, $relationName, self::prettyRelationName($relationName));
                    throw new DbException('Related record ' . self::prettyRelationName($relationName) . ' could not be saved.');
                }
            }
        }
        $junctionTablePropertiesUsed = array_key_exists($relationName, $this->_relationsExtraColumns);

        // Process existing added and deleted relations
        list($addedPks, $deletedPks) = $this->_computePkDiff(
            $this->_oldRelationValue[$relationName],
            $existingRecords,
            $junctionTablePropertiesUsed
        );

        // Deleted relations
        $initialModels = ArrayHelper::index($this->_oldRelationValue[$relationName], function (BaseActiveRecord $model) {
            return implode('-', $model->getPrimaryKey(true));
        });
        $initialRelations = $owner->{$relationName};
        foreach ($deletedPks as $key) {
            $owner->unlink($relationName, $initialModels[$key], true);
        }

        // Added relations
        $actualModels = ArrayHelper::index(
            $junctionTablePropertiesUsed ? $initialRelations : $owner->{$relationName},
            function (BaseActiveRecord $model) {
                return implode('-', $model->getPrimaryKey(true));
            }
        );
        foreach ($addedPks as $key) {
            $junctionTableColumns = $this->_getJunctionTableColumns($relationName, $actualModels[$key]);
            $owner->link($relationName, $actualModels[$key], $junctionTableColumns);
        }
    }

    /**
     * @param $relationName
     * @throws \yii\base\InvalidCallException
     */
    private function _afterSaveHasOneRelation($relationName)
    {
        /** @var BaseActiveRecord $owner */
        $owner = $this->owner;

        if ($this->_oldRelationValue[$relationName] !== $owner->{$relationName}) {
            if ($owner->{$relationName} instanceof BaseActiveRecord) {
                $owner->link($relationName, $owner->{$relationName});
            } else {
                if ($this->_oldRelationValue[$relationName] instanceof BaseActiveRecord) {
                    $owner->unlink($relationName, $this->_oldRelationValue[$relationName]);
                }
            }
        }
        if ($owner->{$relationName} instanceof BaseActiveRecord) {
            $owner->{$relationName}->save();
        }
    }

    /**
     * @param BaseActiveRecord $model
     * @param $relationName
     */
    private function _prepareHasManyRelation(BaseActiveRecord $model, $relationName)
    {
        /** @var BaseActiveRecord $relationModel */
        foreach ($model->{$relationName} as $i => $relationModel) {
            $this->validateRelationModel(self::prettyRelationName($relationName, $i), $relationName, $relationModel);
        }
    }

    /**
     * @param BaseActiveRecord $model
     * @param ModelEvent $event
     * @param $relationName
     */
    private function _prepareHasOneRelation(BaseActiveRecord $model, $relationName, ModelEvent $event)
    {
        /** @var ActiveQuery $relation */
        $relation = $model->getRelation($relationName);
        $relationModel = $model->{$relationName};
        $p1 = $model->isPrimaryKey(array_keys($relation->link));
        $p2 = $relationModel::isPrimaryKey(array_values($relation->link));
        if ($relationModel->getIsNewRecord() && $p1 && !$p2) {
            // Save Has one relation new record
            $this->validateRelationModel(self::prettyRelationName($relationName), $relationName, $model->{$relationName});
            if ($event->isValid && (count($model->dirtyAttributes) || $model->{$relationName}->isNewRecord)) {
                Yii::debug('Saving ' . self::prettyRelationName($relationName) . ' relation model', __METHOD__);
                $model->{$relationName}->save(false);
            }
        } else {
            $this->validateRelationModel(self::prettyRelationName($relationName), $relationName, $relationModel);
        }
    }

    /**
     * @param BaseActiveRecord $model
     */
    protected function startTransactionForModel(BaseActiveRecord $model)
    {
        if ($this->isModelTransactional($model) && is_null($model->getDb()->transaction)) {
            $this->_transaction = $model->getDb()->beginTransaction();
        }
    }


    /**
     * @param BaseActiveRecord $model
     * @return bool
     */
    protected function isModelTransactional(BaseActiveRecord $model)
    {
        if (method_exists($model, 'isTransactional')) {
            return ($model->isNewRecord && $model->isTransactional($model::OP_INSERT))
                || (!$model->isNewRecord && $model->isTransactional($model::OP_UPDATE))
                || $model->isTransactional($model::OP_ALL);
        }
        return false;
    }

    /**
     * Load existing model or create one if no key was provided and data is not empty
     * @param $data
     * @param $fks
     * @param $modelClass
     * @return BaseActiveRecord
     */
    private function _loadOrCreateRelationModel($data, $fks, $modelClass)
    {
        /** @var BaseActiveRecord $relationModel */
        $relationModel = null;
        if (!empty($fks)) {
            $relationModel = $modelClass::findOne($fks);
        }
        if (!($relationModel instanceof BaseActiveRecord) && !empty($data)) {
            $relationModel = new $modelClass;
        }
        if (($relationModel instanceof BaseActiveRecord) && is_array($data)) {
            $relationModel->setAttributes($data);
        }
        return $relationModel;
    }

    /**
     * Get the related model foreign keys
     * @param $data
     * @param $relation
     * @param BaseActiveRecord $modelClass
     * @return array
     */
    private function _getRelatedFks($data, $relation, $modelClass)
    {
        $fks = [];
        if (is_array($data)) {
            // search PK
            foreach ($modelClass::primaryKey() as $modelAttribute) {
                if (array_key_exists($modelAttribute, $data) && !empty($data[$modelAttribute])) {
                    $fks[$modelAttribute] = $data[$modelAttribute];
                } else {
                    $fks = [];
                    break;
                }
            }
            if (empty($fks)) {
                // Get the right link definition
                if ($relation->via instanceof BaseActiveRecord) {
                    $link = $relation->via->link;
                } elseif (is_array($relation->via)) {
                    list($viaName, $viaQuery) = $relation->via;
                    $link = $viaQuery->link;
                } else {
                    $link = $relation->link;
                }
                foreach ($link as $relatedAttribute => $modelAttribute) {
                    if (array_key_exists($modelAttribute, $data) && !empty($data[$modelAttribute])) {
                        $fks[$modelAttribute] = $data[$modelAttribute];
                    }
                }
            }
        } else {
            $fks = $data;
        }
        return $fks;
    }
}
