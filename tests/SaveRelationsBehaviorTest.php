<?php

namespace tests;

use lhs\Yii2SaveRelationsBehavior\SaveRelationsBehavior;
use RuntimeException;
use tests\models\Company;
use tests\models\DummyModel;
use tests\models\DummyModelParent;
use tests\models\Link;
use tests\models\Project;
use tests\models\ProjectNoTransactions;
use tests\models\Tag;
use tests\models\User;
use Yii;
use yii\base\Model;
use yii\db\Migration;
use yii\helpers\VarDumper;

class SaveRelationsBehaviorTest extends \PHPUnit_Framework_TestCase
{

    protected function setUp()
    {
        parent::setUp();
        $this->setupDbData();
    }

    protected function tearDown()
    {
        $db = Yii::$app->getDb();
        $db->createCommand()->dropTable('project_user')->execute();
        $db->createCommand()->dropTable('project')->execute();
        $db->createCommand()->dropTable('user')->execute();
        $db->createCommand()->dropTable('company')->execute();
        $db->createCommand()->dropTable('link_type')->execute();
        $db->createCommand()->dropTable('link')->execute();
        $db->createCommand()->dropTable('project_tags')->execute();
        $db->createCommand()->dropTable('tags')->execute();
        $db->createCommand()->dropTable('project_link')->execute();
        $db->createCommand()->dropTable('dummy')->execute();
        parent::tearDown();
    }

    protected function setupDbData()
    {
        /** @var \yii\db\Connection $db */
        $db = Yii::$app->getDb();
        $migration = new Migration();

        /**
         * Create tables
         **/

        // Company
        $db->createCommand()->createTable('company', [
            'id'   => $migration->primaryKey(),
            'name' => $migration->string()->notNull()->unique()
        ])->execute();

        // User
        $db->createCommand()->createTable('user', [
            'id'       => $migration->primaryKey(),
            'username' => $migration->string()->notNull()->unique()
        ])->execute();

        // Project
        $db->createCommand()->createTable('project', [
            'id'         => $migration->primaryKey(),
            'name'       => $migration->string()->notNull(),
            'company_id' => $migration->integer()->notNull(),
        ])->execute();

        $db->createCommand()->createIndex('company_id-name', 'project', 'company_id,name', true)->execute();

        $db->createCommand()->createTable('link', [
            'language'     => $migration->string(5)->notNull(),
            'name'         => $migration->string()->notNull(),
            'link'         => $migration->string()->notNull(),
            'link_type_id' => $migration->integer(),
            'PRIMARY KEY(language, name)'
        ])->execute();

        $db->createCommand()->createTable('tags', [
            'id'   => $migration->primaryKey(),
            'name' => $migration->string()->notNull()->unique()
        ])->execute();

        $db->createCommand()->createTable('project_tags', [
            'project_id' => $migration->integer()->notNull(),
            'tag_id'     => $migration->integer()->notNull(),
            'order'      => $migration->integer()->notNull()
        ])->execute();

        $db->createCommand()->createTable('link_type', [
            'id'   => $migration->primaryKey(),
            'name' => $migration->string()->notNull()->unique()
        ])->execute();

        $db->createCommand()->createTable('project_link', [
            'language'   => $migration->string(5)->notNull(),
            'name'       => $migration->string()->notNull(),
            'project_id' => $migration->integer()->notNull(),
            'PRIMARY KEY(language, name, project_id)'
        ])->execute();

        // Project User
        $db->createCommand()->createTable('project_user', [
            'project_id' => $migration->integer()->notNull(),
            'user_id'    => $migration->integer()->notNull(),
            'PRIMARY KEY(project_id, user_id)'
        ])->execute();

        // Dummy
        $db->createCommand()->createTable('dummy', [
            'id'        => $migration->primaryKey(),
            'parent_id' => $migration->integer()
        ])->execute();

        /**
         * Insert some data
         */

        $db->createCommand()->batchInsert('company', ['id', 'name'], [
            [1, 'Apple'],
            [2, 'Microsoft'],
            [3, 'Google'],
        ])->execute();

        $db->createCommand()->batchInsert('user', ['id', 'username'], [
            [1, 'Steve Jobs'],
            [2, 'Bill Gates'],
            [3, 'Tim Cook'],
            [4, 'Jonathan Ive']
        ])->execute();

        $db->createCommand()->batchInsert('project', ['id', 'name', 'company_id'], [
            [1, 'Mac OS X', 1],
            [2, 'Windows 10', 2]
        ])->execute();

        $db->createCommand()->batchInsert('link_type', ['id', 'name'], [
            [1, 'public'],
            [2, 'private']
        ])->execute();

        $db->createCommand()->batchInsert('link', ['language', 'name', 'link', 'link_type_id'], [
            ['fr', 'mac_os_x', 'http://www.apple.com/fr/osx/', 1],
            ['en', 'mac_os_x', 'http://www.apple.com/osx/', 1]
        ])->execute();

        $db->createCommand()->batchInsert('project_link', ['language', 'name', 'project_id'], [
            ['fr', 'mac_os_x', 1],
            ['en', 'mac_os_x', 1]
        ])->execute();

        $db->createCommand()->batchInsert('project_user', ['project_id', 'user_id'], [
            [1, 1],
            [1, 4],
            [2, 2]
        ])->execute();
    }

