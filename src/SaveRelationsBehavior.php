<?php

namespace lhs\Yii2SaveRelationsBehavior;

use RuntimeException;
use Yii;
use yii\base\Behavior;
use yii\base\Exception;
use yii\base\ModelEvent;
use yii\base\UnknownPropertyException;
use yii\db\ActiveQuery;
use yii\db\BaseActiveRecord;
use yii\db\Exception as DbException;
use yii\db\Transaction;
use yii\helpers\ArrayHelper;
use yii\helpers\Inflector;

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
    private $_relationsSaveStarted = false;
    private $_transaction;


    private $_relationsScenario = [];
    private $_relationsExtraColumns = [];

    //private $_relationsCascadeDelete = []; //TODO

    /**
     * @inheritdoc
     */
    public function init()
    {
        parent::init();
        $allowedProperties = ['scenario', 'extraColumns'];
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
        ];
    }

    /**
     * Check if the behavior is attached to an Active Record
     * @param BaseActiveRecord $owner
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
        if (in_array($name, $this->_relations) && $this->owner->getRelation($name, false)) {
            return true;
        }
        return parent::canSetProperty($name, $checkVars);
    }

    /**
     * Override __set method to be able to set relations values either by providing a model instance,
     * a primary key value or an associative array
     * @param string $name
     * @param mixed $value
     */
    public function __set($name, $value)
    {
        if (in_array($name, $this->_relations)) {
            Yii::debug("Setting {$name} relation value", __METHOD__);
            if (!isset($this->_oldRelationValue[$name])) {
                if ($this->owner->isNewRecord) {
                    if ($this->owner->getRelation($name)->multiple === true) {
                        $this->_oldRelationValue[$name] = [];
                    } else {
                        $this->_oldRelationValue[$name] = null;
                    }
                } else {
                    $this->_oldRelationValue[$name] = $this->owner->{$name};
                }
            }
            if ($this->owner->getRelation($name)->multiple === true) {
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
     */
    protected function setSingleRelation($name, $value)
    {
        $relation = $this->owner->getRelation($name);
        if (!($value instanceof $relation->modelClass)) {
            $value = $this->processModelAsArray($value, $relation);
        }
        $this->_newRelationValue[$name] = $value;
        $this->owner->populateRelation($name, $value);
    }

    /**
     * Set the named multiple relation with the given value
     * @param $name
     * @param $value
     */
    protected function setMultipleRelation($name, $value)
    {
        $relation = $this->owner->getRelation($name);
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
        $this->owner->populateRelation($name, $newRelations);
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
        // Get the related model foreign keys
        if (is_array($data)) {
            $fks = [];

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
                    $viaQuery = $relation->via;
                    $link = $viaQuery->link;
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
        // Load existing model or create one if no key was provided and data is not empty
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
        if (
            method_exists($model, 'isTransactional')
            && is_null($model->getDb()->transaction)
            && (
                ($model->isNewRecord && $model->isTransactional($model::OP_INSERT))
                || (!$model->isNewRecord && $model->isTransactional($model::OP_UPDATE))
                || $model->isTransactional($model::OP_ALL)
            )
        ) {
            $this->_transaction = $model->getDb()->beginTransaction();
        }
        try {
            foreach ($this->_relations as $relationName) {
                if (array_key_exists($relationName, $this->_oldRelationValue)) { // Relation was not set, do nothing...
                    /** @var ActiveQuery $relation */
                    $relation = $model->getRelation($relationName);
                    if (!empty($model->{$relationName})) {
                        if ($relation->multiple === false) {
                            $relationModel = $model->{$relationName};
                            $p1 = $model->isPrimaryKey(array_keys($relation->link));
                            $p2 = $relationModel::isPrimaryKey(array_values($relation->link));
                            $pettyRelationName = Inflector::camel2words($relationName, true);
                            if ($relationModel->getIsNewRecord() && $p1 && !$p2) {
                                // Save Has one relation new record
                                $this->saveModelRecord($model->{$relationName}, $event, $pettyRelationName, $relationName);
                            } else {
                                $this->validateRelationModel($pettyRelationName, $relationName, $relationModel);
                            }
                        } else {
                            // Save Has many relations new records
                            /** @var BaseActiveRecord $relationModel */
                            foreach ($model->{$relationName} as $i => $relationModel) {
                                $pettyRelationName = Inflector::camel2words($relationName, true) . " #{$i}";
                                $this->validateRelationModel($pettyRelationName, $relationName, $relationModel);
                            }
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
     * Validate and save the model if it is new or changed
     * @param BaseActiveRecord $model
     * @param ModelEvent $event
     * @param $pettyRelationName
     * @param $relationName
     */
    protected function saveModelRecord(BaseActiveRecord $model, ModelEvent $event, $pettyRelationName, $relationName)
    {
        $this->validateRelationModel($pettyRelationName, $relationName, $model, $event);
        if ($event->isValid && (count($model->dirtyAttributes) || $model->isNewRecord)) {
            Yii::debug("Saving {$pettyRelationName} relation model", __METHOD__);
            $model->save(false);
        }
    }

    /**
     * Validate a relation model and add an error message to owner model attribute if needed
     * @param string $pettyRelationName
     * @param string $relationName
     * @param BaseActiveRecord $relationModel
     */
    protected function validateRelationModel($pettyRelationName, $relationName, BaseActiveRecord $relationModel)
    {
        /** @var BaseActiveRecord $model */
        $model = $this->owner;
        if (!is_null($relationModel) && ($relationModel->isNewRecord || count($relationModel->getDirtyAttributes()))) {
            if (array_key_exists($relationName, $this->_relationsScenario)) {
                $relationModel->setScenario($this->_relationsScenario[$relationName]);
            }
            Yii::debug("Validating {$pettyRelationName} relation model using " . $relationModel->scenario . ' scenario', __METHOD__);
            if (!$relationModel->validate()) {
                $this->_addError($relationModel, $model, $relationName, $pettyRelationName);
            }
        }
    }

    /**
     * Attach errors to owner relational attributes
     * @param $relationModel
     * @param $owner
     * @param $relationName
     * @param $pettyRelationName
     */
    private function _addError($relationModel, $owner, $relationName, $pettyRelationName)
    {
        foreach ($relationModel->errors as $attributeErrors) {
            foreach ($attributeErrors as $error) {
                $owner->addError($relationName, "{$pettyRelationName}: {$error}");
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
                $this->owner->populateRelation($name, $value);
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
        $owner = $this->owner;
        $relation = $owner->getRelation($relationName);

        if ($this->_hasActiveRelationTrait($relation)) {
            // Process new relations
            $existingRecords = [];
            /** @var ActiveQuery $relationModel */
            foreach ($owner->{$relationName} as $i => $relationModel) {
                if ($relationModel->isNewRecord) {
                    if ($relation->via !== null) {
                        if ($relationModel->validate()) {
                            $relationModel->save();
                        } else {
                            $pettyRelationName = Inflector::camel2words($relationName, true) . " #{$i}";
                            $this->_addError($relationModel, $owner, $relationName, $pettyRelationName);
                            throw new DbException("Related record {$pettyRelationName} could not be saved.");
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
                        $pettyRelationName = Inflector::camel2words($relationName, true);
                        $this->_addError($relationModel, $owner, $relationName, $pettyRelationName);
                        throw new DbException("Related record {$pettyRelationName} could not be saved.");
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
    }

    /**
     * @param $relationName
     */
    private function _afterSaveHasOneRelation($relationName)
    {
        $owner = $this->owner;
        $relation = $owner->getRelation($relationName);

        if ($this->_hasActiveRelationTrait($relation)) {
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
    }

    /**
     * @param ActiveQuery $relation
     * @return bool
     */
    private function _hasActiveRelationTrait(ActiveQuery $relation)
    {
        $relationTraits = class_uses($relation);
        return in_array('yii\db\ActiveRelationTrait', $relationTraits);
    }
}
