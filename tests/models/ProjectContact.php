<?php

namespace tests\models;

class ProjectContact extends \yii\db\ActiveRecord
{

    public $blockDelete = false;

    /**
     * @inheritdoc
     */
    public static function tableName()
    {
        return 'project_contact';
    }

    /**
     * @inheritdoc
     */
    public function rules()
    {
        return [
            [['project_id'], 'integer'],
            [['email'], 'required'],
            [['email', 'phone'], 'string']
        ];
    }

}