    /**
     * @expectedException RuntimeException
     */
    public function testCannotAttachBehaviorToAnythingButActiveRecord()
    {
        $model = new Model();
        $model->attachBehavior('saveRelated', SaveRelationsBehavior::className());
    }

    /**
     * @expectedException \yii\base\InvalidCallException
     */
    public function testTryToSetUndeclaredRelationShouldFail()
    {
        $project = new Project();
        $project->projectUsers = [];
    }

    public function testSaveExistingHasOneRelationAsModelShouldSucceed()
    {
        $project = new Project();
        $project->name = "iOS 9";
        $project->company = Company::findOne(1);
        $this->assertTrue($project->validate(), 'Project should be valid');
        $this->assertTrue($project->save(), 'Project could not be saved');
        $this->assertEquals(1, $project->company_id, 'Company ID is not the one expected');
    }

    public function testSaveExistingHasOneRelationAsIdShouldSucceed()
    {
        $project = new Project();
        $project->name = "GMail";
        $project->company = 3;
        $this->assertTrue($project->save(), 'Project could not be saved');
        $this->assertEquals(3, $project->company_id, 'Company ID is not the one expected');
    }

    public function testSaveNewHasOneRelationShouldSucceed()
    {
        $project = new Project();
        $project->name = "Java";
        $company = new Company();
        $company->name = "Oracle";
        $project->company = $company;
        $this->assertTrue($company->isNewRecord, 'Company should be a new record');
        $this->assertTrue($project->save(), 'Project could not be saved');
        $this->assertNotNull($project->company_id, 'Company ID should be set');
        $this->assertEquals($project->company_id, $company->id, 'Company ID is not the one expected');
    }

    public function testChangingHasOneRelationShouldSucceed()
    {
        $project = Project::findOne(1);
        $project->company = Company::findOne(2); // Change project company from Apple to Microsoft
        $this->assertTrue($project->save(), 'Project could be saved');
        $this->assertEquals(2, $project->company_id);
        $this->assertEquals('Microsoft', $project->company->name);
    }

    public function testSaveInvalidNewHasOneRelationShouldFail()
    {
        $project = new Project();
        $project->name = "Java";
        $company = new Company();
        $project->company = $company;
        $this->assertTrue($company->isNewRecord, 'Company should be a new record');
        $this->assertFalse($project->save(), 'Project could be saved');
        $this->assertArrayHasKey(
            'company',
            $project->getErrors(),
            'Validation errors do not contain a message for company'
        );
        $this->assertEquals('Company: Name cannot be blank.', $project->getFirstError('company'));
    }

    public function testSaveInvalidModelWithNoTransactionsSetShouldFail()
    {
        $project = new ProjectNoTransactions();
        $project->name = "Java";
        $company = new Company();
        $project->company = $company;
        $this->assertTrue($company->isNewRecord, 'Company should be a new record');
        $this->assertFalse($project->save(), 'Project could be saved');
        $this->assertArrayHasKey(
            'company',
            $project->getErrors(),
            'Validation errors do not contain a message for company'
        );
        $this->assertEquals('Company: Name cannot be blank.', $project->getFirstError('company'));
    }

    public function testSaveAddedExistingHasManyRelationShouldSucceed()
    {
        $project = Project::findOne(1);
        $user = User::findOne(3);
        $this->assertCount(2, $project->users, 'Project should have 2 users before save');
        $project->users = array_merge($project->users, [$user]); // Add new user to the existing list
        $this->assertCount(3, $project->users, 'Project should have 3 users after assignment');
        $this->assertTrue($project->save(), 'Project could not be saved');
        $this->assertCount(3, $project->users, 'Project should have 3 users after save');
    }

