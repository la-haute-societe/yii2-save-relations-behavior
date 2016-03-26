Yii2 Active Record Save Relations Behavior
==========================================
Automatically validate and save Active Record related models.
Both Has Many and Has One relations are supported.

Installation
------------

The preferred way to install this extension is through [composer](http://getcomposer.org/download/).

Either run

```
php composer.phar require --prefer-dist lhs/yii2-save-relations-behavior "*"
```

or add

```
"lhs/yii2-save-relations-behavior": "*"
```

to the require section of your `composer.json` file.


Configuring
-----------

Configure model as follows
```php
use lhs\Yii2SaveRelationsBehavior\SaveRelationsBehavior;

class Project extends \yii\db\ActiveRecord
{
    public function behaviors()
    {
        return [
            'timestamp'     => TimestampBehavior::className(),
            'blameable'     => BlameableBehavior::className(),
            ...
            'saveRelations' => [
                'class'     => SaveRelationsBehavior::className(),
                'relations' => ['users', 'company']
            ],
        ];
    }

    public function transactions()
    {
        return [
            self::SCENARIO_DEFAULT => self::OP_ALL,
        ];
    }

    ...


    /**
     * @return ActiveQuery
     */
    public function getCompany()
    {
        return $this->hasOne(Company::className(), ['id' => 'company_id']);
    }

    /**
     * @return ActiveQuery
     */
    public function getMyModelUsers()
    {
        return $this->hasMany(ProjectUser::className(), ['project_id' => 'id']);
    }

    /**
     * @return ActiveQuery
     */
    public function getUsers()
    {
        return $this->hasMany(User::className(), ['id' => 'user_id'])->via('ProjectUsers');
    }

}
```
Though not mandatory, it is highly recommended to activate the transactions


Usage
-----

Every declared relations in the `relations` behavior parameter can now be set as follow:
```php
// Has one relation using a model
$model = MyModel::findOne(321);
$company = Company::findOne(123);
$model->company = $company;
$model->save();
```

or

```php
// Has one relation using a foreign key
$model = MyModel::findOne(321);
$model->company = 123; // or $model->company = ['id' => 123];
$model->save();
```

