<?php

namespace tests\models;

class ProjectLink extends \yii\db\ActiveRecord
{

    public $blockDelete = false;

    /**
     * @inheritdoc
     */
    public static function tableName()
    {
        return 'project_link';
    }

    /**
     * @inheritdoc
     */
    public function rules()
    {
        return [
            [['language', 'name', 'project_id'], 'required'],
            [['language', 'name'], 'unique']
        ];
    }

    public function beforeDelete()
    {
        if ($this->blockDelete === true) {
            return false;
        } else {
            return parent::beforeDelete();
        }
    }

}
