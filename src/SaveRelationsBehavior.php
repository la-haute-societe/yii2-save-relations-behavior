<?php

namespace lhs\Yii2SaveRelationsBehavior;

use RuntimeException;
use Yii;
use yii\base\Behavior;
use yii\base\Exception;
use yii\base\ModelEvent;
use yii\base\UnknownPropertyException;
use yii\db\ActiveQueryInterface;
use yii\db\BaseActiveRecord;
use Yii\db\Exception as DbException;
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
    private $_oldRelationValue = [];
    private $_relationsSaveStarted = false;
    private $_transaction;

    private $_relationsScenario = [];

    //private $_relationsCascadeDelete = []; //TODO

    public function init()
    {
        parent::init();
        $allowedProperties = ['scenario'];
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
        $getter = 'get' . $name;
        if (in_array($name, $this->_relations) && method_exists($this->owner, $getter) && $this->owner->$getter() instanceof ActiveQueryInterface) {
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
            Yii::trace("Setting {$name} relation value", __METHOD__);
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
     */
    public function beforeValidate(ModelEvent $event)
    {
        if ($this->_relationsSaveStarted == false && !empty($this->_oldRelationValue)) {
            /* @var $model BaseActiveRecord */
            $model = $this->owner;
            if ($this->saveRelatedRecords($model, $event)) {
                // If relation is has_one, try to set related model attributes
                foreach ($this->_relations as $relationName) {
                    if (array_key_exists($relationName, $this->_oldRelationValue)) { // Relation was not set, do nothing...
                        $relation = $model->getRelation($relationName);
                        if ($relation->multiple === false && !empty($model->{$relationName})) {
                            Yii::trace("Setting foreign keys for {$relationName}", __METHOD__);
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
     * or be saved, a rollback will occur.
     * This is done during the before validation process to be able to set the related foreign keys.
     * @param BaseActiveRecord $model
     * @param ModelEvent $event
     * @return bool
     */
    protected function saveRelatedRecords(BaseActiveRecord $model, ModelEvent $event)
    {
        if (($model->isNewRecord && $model->isTransactional($model::OP_INSERT)) || (!$model->isNewRecord && $model->isTransactional($model::OP_UPDATE)) || $model->isTransactional($model::OP_ALL)) {
            $this->_transaction = $model->getDb()->beginTransaction();
        }
        try {
            foreach ($this->_relations as $relationName) {
                if (array_key_exists($relationName, $this->_oldRelationValue)) { // Relation was not set, do nothing...
                    $relation = $model->getRelation($relationName);
                    if (!empty($model->{$relationName})) {
                        if ($relation->multiple === false) {
                            // Save Has one relation new record
                            $pettyRelationName = Inflector::camel2words($relationName, true);
                            $this->saveModelRecord($model->{$relationName}, $event, $pettyRelationName, $relationName);
                        } else {
                            // Save Has many relations new records
                            /** @var BaseActiveRecord $relationModel */
                            foreach ($model->{$relationName} as $i => $relationModel) {
                                $pettyRelationName = Inflector::camel2words($relationName, true) . " #{$i}";
                                $this->validateRelationModel($pettyRelationName, $relationName, $relationModel, $event);
                            }
                        }
                    }
                }
            }
            if (!$event->isValid) {
                throw new Exception("One of the related model could not be validated");
            }
        } catch (Exception $e) {
            Yii::warning(get_class($e) . " was thrown while saving related records during beforeValidate event: " . $e->getMessage(), __METHOD__);
            $this->_rollback();
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
            Yii::trace("Saving {$pettyRelationName} relation model", __METHOD__);
            $model->save(false);
        }
    }

    /**
     * Validate a relation model and add an error message to owner model attribute if needed
     * @param string $pettyRelationName
     * @param string $relationName
     * @param BaseActiveRecord $relationModel
     * @param ModelEvent $event
     */
    protected function validateRelationModel($pettyRelationName, $relationName, BaseActiveRecord $relationModel, ModelEvent $event)
    {
        /** @var BaseActiveRecord $model */
        $model = $this->owner;
        if (!is_null($relationModel) && ($relationModel->isNewRecord || count($relationModel->getDirtyAttributes()))) {
            if (key_exists($relationName, $this->_relationsScenario)) {
                $relationModel->setScenario($this->_relationsScenario[$relationName]);
            }
            Yii::trace("Validating {$pettyRelationName} relation model using " . $relationModel->scenario . " scenario", __METHOD__);
            if (!$relationModel->validate()) {
                $this->_addError($relationModel, $model, $relationName, $pettyRelationName);
            }
        }
    }

    /**
     * Link the related models.
     * If the models have not been changed, nothing will be done.
     * Related records will be linked to the owner model using the BaseActiveRecord `link()` method.
     */
    public function afterSave()
    {
        if ($this->_relationsSaveStarted == false) {
            /** @var BaseActiveRecord $model */
            $model = $this->owner;
            $this->_relationsSaveStarted = true;
            try {


                foreach ($this->_relations as $relationName) {
                    if (array_key_exists($relationName, $this->_oldRelationValue)) { // Relation was not set, do nothing...
                        Yii::trace("Linking {$relationName} relation", __METHOD__);
                        $relation = $model->getRelation($relationName);
                        if ($relation->multiple === true) { // Has many relation
                            // Process new relations
                            $existingRecords = [];

                            /** @var BaseActiveRecord $relationModel */
                            foreach ($model->{$relationName} as $i => $relationModel) {
                                if ($relationModel->isNewRecord) {
                                    if ($relation->via !== null) {
                                        if ($relationModel->validate()) {
                                            $relationModel->save();
                                        } else {
                                            $pettyRelationName = Inflector::camel2words($relationName, true) . " #{$i}";
                                            $this->_addError($relationModel, $model, $relationName, $pettyRelationName);
                                            throw new DbException("Related record {$pettyRelationName} could not be saved.");
                                        }
                                    }
                                    $model->link($relationName, $relationModel);
                                } else {
                                    $existingRecords[] = $relationModel;
                                }
                                if (count($relationModel->dirtyAttributes)) {
                                    if ($relationModel->validate()) {
                                        $relationModel->save();
                                    } else {
                                        $pettyRelationName = Inflector::camel2words($relationName, true);
                                        $this->_addError($relationModel, $model, $relationName, $pettyRelationName);
                                        throw new DbException("Related record {$pettyRelationName} could not be saved.");
                                    }
                                }
                            }
                            // Process existing added and deleted relations
                            list($addedPks, $deletedPks) = $this->_computePkDiff($this->_oldRelationValue[$relationName], $existingRecords);
                            // Deleted relations
                            $initialModels = ArrayHelper::index($this->_oldRelationValue[$relationName], function (BaseActiveRecord $model) {
                                return implode("-", $model->getPrimaryKey(true));
                            });
                            foreach ($deletedPks as $key) {
                                $model->unlink($relationName, $initialModels[$key], true);
                            }
                            // Added relations
                            $actualModels = ArrayHelper::index($model->{$relationName}, function (BaseActiveRecord $model) {
                                return implode("-", $model->getPrimaryKey(true));
                            });
                            foreach ($addedPks as $key) {
                                $model->link($relationName, $actualModels[$key]);
                            }
                        } else { // Has one relation
                            if ($this->_oldRelationValue[$relationName] !== $model->{$relationName}) {
                                if ($model->{$relationName} instanceof BaseActiveRecord) {
                                    $model->link($relationName, $model->{$relationName});
                                } else {
                                    if ($this->_oldRelationValue[$relationName] instanceof BaseActiveRecord) {
                                        $model->unlink($relationName, $this->_oldRelationValue[$relationName]);
                                    }
                                }
                            }
                        }
                        unset($this->_oldRelationValue[$relationName]);
                    }
                }
            } catch (Exception $e) {
                Yii::warning(get_class($e) . " was thrown while saving related records during afterSave event: " . $e->getMessage(), __METHOD__);
                $this->_rollback();
                /***
                 * Sadly mandatory because the error occurred during afterSave event
                 * and we don't want the user/developper not to be aware of the issue.
                 ***/
                throw $e;
            }
            $model->refresh();
            $this->_relationsSaveStarted = false;
            if (($this->_transaction instanceof Transaction) && $this->_transaction->isActive) {
                $this->_transaction->commit();
            }
        }
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
            /** @var BaseActiveRecord $relationalModel */
            $relationalModel = new $modelClass;
            $formName = $relationalModel->formName();
            if (array_key_exists($formName, $data)) {
                $model->{$relationName} = $data[$formName];
            }
        }
    }

    /**
     * Compute the difference between two set of records using primary keys "tokens"
     * @param BaseActiveRecord[] $initialRelations
     * @param BaseActiveRecord[] $updatedRelations
     * @return array
     */
    private function _computePkDiff($initialRelations, $updatedRelations)
    {
        // Compute differences between initial relations and the current ones
        $oldPks = ArrayHelper::getColumn($initialRelations, function (BaseActiveRecord $model) {
            return implode("-", $model->getPrimaryKey(true));
        });
        $newPks = ArrayHelper::getColumn($updatedRelations, function (BaseActiveRecord $model) {
            return implode("-", $model->getPrimaryKey(true));
        });
        $identicalPks = array_intersect($oldPks, $newPks);
        $addedPks = array_values(array_diff($newPks, $identicalPks));
        $deletedPks = array_values(array_diff($oldPks, $identicalPks));
        return [$addedPks, $deletedPks];
    }

    /**
     * Attach errors to owner relational attributes
     * @param $relationModel
     * @param $owner
     * @param $relationName
     * @param $pettyRelationName
     * @return array
     */
    private function _addError($relationModel, $owner, $relationName, $pettyRelationName)
    {
        foreach ($relationModel->errors as $attributeErrors) {
            foreach ($attributeErrors as $error) {
                $owner->addError($relationName, "{$pettyRelationName}: {$error}");
            }
        }
    }

    private function _rollback()
    {
        if (($this->_transaction instanceof Transaction) && $this->_transaction->isActive) {
            $this->_transaction->rollBack(); // If anything goes wrong, transaction will be rolled back
            Yii::info("Rolling back", __METHOD__);
        }
    }
}
