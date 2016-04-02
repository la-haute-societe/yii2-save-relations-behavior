<?php

namespace tests\models;

use lhs\Yii2SaveRelationsBehavior\SaveRelationsBehavior;

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
    public function behaviors()
    {
        return [
            'saveRelations' => [
                'class'     => SaveRelationsBehavior::className(),
                'relations' => ['linkType']
            ],
        ];
    }

    /**
     * @inheritdoc
     */
    public function rules()
    {
        return [
            [['language', 'name', 'link'], 'required'],
            [['name'], 'unique', 'targetAttribute' => ['language', 'name']],
            [['link_type_id'], 'safe']
        ];
    }

    /**
     * @return \yii\db\ActiveQuery
     */
    public function getLinkType()
    {
        return $this->hasOne(LinkType::className(), ['id' => 'link_type_id']);
    }
}
