<?php

namespace tests\models;

use lhs\Yii2SaveRelationsBehavior\SaveRelationsBehavior;
use lhs\Yii2SaveRelationsBehavior\SaveRelationsTrait;

class Project extends \yii\db\ActiveRecord
{
    use SaveRelationsTrait;

    /**
     * @inheritdoc
     */
    public static function tableName()
    {
        return 'project';
    }

    /**
     * @inheritdoc
     */
    public function behaviors()
    {
        return [
            'saveRelations' => [
                'class'     => SaveRelationsBehavior::className(),
                'relations' => [
                    'company',
                    'users',
                    'contacts',
                    'images',
                    'links'        => ['scenario' => Link::SCENARIO_FIRST],
                    'projectLinks' => ['cascadeDelete' => true],
                    'tags'         => [
                        'extraColumns' => function ($model) {
                            /** @var $model Tag */
                            return [
                                'order' => $model->order
                            ];
                        }
                    ]
                ],
            ],
        ];
    }

    /**
     * @inheritdoc
     */
    public function rules()
    {
        return [
            [['name', 'company_id'], 'required'],
            [['name'], 'unique', 'targetAttribute' => ['company_id', 'name']],
            [['company', 'links', 'users'], 'safe']
        ];
    }

    /**
     * @inheritdoc
     */
    public function transactions()
    {
        return [
            self::SCENARIO_DEFAULT => self::OP_ALL,
        ];
    }

    /**
     * @return \yii\db\ActiveQuery
     */
    public function getCompany()
    {
        return $this->hasOne(Company::className(), ['id' => 'company_id']);
    }

    /**
     * @return \yii\db\ActiveQuery
     */
    public function getProjectUsers()
    {
        return $this->hasMany(ProjectUser::className(), ['project_id' => 'id']);
    }

    /**
     * @return \yii\db\ActiveQuery
     */
    public function getUsers()
    {
        return $this->hasMany(User::className(), ['id' => 'user_id'])->via('projectUsers', function ($query) {
            return $query;
        });
    }

    /**
     * @return \yii\db\ActiveQuery
     */
    public function getProjectLinks()
    {
        return $this->hasMany(ProjectLink::className(), ['project_id' => 'id']);
    }

    /**
     * @return \yii\db\ActiveQuery
     */
    public function getContacts()
    {
        return $this->hasMany(ProjectContact::className(), ['project_id' => 'id']);
    }

    /**
     * @return \yii\db\ActiveQuery
     */
    public function getImages()
    {
        return $this->hasMany(ProjectImage::className(), ['project_id' => 'id']);
    }

    /**
     * @return \yii\db\ActiveQuery
     */
    public function getLinks()
    {
        return $this->hasMany(Link::className(), ['language' => 'language', 'name' => 'name'])->via('projectLinks');
    }

    /**
     * @return ActiveQuery
     */
    public function getTags()
    {
        return $this->hasMany(Tag::className(), ['id' => 'tag_id'])->viaTable('project_tags', ['project_id' => 'id']);
    }

}
