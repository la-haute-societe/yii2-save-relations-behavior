Yii2 Active Record Save Relations Behavior
==========================================
Automatically validate and save related Active Record models.

[![Latest Stable Version](https://poser.pugx.org/la-haute-societe/yii2-save-relations-behavior/v/stable)](https://packagist.org/packages/la-haute-societe/yii2-save-relations-behavior) [![Total Downloads](https://poser.pugx.org/la-haute-societe/yii2-save-relations-behavior/downloads)](https://packagist.org/packages/la-haute-societe/yii2-save-relations-behavior) [![Latest Unstable Version](https://poser.pugx.org/la-haute-societe/yii2-save-relations-behavior/v/unstable)](https://packagist.org/packages/la-haute-societe/yii2-save-relations-behavior) [![License](https://poser.pugx.org/la-haute-societe/yii2-save-relations-behavior/license)](https://packagist.org/packages/la-haute-societe/yii2-save-relations-behavior)

Features
--------
- Both `hasMany()` and `hasOne()` relations are supported
- Works with existing as well as new related models
- Composite primary keys are supported
- Only pure Active Record API is used so it should work with any DB driver

Installation
------------

The preferred way to install this extension is through [composer](http://getcomposer.org/download/).

Either run

```
php composer.phar require --prefer-dist la-haute-societe/yii2-save-relations-behavior "*"
```

or add

```
"la-haute-societe/yii2-save-relations-behavior": "*"
```

to the require section of your `composer.json` file.


Configuring
-----------

Configure model as follows
```php
use lhs\Yii2SaveRelationsBehavior\SaveRelationsBehavior;

class Project extends \yii\db\ActiveRecord
{
    use SaveRelationsTrait; // Optional

    public function behaviors()
    {
        return [
            'timestamp'     => TimestampBehavior::className(),
            'blameable'     => BlameableBehavior::className(),
            ...
            'saveRelations' => [
                'class'     => SaveRelationsBehavior::className(),
                'relations' => ['users', 'company', 'tags'],
                'junctionTableColumns' => [
                    'tags' => function ($model) {
                        return [
                            'order' => $model->order
                        ];
                    }
                ]
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
    public function getProjectUsers()
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
    
    /**
     * @return ActiveQuery
     */
    public function getTags()
    {
        return $this->hasMany(Tag::className(), ['id' => 'tag_id'])->viaTable('ProjectTags', ['project_id' => 'id']);
    }

}
```
> Though not mandatory, it is highly recommended to activate the transactions for the owner model.


Usage
-----

Every declared relations in the `relations` behavior parameter can now be set and saved as follow:
```php
$project = new Project();
$project->name = "New project";
$project->company = Company::findOne(2);
$project->users = User::findAll([1,3]);
$project->save();
```
You can set related model by only specifying its primary key:
```php
$project = new Project();
$project->name = "Another project";
$project->company = 2;
$project->users = [1,3];
$project->save();
```
You can even set related models as associative arrays like this:

```php
$project = Project::findOne(1);
$project->company = ['name' => 'GiHub', 'description' => 'Awesome']; // Will create a new company record
// $project->company = ['id' => 3, 'name' => 'GiHub', 'description' => 'Awesome']; // Will update an existing company record
$project->save();
```
Attributes of the related model will be massively assigned using the `load() method. So remember to declare the according attributes as safe in the rules of the related model.

> **Notes:**
> - Related models are saved during the EVENT_BEFORE_VALIDATE event of the owner model. Transaction will start at this point according to the transactions() method of the model.
> - Only newly created or changed related models will be saved.

> See the PHPUnit tests for more examples.

Populate additional junction table columns in a many-to-many relation
---------------------------------------------------------------------
In a many-to-many relation involving a junction table additional column values can be saved to the junction table for each model.
Note that if junction table properties are configured for a relation the rows associated with the related models in the junction table will be deleted and inserted again on each saving
to ensure that changes to the junction table properties are saved too.
See the configuration section for examples.


Validation
----------
Every declared related models will be validated prior to be saved. If any validation fails, for each related model attribute in error, an error associated with the named relation will be added to the owner model.

For `hasMany()` relations, the index of the related model will be used to identify the associated error message.


> **Tips :**
> For relations not involving a junction table by using the `via()` or `viaTable()` methods, you should remove the attributes pointing to the owner model from the 'required' validation rules to be able to pass the validations.

Populate the model and its relations with input data
----------------------------------------------------
This behavior adds a convenient method to load relations models attributes in the same way that the load() method does.
Simply call the `loadRelations()` with the according input data.

For instance:
```php
$project = Project::findOne(1);
/**
 * $_POST could be something like:
 * [
 *     'Company'     => [
 *         'name' => 'YiiSoft'
 *     ],
 *     'ProjectLink' => [
 *         [
 *             'language' => 'en',
 *             'name'     => 'yii',
 *             'link'     => 'http://www.yiiframework.com'
 *         ],
 *         [
 *             'language' => 'fr',
 *             'name'     => 'yii',
 *             'link'     => 'http://www.yiiframework.fr'
 *         ]
 *     ]
 * ];
 */
$project->loadRelations(Yii::$app->request->post());
```

You can even further simplify the process by adding the `SaveRelationsTrait` to your model.
In that case, a call to the `load()` method will also automatically trigger a call to the `loadRelations()` method by using the same data, so you basically won't have to change your controllers.