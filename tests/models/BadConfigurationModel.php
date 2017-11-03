<?php
/**
 * @link http://www.lahautesociete.com
 * @copyright Copyright (c) 2016 La Haute Société
 */

namespace tests\models;

use lhs\Yii2SaveRelationsBehavior\SaveRelationsBehavior;

/**
 * DummyModel class
 *
 * @author albanjubert
 **/
class BadConfigurationModel extends \yii\db\ActiveRecord
{

    /**
     * @inheritdoc
     */
    public static function tableName()
    {
        return 'dummy';
    }

    /**
     * @inheritdoc
     */
    public function behaviors()
    {
        return [
            'saveRelations' => [
                'class'     => SaveRelationsBehavior::className(),
                'relations' => ['children']
            ],
        ];
    }

    /**
     * @return \yii\db\ActiveQuery
     */
    public function getChildren()
    {
        return $this->hasOne(DummyModel::className(), ['id' => 'parent_id']);
    }

}