    public function testSaveAddedExistingHasManyRelationAsArrayShouldSucceed()
    {
        $project = Project::findOne(1);
        $user = ['id' => 3];
        $this->assertCount(2, $project->users, 'Project should have 2 users before save');
        $project->users = array_merge($project->users, [$user]); // Add new user to the existing list
        $this->assertCount(3, $project->users, 'Project should have 3 users after assignment');
        $this->assertTrue($project->save(), 'Project could not be saved' . VarDumper::dumpAsString($project->errors));
        $this->assertCount(3, $project->users, 'Project should have 3 users after save');
    }

    public function testSaveAddedExistingHasManyRelationAsIDShouldSucceed()
    {
        $project = Project::findOne(1);
        $user = 3;
        $this->assertCount(2, $project->users, 'Project should have 2 users before save');
        $project->users = array_merge($project->users, [$user]); // Add new user to the existing list
        $this->assertCount(3, $project->users, 'Project should have 3 users after assignment');
        $this->assertTrue($project->save(), 'Project could not be saved');
        $this->assertCount(3, $project->users, 'Project should have 3 users after save');
    }

    public function testSaveDeletedExistingHasManyRelationShouldSucceed()
    {
        $project = Project::findOne(1);
        $this->assertCount(2, $project->users, 'Project should have 2 users before save');
        $project->users = User::findAll([1]); // Change users by removing one
        $this->assertCount(1, $project->users, 'Project should have 1 user after assignment');
        $this->assertTrue($project->save(), 'Project could not be saved');
        $this->assertCount(1, $project->users, 'Project should have 1 user after save');
    }

    public function testSaveNewHasManyRelationAsModelShouldSucceed()
    {
        $project = Project::findOne(2);
        $this->assertCount(1, $project->users, 'Project should have 1 user before save');
        $user = new User();
        $user->username = "Steve Balmer";
        $project->users = array_merge($project->users, [$user]); // Add a fresh new user
        $this->assertCount(2, $project->users, 'Project should have 2 users after assignment');
        $this->assertTrue($project->save(), 'Project could not be saved');
        $this->assertCount(2, $project->users, 'Project should have 2 users after save');
    }

    public function testSaveNewHasManyRelationAsArrayShouldSucceed()
    {
        $project = Project::findOne(2);
        $this->assertCount(1, $project->users, 'Project should have 1 user before save');
        $user = ['username' => "Steve Balmer"];
        $project->users = array_merge($project->users, [$user]); // Add a fresh new user
        $this->assertCount(2, $project->users, 'Project should have 2 users after assignment');
        $this->assertTrue($project->save(), 'Project could not be saved');
        $this->assertCount(2, $project->users, 'Project should have 2 users after save');
        $this->assertEquals("Steve Balmer", $project->users[1]->username, 'Second user should be Steve Balmer');
        $this->assertNotEmpty($project->users[1]->id, 'Second user should have an ID');
    }

    public function testSaveNewHasManyRelationWithCompositeFksShouldSucceed()
    {
        $project = Project::findOne(1);
        $this->assertEquals(2, count($project->links), 'Project should have 2 links before save');
        $link = new Link();
        $link->language = 'fr';
        $link->name = 'windows10';
        $link->link = 'https://www.microsoft.com/fr-fr/windows/features';
        $project->links = array_merge($project->links, [$link]);
        $this->assertCount(3, $project->links, 'Project should have 3 links after assignment');
        $this->assertTrue($project->save(), 'Project could not be saved');
        $this->assertCount(3, $project->links, 'Project should have 3 links after save');
        $this->assertEquals(
            "https://www.microsoft.com/fr-fr/windows/features",
            $project->links[2]->link,
            'Second link should be https://www.microsoft.com/fr-fr/windows/features'
        );
    }

    public function testCreateHasManyRelationWithOneOfTheMissingKeyOfCompositeFk()
    {
        $project = Project::findOne(1);
        $project->links = [
            [
                'language' => 'fr',
            ]
        ];
        $this->assertCount(1, $project->links, 'Project should have 1 links after assignment');
        $this->assertTrue(
            $project->links[0]->isNewRecord,
            'Related link without one of the missed key of composite fk must be is new record'
        );
    }

    public function testSaveNewHasManyRelationWithCompositeFksAsArrayShouldSucceed()
    {
        $project = Project::findOne(1);
        $this->assertEquals(2, count($project->links), 'Project should have 2 links before save');
        $links = [
            ['language' => 'fr', 'name' => 'windows10', 'link' => 'https://www.microsoft.com/fr-fr/windows/features'],
            ['language' => 'en', 'name' => 'windows10', 'link' => 'https://www.microsoft.com/en-us/windows/features']
        ];
        $project->links = array_merge($project->links, $links);
        $this->assertCount(4, $project->links, 'Project should have 4 links after assignment');
        $this->assertTrue($project->save(), 'Project could not be saved');
        $this->assertCount(4, $project->links, 'Project should have 4 links after save');
        $this->assertEquals(
            "https://www.microsoft.com/fr-fr/windows/features",
            $project->links[2]->link,
            'Second link should be https://www.microsoft.com/fr-fr/windows/features'
        );
        $this->assertEquals(
            "https://www.microsoft.com/en-us/windows/features",
            $project->links[3]->link,
            'Third link should be https://www.microsoft.com/en-us/windows/features'
        );
    }

