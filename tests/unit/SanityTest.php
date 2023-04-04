<?php

namespace lsst\dam\tests;

use Craft;
use Codeception\Test\Unit;
use craft\elements\User;
use craft\elements\Entry;
use craft\errors\ElementNotFoundException;
use yii\base\Exception;

class SanityTest extends Unit
{
    /**
     * @var \UnitTester
     */
    protected \UnitTester $tester;

    protected function _before()
    {
    }

    protected function _after()
    {
    }

    public function testUserCreation() {
        $new_user = new User();
        $new_user->username = "some_body";
        $new_user->pending = true;
        $new_user->firstName = "Some";
        $new_user->lastName = "Body";
        $new_user->email = "some@body.email";
        $new_user->passwordResetRequired = false;
        $new_user->validate(null, false);
        try {
            $success = Craft::$app->getElements()->saveElement($new_user, false);
            $this->assertEquals(true, $success);
        } catch (ElementNotFoundException $e) {
            $this->debugSection('User not found error!', $e);
        } catch (Exception $e) {
            $this->debugSection('An exception occurred!', $e);
        } catch (\Throwable $e) {
            $this->debugSection('An throwable error occurred!', $e);
        }
    }

    public function testEntryCreation() {
        $callouts_section = Craft::$app->sections->getSectionByHandle('callouts');
        $entry_types = $callouts_section->getEntryTypes();

        $this->debugSection('Logging $entryTypes', $entry_types);

        $callout = $entry_types[0];
        $entry = new Entry();
        $entry->title = "Some test title";
        $entry->sectionId = $callouts_section->id;
        $entry->typeId = $callout->id;
        $entry->setFieldValue('header', "My fancy header");
        $entry->setFieldValue('text', "This and that");
        $entry->setFieldValue("backgroundColor", "base64:bmV1dHJhbDE1");

        try {
            $success = Craft::$app->getElements()->saveElement($entry);
            $this->assertEquals(true, $success);
        } catch (ElementNotFoundException $e) {
            $this->debugSection('User not found error!', $e);
        } catch (Exception $e) {
            $this->debugSection('An exception occurred!', $e);
        } catch (\Throwable $e) {
            $this->debugSection('An throwable error occurred!', $e);
        }

    }

}