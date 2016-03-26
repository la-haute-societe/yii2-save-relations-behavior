<?php

namespace tests\models;

class Link extends \yii\db\ActiveRecord
{
    /**
     * @inheritdoc
     */
    public static function tableName()
    {
        return 'link';
    }

    /**
     * @inheritdoc
     */
    public function rules()
    {
        return [
            [['language', 'name', 'link'], 'required'],
            [['name'], 'unique', 'targetAttribute' => ['language', 'name']],
        ];
    }

}