    public function testSaveUpdatedHasManyRelationWithCompositeFksAsArrayShouldSucceed()
    {
        $project = Project::findOne(1);
        $this->assertEquals(2, count($project->links), 'Project should have 2 links before save');
        $links = $project->links;
        $links[1]->link = "http://www.otherlink.com/";
        $project->links = $links;
        $this->assertTrue($project->save(), 'Project could not be saved');
        $this->assertCount(2, $project->links, 'Project should have 2 links before save');
        $this->assertEquals(
            "http://www.otherlink.com/",
            $project->links[1]->link,
            'Second link "Link" attribute should be "http://www.otherlink.com/"'
        );
    }

    public function testSaveNewManyRelationJunctionTableColumnsShouldSucceed()
    {
        $project = Project::findOne(1);
        $firstTag = new Tag();
        $firstTag->name = 'Tag One';
        $firstTag->setOrder(1);
        $secondTag = new Tag();
        $secondTag->name = 'Tag Two';
        $secondTag->setOrder(3);
        $project->tags = [
            $firstTag,
            $secondTag
        ];
        $this->assertTrue($project->save(), 'Project could not be saved');
        $this->assertCount(2, $project->tags, 'Project should have 2 tags after assignment');
        $firstTagJunctionTableColumns = (new \yii\db\Query())->from('project_tags')->where(['tag_id' => $firstTag->id])->one();
        $secondTagJunctionTableColumns = (new \yii\db\Query())->from('project_tags')->where(['tag_id' => $secondTag->id])->one();
        $this->assertEquals($firstTag->getOrder(), $firstTagJunctionTableColumns['order']);
        $this->assertEquals($secondTag->getOrder(), $secondTagJunctionTableColumns['order']);
    }

    public function testSaveMixedRelationsShouldSucceed()
    {
        $project = new Project();
        $project->name = "New project";
        $project->company = Company::findOne(2);
        $users = User::findAll([1, 3]);
        $this->assertCount(0, $project->users, 'Project should have 0 users before save');
        $project->users = $users; // Add users
        $this->assertEquals(2, count($project->users), 'Project should have 2 users after assignment');
        $this->assertTrue($project->save(), 'Project could not be saved');
        $this->assertCount(2, $project->users, 'Project should have 2 users after save');
        $this->assertEquals(2, $project->company_id, 'Company ID is not the one expected');
    }

    public function testSettingANullRelationShouldSucceed()
    {
        $link = new Link();
        $link->language = 'en';
        $link->name = 'yii';
        $link->link = 'http://www.yiiframework.com';
        $link->linkType = null;
        $this->assertTrue($link->save(), 'Link could not be saved');
        $this->assertNull($link->linkType, "Link type should be null");
        $this->assertNull($link->link_type_id, "Link type id should be null");
    }

    public function testUnsettingARelationShouldSucceed()
    {
        $link = Link::findOne(['language' => 'fr', 'name' => 'mac_os_x']);
        $this->assertEquals(1, $link->link_type_id, 'Link type id should be 1');
        $link->linkType = null;
        $this->assertTrue($link->save(), 'Link could not be saved');
        $this->assertNull($link->linkType, "Link type should be null");
        $this->assertNull($link->link_type_id, "Link type id should be null");
    }

    public function testLoadRelationsShouldSucceed()
    {
        $project = Project::findOne(1);
        $data = [
            'Company' => [
                'name' => 'YiiSoft'
            ],
            'Link'    => [
                [
                    'language' => 'en',
                    'name'     => 'yii',
                    'link'     => 'http://www.yiiframework.com'
                ],
                [
                    'language' => 'fr',
                    'name'     => 'yii',
                    'link'     => 'http://www.yiiframework.fr'
                ]
            ]
        ];
        $project->loadRelations($data);
        $this->assertTrue($project->save(), 'Project could not be saved');
        $this->assertEquals('YiiSoft', $project->company->name, "Company name should be YiiSoft");
        $this->assertCount(2, $project->projectLinks, "Project should have 2 links");
        $this->assertEquals($project->links[0]->link, 'http://www.yiiframework.com');
        $this->assertEquals($project->links[1]->link, 'http://www.yiiframework.fr');
    }

