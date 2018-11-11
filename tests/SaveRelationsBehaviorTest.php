<?php

namespace tests;

use lhs\Yii2SaveRelationsBehavior\SaveRelationsBehavior;
use tests\models\Company;
use tests\models\DummyModel;
use tests\models\DummyModelParent;
use tests\models\Link;
use tests\models\Project;
use tests\models\ProjectLink;
use tests\models\ProjectNoTransactions;
use tests\models\Tag;
use tests\models\User;
use tests\models\UserProfile;
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
        $db->createCommand()->dropTable('user_profile')->execute();
        $db->createCommand()->dropTable('company')->execute();
        $db->createCommand()->dropTable('link_type')->execute();
        $db->createCommand()->dropTable('link')->execute();
        $db->createCommand()->dropTable('project_tags')->execute();
        $db->createCommand()->dropTable('tags')->execute();
        $db->createCommand()->dropTable('project_link')->execute();
        $db->createCommand()->dropTable('project_contact')->execute();
        $db->createCommand()->dropTable('project_image')->execute();
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
            'id'         => $migration->primaryKey(),
            'company_id' => $migration->integer()->notNull(),
            'username'   => $migration->string()->notNull()->unique()
        ])->execute();

        // User profile
        $db->createCommand()->createTable('user_profile', [
            'user_id' => $migration->primaryKey(),
            'bio'     => $migration->text(),
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

        // Project Contact
        $db->createCommand()->createTable('project_contact', [
            'project_id' => $migration->integer()->notNull(),
            'email'      => $migration->string()->notNull(),
            'phone'      => $migration->string(),
            'PRIMARY KEY(project_id, email)'
        ])->execute();

        // Project Image
        $db->createCommand()->createTable('project_image', [
            'id'         => $migration->primaryKey(),
            'project_id' => $migration->integer()->notNull(),
            'path'       => $migration->string()->notNull()
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

        $db->createCommand()->batchInsert('user', ['id', 'username', 'company_id'], [
            [1, 'Steve Jobs', 1],
            [2, 'Bill Gates', 2],
            [3, 'Tim Cook', 1],
            [4, 'Jonathan Ive', 1]
        ])->execute();

        $db->createCommand()->batchInsert('user_profile', ['user_id', 'bio'], [
            [1, 'Steven Paul Jobs (February 24, 1955 – October 5, 2011) was an American entrepreneur, business magnate, inventor, and industrial designer. He was the chairman, chief executive officer (CEO), and co-founder of Apple Inc.; CEO and majority shareholder of Pixar; a member of The Walt Disney Company\'s board of directors following its acquisition of Pixar; and the founder, chairman, and CEO of NeXT.'],
            [2, 'William Henry Gates III (born October 28, 1955) is an American business magnate, investor, author, philanthropist, and co-founder of the Microsoft Corporation along with Paul Allen.'],
            [3, 'Timothy Donald Cook (born November 1, 1960) is an American business executive, industrial engineer, and developer. Cook is the Chief Executive Officer of Apple Inc., previously serving as the company\'s Chief Operating Officer, under its founder Steve Jobs.'],
            [4, 'Sir Jonathan Paul "Jony" Ive, KBE (born 27 February 1967), is an English industrial designer who is currently the chief design officer (CDO) of Apple and chancellor of the Royal College of Art in London.']
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

        $db->createCommand()->batchInsert('project_contact', ['email', 'phone', 'project_id'], [
            ['admin@apple.com', '(123) 456–7890', 1]
        ])->execute();

        $db->createCommand()->batchInsert('project_image', ['id', 'project_id', 'path'], [
            [1, 1, '/images/macosx.png'],
            [2, 1, '/images/macosx_icon.png'],
            [3, 2, '/images/windows.png']
        ])->execute();

        $db->createCommand()->batchInsert('project_user', ['project_id', 'user_id'], [
            [1, 1],
            [1, 4],
            [2, 2]
        ])->execute();
    }

    public function testCannotAttachBehaviorToAnythingButActiveRecord()
    {
        $this->setExpectedException('RuntimeException');
        $model = new Model();
        $model->attachBehavior('saveRelated', SaveRelationsBehavior::className());
    }

    public function testUnsupportedRelationProperty()
    {
        $this->setExpectedException('\yii\base\UnknownPropertyException');
        $model = new Project();
        $model->detachBehaviors();
        $model->attachBehavior('saveRelated', new SaveRelationsBehavior(['relations' => ['links' => ['fakeParam' => 'Some value']]]));
    }


    public function testTryToSetUndeclaredRelationShouldFail()
    {
        $this->setExpectedException('\yii\base\InvalidCallException');
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
        $user->company_id = 2;
        $project->users = array_merge($project->users, [$user]); // Add a fresh new user
        $this->assertCount(2, $project->users, 'Project should have 2 users after assignment');
        $this->assertTrue($project->save(), 'Project could not be saved');
        $this->assertCount(2, $project->users, 'Project should have 2 users after save');
    }

    public function testSaveNewHasManyRelationAsArrayShouldSucceed()
    {
        $project = Project::findOne(2);
        $this->assertCount(1, $project->users, 'Project should have 1 user before save');
        $user = ['username' => "Steve Balmer", 'company_id' => 2];
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
        $this->assertCount(2, $project->links, 'Project should have 2 links before save');
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
        $this->assertCount(2, $project->links, 'Project should have 2 links before save');
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
        $this->assertCount(2, $project->links, 'Project should have 2 links before save');
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
        $this->assertCount(2, $project->users, 'Project should have 2 users after assignment');
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

    public function testLoadHasManyWithCompositeKeyShouldSucceed()
    {
        $project = Project::findOne(1);
        $data = [
            'ProjectContact' => [
                [
                    'email' => 'admin@apple.com',
                    'phone' => '(999) 999–9999'
                ],
                [
                    'email' => 'new@apple.com',
                    'phone' => '(987) 654–3210'
                ]
            ]
        ];
        $project->loadRelations($data);
        $this->assertTrue($project->save(), 'Project could not be saved');
        $this->assertCount(2, $project->contacts, "Project should have 2 contacts");
        $this->assertEquals($project->contacts[0]->phone, '(999) 999–9999');

        $this->assertEquals($project->contacts[1]->email, 'new@apple.com');
        $this->assertEquals($project->contacts[1]->phone, '(987) 654–3210');
    }

    public function testLoadHasManyWithoutReferenceKeyShouldSucceed()
    {
        $project = Project::findOne(1);
        $data = [
            'ProjectImage' => [
                [
                    'path' => '/images/macosx_new.png'
                ],
                [
                    'id'   => 2,
                    'path' => '/images/macosx_updated.png'
                ]
            ]
        ];
        $project->loadRelations($data);
        $this->assertTrue($project->save(), 'Project could not be saved');
        $this->assertCount(2, $project->images, "Project should have 2 images");
        $this->assertEquals($project->images[0]->id, 2);
        $this->assertEquals($project->images[0]->path, '/images/macosx_updated.png');
        $this->assertEquals($project->images[1]->path, '/images/macosx_new.png');
    }

    public function testAssignSingleObjectToHasManyRelationShouldSucceed()
    {
        $project = new Project();
        $user = User::findOne(1);
        $project->users = $user;
        $this->assertCount(1, $project->users, 'Project should have 1 users after assignment');
    }

    public function testAssignSingleEmptyObjectToHasManyRelationShouldSucceed()
    {
        $project = new Project();
        $user = User::findOne(1);
        $project->users = null;
        $this->assertCount(0, $project->users, 'Project should have 0 users after assignment');
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

    public function testFailToSetScenarioForUnkownRelation()
    {
        $this->setExpectedException('\yii\base\InvalidArgumentException');
        $user = new User();
        $user->setRelationScenario('wrongNameRelation', 'insert');
    }


    public function testSaveHasOneWithPrimaryKeyAsForeignKey()
    {
        $user = new User();
        $user->username = 'Dummy More';
        $user->company = [
            'name' => 'ACME'
        ];
        $user->setRelationScenario('userProfile', 'insert');
        $user->userProfile = [
            'bio'   => "Some great bio",
            'agree' => 1
        ];
        $this->assertEquals(1, $user->userProfile->agree, 'User could not be saved' . VarDumper::dumpAsString($user->errors));
        $this->assertTrue($user->save(), 'User could not be saved' . VarDumper::dumpAsString($user->errors));
        $this->assertEquals("Some great bio", $user->userProfile->bio);
        $this->assertEquals($user->id, $user->userProfile->user_id);
    }

    public function testSaveHasOneReplaceRelatedWithNewRecord()
    {
        $profile = UserProfile::findOne(1);
        $this->assertEquals('Steven Paul Jobs (February 24, 1955 – October 5, 2011) was an American entrepreneur, business magnate, inventor, and industrial designer. He was the chairman, chief executive officer (CEO), and co-founder of Apple Inc.; CEO and majority shareholder of Pixar; a member of The Walt Disney Company\'s board of directors following its acquisition of Pixar; and the founder, chairman, and CEO of NeXT.', $profile->bio, "Profile bio is wrong");
        $data = [
            'User' => [
                'username'   => 'Someone Else',
                'company_id' => 1
            ]
        ];
        $profile->loadRelations($data);
        $this->assertEquals('Someone Else', $profile->user->username, "User name should be 'Someone Else'");
        $this->assertTrue($profile->user->isNewRecord, "User should be a new record");
        $this->assertEquals(1, $profile->user_id);
        $this->assertTrue($profile->save(), 'Profile could not be saved');
        $this->assertEquals('Someone Else', $profile->user->username, "User name should be 'Someone Else'");
    }

    public function testSaveNestedModels()
    {
        $project = new Project();
        $project->name = "Cartoon";
        $company = new Company();
        $company->name = 'ACME';
        $user = new User();
        $user->username = "Bugs Bunny";
        $company->users = $user;
        $project->company = $company;
        $this->assertTrue($project->save(), 'Project could not be saved');
        $project->refresh();
        $this->assertEquals($project->name, "Cartoon");
        $this->assertEquals($project->company->name, "ACME");
        $this->assertEquals($project->company->users[0]->username, "Bugs Bunny");
    }

    public function testUpdateHasOneNestedModels()
    {
        $project = Project::findOne(1);
        $project->name = "Other name";
        $company = $project->company;
        $company->name = "Tutu";
        $users = $company->users;
        $user = $users[0];
        $user->username = "Someone Else";
        $users[0] = $user;
        $company->users = $users;
        $project->company = $company;
        $this->assertTrue($project->save(), 'Project could not be saved');
        $project->refresh();
        $this->assertEquals($project->name, "Other name");
        $this->assertEquals($project->company->name, "Tutu");
        $this->assertEquals($project->company->users[0]->username, "Someone Else");
    }

    public function testUpdateHasManyNestedModels()
    {
        $project = Project::findOne(1);
        $project->name = "Other name";
        $users = $project->users;
        $user = $users[0];
        $company = $user->company;
        $company->name = "Tutu";
        $user->company = $company;
        $users[0] = $user;
        $user->username = "Someone Else";
        $project->users = $users;
        $this->assertTrue($project->save(), 'Project could not be saved');
        $project->refresh();
        $this->assertEquals($project->name, "Other name");
        $this->assertEquals($project->company->name, "Tutu");
        $this->assertEquals($project->company->users[0]->username, "Someone Else");
    }

    public function testHasOneRelationShouldTriggerOnBeforeValidateEvent()
    {
        $user = new User();
        $user->setAttributes([
            'username'   => 'Larry Page',
            'company_id' => 3
        ]);
        $user->userProfile = new UserProfile();
        $this->assertFalse($user->save(), 'User should not be saved');
        $this->assertCount(1, $user->userProfile->getErrors());
        $user->userProfile->bio = 'Lawrence Edward Page (born March 26, 1973) is an American computer scientist and Internet entrepreneur who co-founded Google with Sergey Brin.';
        $this->assertTrue($user->save(), 'User could not be saved');
    }

    public function testDeleteRelatedHasOneShouldSucceed()
    {
        User::findOne(1)->delete();
        $this->assertNull(UserProfile::findOne(1), 'Related user profile was not deleted');
        $this->assertNotNull(UserProfile::findOne(2), 'Unrelated user profile was deleted');
    }

    public function testDeleteRelatedHasManyShouldSucceed()
    {
        Project::findOne(1)->delete();
        $this->assertCount(0, ProjectLink::find()->where(['project_id' => 1])->all(), 'Related project links were not deleted');
    }

    public function testDeleteRelatedWithErrorShouldThrowAnException()
    {
        $this->setExpectedException('\yii\db\Exception');
        $project = Project::findOne(1);
        foreach ($project->projectLinks as $projectLink) {
            $projectLink->blockDelete = true;
        }
        $this->assertFalse($project->delete(), 'Project could be deleted');
    }

    public function testSaveProjectWithCompanyWithUserShouldSucceed()
    {
        // Test for cascading save relations
        $project = new Project();
        $project->name = "Cartoon";
        $company = new Company();
        $company->name = 'ACME';
        $user = new User();
        $user->username = "Bugs Bunny";
        $company->users = $user;
        $project->company = $company;
        $this->assertTrue($project->save(), 'Project could not be saved');
        $this->assertEquals('ACME', $project->company->name, 'Project company\'s name is wrong');
        $this->assertCount(1, $project->company->users, 'Count of related users is wrong');
        $this->assertEquals('Bugs Bunny', $project->company->users[0]->username, 'Company user\'s name is wrong');
        $this->assertFalse($project->company->isNewRecord, 'Company record should be saved');
        $this->assertFalse($project->company->users[0]->isNewRecord, 'Company Users records should be saved');
    }


    public function testLoadRelationNameAsDataKeyShouldSucceed()
    {
        $company = new Company([
            'name' => 'NewSoft',
        ]);

        $company->attachBehavior('saveRelations', [
            'class'           => SaveRelationsBehavior::className(),
            'relations'       => ['users'],
            'relationKeyName' => SaveRelationsBehavior::RELATION_KEY_RELATION_NAME
        ]);

        $data = [
            'users' => [
                ['username' => "user1"],
                ['username' => "user2"]
            ]
        ];

        $company->loadRelations($data);

        $this->assertTrue($company->save(), 'Company could not be saved');
        $this->assertEquals('NewSoft', $company->name, 'Company\'s name is wrong');
        $this->assertEquals('user1', $company->users[0]->username);
        $this->assertEquals('user2', $company->users[1]->username);
    }

    public function testGetOldRelations()
    {
        $project = Project::findOne(1);
        $project->company = Company::findOne(2);
        $links = [
            ['language' => 'fr', 'name' => 'windows10', 'link' => 'https://www.microsoft.com/fr-fr/windows/features', 'link_type_id' => 2],
            ['language' => 'en', 'name' => 'windows10', 'link' => 'https://www.microsoft.com/en-us/windows/features', 'link_type_id' => 2]
        ];
        $project->links = $links;
        $oldRelations = $project->getOldRelations();
        $this->assertArrayHasKey('company', $oldRelations);
        $this->assertArrayHasKey('links', $oldRelations);
        $this->assertCount(2, $oldRelations['links']);
        $oldLinks = $project->getOldRelation('links');
        $this->assertInternalType('array', $oldLinks);
        $this->assertEquals($oldLinks[0]->language, 'fr');
        $this->assertEquals($oldLinks[0]->name, 'mac_os_x');
        $this->assertEquals($oldLinks[0]->link, 'http://www.apple.com/fr/osx/');
        $this->assertEquals($oldLinks[0]->link_type_id, 1);
        $this->assertEquals($oldLinks[1]->language, 'en');
        $this->assertEquals($oldLinks[1]->name, 'mac_os_x');
        $this->assertEquals($oldLinks[1]->link, 'http://www.apple.com/osx/');
        $this->assertEquals($oldLinks[1]->link_type_id, 1);
        $oldCompany = $project->getOldRelation('company');
        $this->assertInstanceOf(Company::className(), $oldCompany);
        $this->assertEquals($oldCompany->id, 1);
        $this->assertEquals($oldCompany->name, 'Apple');
        $this->assertTrue($project->save());
        $oldLinks = $project->getOldRelation('links');
        $this->assertInternalType('array', $oldLinks);
        $this->assertEquals($oldLinks[0]->language, 'fr');
        $this->assertEquals($oldLinks[0]->name, 'windows10');
        $this->assertEquals($oldLinks[0]->link, 'https://www.microsoft.com/fr-fr/windows/features');
        $this->assertEquals($oldLinks[0]->link_type_id, 2);
        $this->assertEquals($oldLinks[1]->language, 'en');
        $this->assertEquals($oldLinks[1]->name, 'windows10');
        $this->assertEquals($oldLinks[1]->link, 'https://www.microsoft.com/en-us/windows/features');
        $this->assertEquals($oldLinks[1]->link_type_id, 2);
        $oldCompany = $project->getOldRelation('company');
        $this->assertInstanceOf(Company::className(), $oldCompany);
        $this->assertEquals($oldCompany->id, 2);
        $this->assertEquals($oldCompany->name, 'Microsoft');

    }

    public function testGetDirtyRelations()
    {
        $project = Project::findOne(1);
        $project->company = Company::findOne(2);
        $links = [
            ['language' => 'fr', 'name' => 'windows10', 'link' => 'https://www.microsoft.com/fr-fr/windows/features', 'link_type_id' => 2],
            ['language' => 'en', 'name' => 'windows10', 'link' => 'https://www.microsoft.com/en-us/windows/features', 'link_type_id' => 2]
        ];
        $project->links = $links;
        $dirtyRelations = $project->getDirtyRelations();
        $this->assertCount(2, $dirtyRelations);
        $this->assertArrayHasKey('company', $dirtyRelations);
        $this->assertArrayHasKey('links', $dirtyRelations);
        $this->assertArrayNotHasKey('tags', $dirtyRelations);
        $this->assertEquals($dirtyRelations['company'], $project->company);
        $this->assertEquals($dirtyRelations['links'], $project->links);
    }

    public function testMarkRelationDirty()
    {
        $project = Project::findOne(1);
        $this->assertArrayNotHasKey('company', $project->getDirtyRelations());
        $this->assertFalse($project->markRelationDirty('wrongRelationName'));
        $this->assertTrue($project->markRelationDirty('company'));
        $this->assertArrayHasKey('company', $project->getDirtyRelations());
    }

    public function testLoadNestedDataModels()
    {
        $project = Project::findOne(1);
        $data = [
            'Project' => [
                'name'    => 'Other name',
                'company' => [
                    'name'  => 'New Company',
                    'users' => [
                        [
                            'username' => 'New user'
                        ]
                    ]
                ],
                'users'   => [
                    [
                        'username'    => 'Another user',
                        'company'     => 1,
                        'userProfile' => [
                            'bio' => 'Another user great story'
                        ]
                    ]
                ]
            ]
        ];
        $project->load($data);
        $this->assertEquals($project->name, "Other name");
        $this->assertEquals($project->company->name, "New Company");
        $this->assertCount(1, $project->users);
        $this->assertEquals($project->users[0]->username, "Another user");
        $this->assertEquals($project->users[0]->company->name, "Apple");
        $this->assertEquals($project->company->users[0]->username, "New user");
        $this->assertTrue($project->save(), 'Project could not be saved ' . VarDumper::dumpAsString($project->getErrors()));
        $project->refresh();
        $this->assertEquals($project->name, "Other name");
        $this->assertEquals($project->company->name, "New Company");
        $this->assertEquals($project->company->users[0]->username, "New user");
        $this->assertCount(1, $project->users);
        $this->assertEquals($project->users[0]->username, "Another user");
    }
}