    public function testAssignSingleObjectToHasManyRelationShouldSucceed()
    {
        $project = new Project();
        $user = User::findOne(1);
        $project->users = $user;
        $this->assertEquals(1, count($project->users), 'Project should have 1 users after assignment');
    }

    public function testAssignSingleEmptyObjectToHasManyRelationShouldSucceed()
    {
        $project = new Project();
        $user = User::findOne(1);
        $project->users = null;
        $this->assertEquals(0, count($project->users), 'Project should have 0 users after assignment');
    }

    public function testChangeHasOneRelationWithAnotherObject()
    {
        $dummy_a = new DummyModel();
        $dummy_b = new DummyModel();
        $dummy_a->save();
        $dummy_b->save();
        $dummy_a->children = $dummy_b;
        $dummy_b->children = $dummy_a;
        $this->assertTrue($dummy_a->save(), 'Dummy A could not be saved');
        $this->assertTrue($dummy_b->save(), 'Dummy B could not be saved');
        $dummy_c = new DummyModel();
        $dummy_a->children = $dummy_c;
        $this->assertTrue($dummy_a->save(), 'Dummy A could not be saved');
    }

    /**
     * @expectedException yii\db\Exception
     */
    public function testSavingRelationWithSameUniqueKeyShouldFail()
    {
        $project = new Project();
        $project->name = "Yii Framework";
        $data = [
            'Company' => [
                'name' => 'NewSoft'
            ],
            'Link'    => [
                [
                    'language' => 'en',
                    'name'     => 'newsoft',
                    'link'     => 'http://www.newsoft.com'
                ],
                [
                    'language' => 'en',
                    'name'     => 'newsoft',
                    'link'     => 'http://www.newsoft.co.uk'
                ]
            ]
        ];
        $project->loadRelations($data);
        /***
         * This test throw an yii\base\Exception due to key conflict for related records.
         * That kind of issue is hard to address because no validation process could prevent that.
         * The exception is raised during the afterSave event of the owner model.
         * In that case, the behavior takes care to rollback any database modifications
         * and add an error to the related relational record.
         * Anyway, the exception should be catched to address the correct workflow.
         ***/
        try {
            $project->save();
        } catch (\Exception $e) {
            $this->assertArrayHasKey(
                'links',
                $project->getErrors(),
                'Links #1: The combination \"en\"-\"newsoft\" of Language and Name has already been taken.'
            );
            throw $e;
        }

    }

    public function testUpdatingAnExistingRelationShouldSucceed()
    {
        $project = new Project();
        $project->name = "Yii Framework";
        $data = [
            'Company' => [
                'name' => 'YiiSoft'
            ],
            'Link'    => [
                [
                    'language' => 'en',
                    'name'     => 'yii',
                    'link'     => 'http://www.yiiframework.ru'
                ]
            ]
        ];
        $project->loadRelations($data);
        $this->assertTrue($project->save(), 'Project could not be saved');
        $this->assertEquals('YiiSoft', $project->company->name, "Company name should be YiiSoft");
        $this->assertCount(1, $project->projectLinks, "Project should have 1 link");
        $this->assertEquals($project->links[0]->link, 'http://www.yiiframework.ru');
        $data = [
            'Link' => [
                [
                    'language' => 'en',
                    'name'     => 'yii',
                    'link'     => 'http://www.yiiframework.com'
                ],
                [
                    'language' => 'fr',
                    'name'     => 'yii',
                    'link'     => 'http://www.yiiframework.fr'
                ]
            ]
        ];
        $project->loadRelations($data);
        $this->assertTrue($project->save(), 'Project could not be saved');
        $this->assertEquals($project->links[0]->link, 'http://www.yiiframework.com');
        $this->assertEquals($project->links[1]->link, 'http://www.yiiframework.fr');
    }

    public function testPerScenarioAttributeValidationShouldSucceed()
    {
        $project = new Project();
        $project->name = "Yii Framework";
        $data = [
            'Company' => [
                'name' => 'YiiSoft'
            ],
            'Link'    => [
                [
                    'language' => 'en',
                    'name'     => 'yii',
                    'link'     => 'Invalid value',
                ]
            ]
        ];
        $project->loadRelations($data);
        $this->assertFalse($project->save(), 'Project could be saved');
        $data = [
            'Link' => [
                [
                    'language' => 'en',
                    'name'     => 'yii',
                    'link'     => 'http://www.yiiframework.com',
                ]
            ]
        ];
        $project->loadRelations($data);
        $this->assertTrue($project->save(), 'Project could not be saved');
    }

}
