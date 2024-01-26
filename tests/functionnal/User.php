<?php

/**
 * ---------------------------------------------------------------------
 * GLPI - Gestionnaire Libre de Parc Informatique
 * Copyright (C) 2015-2022 Teclib' and contributors.
 *
 * http://glpi-project.org
 *
 * based on GLPI - Gestionnaire Libre de Parc Informatique
 * Copyright (C) 2003-2014 by the INDEPNET Development Team.
 *
 * ---------------------------------------------------------------------
 *
 * LICENSE
 *
 * This file is part of GLPI.
 *
 * GLPI is free software; you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation; either version 2 of the License, or
 * (at your option) any later version.
 *
 * GLPI is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License
 * along with GLPI. If not, see <http://www.gnu.org/licenses/>.
 * ---------------------------------------------------------------------
 */

namespace tests\units;

/* Test for inc/user.class.php */

class User extends \DbTestCase
{
    public function testGenerateUserToken()
    {
        $user = getItemByTypeName('User', TU_USER);
        $this->variable($user->fields['personal_token_date'])->isNull();
        $this->variable($user->fields['personal_token'])->isNull();

        $token = $user->getAuthToken();
        $this->string($token)->isNotEmpty();

        $user->getFromDB($user->getID());
        $this->string($user->fields['personal_token'])->isIdenticalTo($token);
        $this->string($user->fields['personal_token_date'])->isIdenticalTo($_SESSION['glpi_currenttime']);
    }

    /**
     *
     */
    public function testLostPassword()
    {
        // would not be logical to login here
        $_SESSION['glpicronuserrunning'] = "cron_phpunit";
        $user = getItemByTypeName('User', TU_USER);

        // Test request for a password with invalid email
        $this->when(
            function () use ($user) {
                $user->forgetPassword('this-email-does-not-exists@example.com');
            }
        )->error()
            ->withType(E_USER_WARNING)
            ->withMessage("Failed to find a single user for 'this-email-does-not-exists@example.com', 0 user(s) found.")
            ->exists();

        // Test request for a password
        $result = $user->forgetPassword($user->getDefaultEmail());
        $this->boolean($result)->isTrue();

        // Test reset password with a bad token
        $token = $user->getField('password_forget_token');
        $input = [
            'password_forget_token' => $token . 'bad',
            'password'  => TU_PASS,
            'password2' => TU_PASS
        ];
        $this->exception(
            function () use ($user, $input) {
                $result = $user->updateForgottenPassword($input);
            }
        )
        ->isInstanceOf(\Glpi\Exception\ForgetPasswordException::class);

        // Test reset password with good token
        // 1 - Refresh the in-memory instance of user and get the current password
        $user->getFromDB($user->getID());

        // 2 - Set a new password
        $input = [
            'password_forget_token' => $token,
            'password'  => 'NewPassword',
            'password2' => 'NewPassword'
        ];

        // 3 - check the update succeeds
        $result = $user->updateForgottenPassword($input);
        $this->boolean($result)->isTrue();
        $newHash = $user->getField('password');

        // 4 - Restore the initial password in the DB before checking the updated password
        // This ensure the original password is restored even if the next test fails
        $updateSuccess = $user->update([
            'id'        => $user->getID(),
            'password'  => TU_PASS,
            'password2' => TU_PASS
        ]);
        $this->variable($updateSuccess)->isNotFalse('password update failed');

        // Test the new password was saved
        $this->variable(\Auth::checkPassword('NewPassword', $newHash))->isNotFalse();
    }

    public function testGetDefaultEmail()
    {
        $user = new \User();

        $this->string($user->getDefaultEmail())->isIdenticalTo('');
        $this->array($user->getAllEmails())->isIdenticalTo([]);
        $this->boolean($user->isEmail('one@test.com'))->isFalse();

        $uid = (int)$user->add([
            'name'   => 'test_email',
            '_useremails'  => [
                'one@test.com'
            ]
        ]);
        $this->integer($uid)->isGreaterThan(0);
        $this->boolean($user->getFromDB($user->fields['id']))->isTrue();
        $this->string($user->getDefaultEmail())->isIdenticalTo('one@test.com');

        $this->boolean(
            $user->update([
                'id'              => $uid,
                '_useremails'     => ['two@test.com'],
                '_default_email'  => 0
            ])
        )->isTrue();

        $this->boolean($user->getFromDB($user->fields['id']))->isTrue();
        $this->string($user->getDefaultEmail())->isIdenticalTo('two@test.com');

        $this->array($user->getAllEmails())->hasSize(2);
        $this->boolean($user->isEmail('one@test.com'))->isTrue();

        $tu_user = getItemByTypeName('User', TU_USER);
        $this->boolean($user->isEmail($tu_user->getDefaultEmail()))->isFalse();
    }

    public function testGetFromDBbyToken()
    {
        $user = $this->newTestedInstance;
        $uid = (int)$user->add([
            'name'   => 'test_token'
        ]);
        $this->integer($uid)->isGreaterThan(0);
        $this->boolean($user->getFromDB($uid))->isTrue();

        $token = $user->getToken($uid);
        $this->boolean($user->getFromDB($uid))->isTrue();
        $this->string($token)->hasLength(40);

        $user2 = new \User();
        $this->boolean($user2->getFromDBbyToken($token))->isTrue();
        $this->array($user2->fields)->isIdenticalTo($user->fields);

        $this->when(
            function () use ($uid) {
                $this->testedInstance->getFromDBbyToken($uid, 'my_field');
            }
        )->error
         ->withType(E_USER_WARNING)
         ->withMessage('User::getFromDBbyToken() can only be called with $field parameter with theses values: \'personal_token\', \'api_token\'')
         ->exists();
    }

    public function testPrepareInputForAdd()
    {
        $this->login();
        $user = $this->newTestedInstance();

        $input = [
            'name'   => 'prepare_for_add'
        ];
        $expected = [
            'name'         => 'prepare_for_add',
            'authtype'     => 1,
            'auths_id'     => 0,
            'is_active'    => 1,
            'is_deleted'   => 0,
            'entities_id'  => 0,
            'profiles_id'  => 0
        ];

        $this->array($user->prepareInputForAdd($input))->isIdenticalTo($expected);

        $input['_stop_import'] = 1;
        $this->boolean($user->prepareInputForAdd($input))->isFalse();

        $input = ['name' => 'invalid+login'];
        $this->boolean($user->prepareInputForAdd($input))->isFalse();
        $this->hasSessionMessages(ERROR, ['The login is not valid. Unable to add the user.']);

       //add same user twice
        $input = ['name' => 'new_user'];
        $this->integer($user->add($input))->isGreaterThan(0);
        $this->boolean($user->add($input))->isFalse(0);
        $this->hasSessionMessages(ERROR, ['Unable to add. The user already exists.']);

        $input = [
            'name'      => 'user_pass',
            'password'  => 'password',
            'password2' => 'nomatch'
        ];
        $this->boolean($user->prepareInputForAdd($input))->isFalse();
        $this->hasSessionMessages(ERROR, ['Error: the two passwords do not match']);

        $input = [
            'name'      => 'user_pass',
            'password'  => '',
            'password2' => 'nomatch'
        ];
        $expected = [
            'name'         => 'user_pass',
            'password2'    => 'nomatch',
            'authtype'     => 1,
            'auths_id'     => 0,
            'is_active'    => 1,
            'is_deleted'   => 0,
            'entities_id'  => 0,
            'profiles_id'  => 0
        ];
        $this->array($user->prepareInputForAdd($input))->isIdenticalTo($expected);

        $input['password'] = 'nomatch';
        $expected['password'] = 'unknonwn';
        unset($expected['password2']);
        $prepared = $user->prepareInputForAdd($input);
        $this->array($prepared)
         ->hasKeys(array_keys($expected))
         ->string['password']->hasLength(60)->startWith('$2y$');

        $input['password'] = 'mypass';
        $input['password2'] = 'mypass';
        $input['_extauth'] = 1;
        $expected = [
            'name'                 => 'user_pass',
            'password'             => '',
            '_extauth'             => 1,
            'authtype'             => 1,
            'auths_id'             => 0,
            'password_last_update' => $_SESSION['glpi_currenttime'],
            'is_active'            => 1,
            'is_deleted'           => 0,
            'entities_id'          => 0,
            'profiles_id'          => 0,
        ];
        $this->array($user->prepareInputForAdd($input))->isIdenticalTo($expected);
    }

    protected function prepareInputForTimezoneUpdateProvider()
    {
        return [
            [
                'input'     => [
                    'timezone' => 'Europe/Paris',
                ],
                'expected'  => [
                    'timezone' => 'Europe/Paris',
                ],
            ],
            [
                'input'     => [
                    'timezone' => '0',
                ],
                'expected'  => [
                    'timezone' => 'NULL',
                ],
            ],
         // check that timezone is not reset unexpectedly
            [
                'input'     => [
                    'registration_number' => 'no.1',
                ],
                'expected'  => [
                    'registration_number' => 'no.1',
                ],
            ],
        ];
    }

    /**
     * @dataProvider prepareInputForTimezoneUpdateProvider
     */
    public function testPrepareInputForUpdateTimezone(array $input, $expected)
    {
        $this->login();
        $user = $this->newTestedInstance();
        $username = 'prepare_for_update_' . mt_rand();
        $user_id = $user->add(
            [
                'name'         => $username,
                'password'     => 'mypass',
                'password2'    => 'mypass',
                '_profiles_id' => 1
            ]
        );
        $this->integer((int)$user_id)->isGreaterThan(0);

        $this->login($username, 'mypass');

        $input = ['id' => $user_id] + $input;
        $result = $user->prepareInputForUpdate($input);

        $expected = ['id' => $user_id] + $expected;
        $this->array($result)->isIdenticalTo($expected);
    }

    protected function prepareInputForUpdatePasswordProvider()
    {
        return [
            [
                'input'     => [
                    'password'  => 'initial_pass',
                    'password2' => 'initial_pass'
                ],
                'expected'  => [
                ],
            ],
            [
                'input'     => [
                    'password'  => 'new_pass',
                    'password2' => 'new_pass_not_match'
                ],
                'expected'  => false,
                'messages'  => [ERROR => ['Error: the two passwords do not match']],
            ],
            [
                'input'     => [
                    'password'  => 'new_pass',
                    'password2' => 'new_pass'
                ],
                'expected'  => [
                    'password_last_update' => true,
                    'password' => true,
                ],
            ],
        ];
    }

    /**
     * @dataProvider prepareInputForUpdatePasswordProvider
     */
    public function testPrepareInputForUpdatePassword(array $input, $expected, array $messages = null)
    {
        $this->login();
        $user = $this->newTestedInstance();
        $username = 'prepare_for_update_' . mt_rand();
        $user_id = $user->add(
            [
                'name'         => $username,
                'password'     => 'initial_pass',
                'password2'    => 'initial_pass',
                '_profiles_id' => 1
            ]
        );
        $this->integer((int)$user_id)->isGreaterThan(0);

        $this->login($username, 'initial_pass');

        $input = ['id' => $user_id] + $input;
        $result = $user->prepareInputForUpdate($input);

        if (null !== $messages) {
            $this->array($_SESSION['MESSAGE_AFTER_REDIRECT'])->isIdenticalTo($messages);
            $_SESSION['MESSAGE_AFTER_REDIRECT'] = []; //reset
        }

        if (false === $expected) {
            $this->boolean($result)->isIdenticalTo($expected);
            return;
        }

        if (array_key_exists('password', $expected) && true === $expected['password']) {
           // password_hash result is unpredictible, so we cannot test its exact value
            $this->array($result)->hasKey('password');
            $this->string($result['password'])->isNotEmpty();

            unset($expected['password']);
            unset($result['password']);
        }

        $expected = ['id' => $user_id] + $expected;
        if (array_key_exists('password_last_update', $expected) && true === $expected['password_last_update']) {
           // $_SESSION['glpi_currenttime'] was reset on login, value cannot be provided by test provider
            $expected['password_last_update'] = $_SESSION['glpi_currenttime'];
        }

        $this->array($result)->isIdenticalTo($expected);
    }

    public function testPost_addItem()
    {
        $this->login();
        $this->setEntity('_test_root_entity', true);
        $eid = getItemByTypeName('Entity', '_test_root_entity', true);

        $user = $this->newTestedInstance;

       //user with a profile
        $pid = getItemByTypeName('Profile', 'Technician', true);
        $uid = (int)$user->add([
            'name'         => 'create_user',
            '_profiles_id' => $pid
        ]);
        $this->integer($uid)->isGreaterThan(0);

        $this->boolean($user->getFromDB($uid))->isTrue();
        $this->array($user->fields)
         ->string['name']->isIdenticalTo('create_user')
         ->integer['profiles_id']->isEqualTo(0);

        $puser = new \Profile_User();
        $this->boolean($puser->getFromDBByCrit(['users_id' => $uid]))->isTrue();
        $this->array($puser->fields)
         ->integer['profiles_id']->isEqualTo($pid)
         ->integer['entities_id']->isEqualTo($eid)
         ->integer['is_recursive']->isEqualTo(0)
         ->integer['is_dynamic']->isEqualTo(0);

        $pid = (int)\Profile::getDefault();
        $this->integer($pid)->isGreaterThan(0);

       //user without a profile (will take default one)
        $uid2 = (int)$user->add([
            'name' => 'create_user2',
        ]);
        $this->integer($uid2)->isGreaterThan(0);

        $this->boolean($user->getFromDB($uid2))->isTrue();
        $this->array($user->fields)
         ->string['name']->isIdenticalTo('create_user2')
         ->integer['profiles_id']->isEqualTo(0);

        $puser = new \Profile_User();
        $this->boolean($puser->getFromDBByCrit(['users_id' => $uid2]))->isTrue();
        $this->array($puser->fields)
         ->integer['profiles_id']->isEqualTo($pid)
         ->integer['entities_id']->isEqualTo($eid)
         ->integer['is_recursive']->isEqualTo(0)
         ->integer['is_dynamic']->isEqualTo(1);

       //user with entity not recursive
        $eid2 = (int)getItemByTypeName('Entity', '_test_child_1', true);
        $this->integer($eid2)->isGreaterThan(0);
        $uid3 = (int)$user->add([
            'name'         => 'create_user3',
            '_entities_id' => $eid2
        ]);
        $this->integer($uid3)->isGreaterThan(0);

        $this->boolean($user->getFromDB($uid3))->isTrue();
        $this->array($user->fields)
         ->string['name']->isIdenticalTo('create_user3');

        $puser = new \Profile_User();
        $this->boolean($puser->getFromDBByCrit(['users_id' => $uid3]))->isTrue();
        $this->array($puser->fields)
         ->integer['profiles_id']->isEqualTo($pid)
         ->integer['entities_id']->isEqualTo($eid2)
         ->integer['is_recursive']->isEqualTo(0)
         ->integer['is_dynamic']->isEqualTo(1);

       //user with entity recursive
        $uid4 = (int)$user->add([
            'name'            => 'create_user4',
            '_entities_id'    => $eid2,
            '_is_recursive'   => 1
        ]);
        $this->integer($uid4)->isGreaterThan(0);

        $this->boolean($user->getFromDB($uid4))->isTrue();
        $this->array($user->fields)
         ->string['name']->isIdenticalTo('create_user4');

        $puser = new \Profile_User();
        $this->boolean($puser->getFromDBByCrit(['users_id' => $uid4]))->isTrue();
        $this->array($puser->fields)
         ->integer['profiles_id']->isEqualTo($pid)
         ->integer['entities_id']->isEqualTo($eid2)
         ->integer['is_recursive']->isEqualTo(1)
         ->integer['is_dynamic']->isEqualTo(1);
    }

    public function testClone()
    {
        $this->login();

        $user = getItemByTypeName('User', TU_USER);

        $this->setEntity('_test_root_entity', true);

        $date = date('Y-m-d H:i:s');
        $_SESSION['glpi_currenttime'] = $date;

       // Test item cloning
        $added = $user->clone();
        $this->integer((int)$added)->isGreaterThan(0);

        $clonedUser = new \User();
        $this->boolean($clonedUser->getFromDB($added))->isTrue();

        $fields = $user->fields;

       // Check the values. Id and dates must be different, everything else must be equal
        foreach ($fields as $k => $v) {
            switch ($k) {
                case 'id':
                case 'name':
                    $this->variable($clonedUser->getField($k))->isNotEqualTo($user->getField($k));
                    break;
                case 'date_mod':
                case 'date_creation':
                    $dateClone = new \DateTime($clonedUser->getField($k));
                    $expectedDate = new \DateTime($date);
                    $this->dateTime($dateClone)->isEqualTo($expectedDate);
                    break;
                default:
                    $this->variable($clonedUser->getField($k))->isEqualTo($user->getField($k));
            }
        }
    }

    public function testGetFromDBbyDn()
    {
        $user = $this->newTestedInstance;
        $dn = 'user=user_with_dn,dc=test,dc=glpi-project,dc=org';

        $uid = (int)$user->add([
            'name'      => 'user_with_dn',
            'user_dn'   => $dn
        ]);
        $this->integer($uid)->isGreaterThan(0);

        $this->boolean($user->getFromDBbyDn($dn))->isTrue();
        $this->array($user->fields)
         ->integer['id']->isIdenticalTo($uid)
         ->string['name']->isIdenticalTo('user_with_dn');
    }

    public function testGetFromDBbySyncField()
    {
        $user = $this->newTestedInstance;
        $sync_field = 'abc-def-ghi';

        $uid = (int)$user->add([
            'name'         => 'user_with_syncfield',
            'sync_field'   => $sync_field
        ]);

        $this->integer($uid)->isGreaterThan(0);

        $this->boolean($user->getFromDBbySyncField($sync_field))->isTrue();
        $this->array($user->fields)
         ->integer['id']->isIdenticalTo($uid)
         ->string['name']->isIdenticalTo('user_with_syncfield');
    }

    public function testGetFromDBbyName()
    {
        $user = $this->newTestedInstance;
        $name = 'user_with_name';

        $uid = (int)$user->add([
            'name' => $name
        ]);

        $this->integer($uid)->isGreaterThan(0);

        $this->boolean($user->getFromDBbyName($name))->isTrue();
        $this->array($user->fields)
         ->integer['id']->isIdenticalTo($uid);
    }

    public function testGetFromDBbyNameAndAuth()
    {
        $user = $this->newTestedInstance;
        $name = 'user_with_auth';

        $uid = (int)$user->add([
            'name'      => $name,
            'authtype'  => \Auth::DB_GLPI,
            'auths_id'  => 12
        ]);

        $this->integer($uid)->isGreaterThan(0);

        $this->boolean($user->getFromDBbyNameAndAuth($name, \Auth::DB_GLPI, 12))->isTrue();
        $this->array($user->fields)
         ->integer['id']->isIdenticalTo($uid)
         ->string['name']->isIdenticalTo($name);
    }

    protected function rawNameProvider()
    {
        return [
            [
                'input'     => ['name' => 'myname'],
                'rawname'   => 'myname'
            ], [
                'input'     => [
                    'name'      => 'anothername',
                    'realname'  => 'real name'
                ],
                'rawname'      => 'real name'
            ], [
                'input'     => [
                    'name'      => 'yet another name',
                    'firstname' => 'first name'
                ],
                'rawname'   => 'yet another name'
            ], [
                'input'     => [
                    'name'      => 'yet another one',
                    'realname'  => 'real name',
                    'firstname' => 'first name'
                ],
                'rawname'   => 'real name first name'
            ]
        ];
    }

    /**
     * @dataProvider rawNameProvider
     */
    public function testGetFriendlyName($input, $rawname)
    {
        $user = $this->newTestedInstance;

        $this->string($user->getFriendlyName())->isIdenticalTo('');

        $this
         ->given($this->newTestedInstance)
            ->then
               ->integer($uid = (int)$this->testedInstance->add($input))
                  ->isGreaterThan(0)
               ->boolean($this->testedInstance->getFromDB($uid))->isTrue()
               ->string($this->testedInstance->getFriendlyName())->isIdenticalTo($rawname);
    }

    public function testBlankPassword()
    {
        $input = [
            'name'      => 'myname',
            'password'  => 'mypass',
            'password2' => 'mypass'
        ];
        $this
         ->given($this->newTestedInstance)
            ->then
               ->integer($uid = (int)$this->testedInstance->add($input))
                  ->isGreaterThan(0)
               ->boolean($this->testedInstance->getFromDB($uid))->isTrue()
               ->array($this->testedInstance->fields)
                  ->string['name']->isIdenticalTo('myname')
                  ->string['password']->hasLength(60)->startWith('$2y$')
         ->given($this->testedInstance->blankPassword())
            ->then
               ->boolean($this->testedInstance->getFromDB($uid))->isTrue()
               ->array($this->testedInstance->fields)
                  ->string['name']->isIdenticalTo('myname')
                  ->string['password']->isIdenticalTo('');
    }

    public function testPre_updateInDB()
    {
        $this->login();
        $user = $this->newTestedInstance();

        $uid = (int)$user->add([
            'name' => 'preupdate_user'
        ]);
        $this->integer($uid)->isGreaterThan(0);
        $this->boolean($user->getFromDB($uid))->isTrue();

        $this->boolean($user->update([
            'id'     => $uid,
            'name'   => 'preupdate_user_edited'
        ]))->isTrue();
        $this->hasNoSessionMessages([ERROR, WARNING]);

       //can update with same name when id is identical
        $this->boolean($user->update([
            'id'     => $uid,
            'name'   => 'preupdate_user_edited'
        ]))->isTrue();
        $this->hasNoSessionMessages([ERROR, WARNING]);

        $this->integer(
            (int)$user->add(['name' => 'do_exist'])
        )->isGreaterThan(0);
        $this->boolean($user->update([
            'id'     => $uid,
            'name'   => 'do_exist'
        ]))->isTrue();
        $this->hasSessionMessages(ERROR, ['Unable to update login. A user already exists.']);

        $this->boolean($user->getFromDB($uid))->isTrue();
        $this->string($user->fields['name'])->isIdenticalTo('preupdate_user_edited');

        $this->boolean($user->update([
            'id'     => $uid,
            'name'   => 'in+valid'
        ]))->isTrue();
        $this->hasSessionMessages(ERROR, ['The login is not valid. Unable to update login.']);
    }

    public function testGetIdByName()
    {
        $user = $this->newTestedInstance;

        $uid = (int)$user->add(['name' => 'id_by_name']);
        $this->integer($uid)->isGreaterThan(0);

        $this->integer($user->getIdByName('id_by_name'))->isIdenticalTo($uid);
    }

    public function testGetIdByField()
    {
        $user = $this->newTestedInstance;

        $uid = (int)$user->add([
            'name'   => 'id_by_field',
            'phone'  => '+33123456789'
        ]);
        $this->integer($uid)->isGreaterThan(0);

        $this->integer($user->getIdByField('phone', '+33123456789'))->isIdenticalTo($uid);

        $this->integer(
            $user->add([
                'name'   => 'id_by_field2',
                'phone'  => '+33123456789'
            ])
        )->isGreaterThan(0);
        $this->boolean($user->getIdByField('phone', '+33123456789'))->isFalse();

        $this->boolean($user->getIdByField('phone', 'donotexists'))->isFalse();
    }

    public function testgetAdditionalMenuOptions()
    {
        $this->Login();
        $this
         ->given($this->newTestedInstance)
            ->then
               ->array($this->testedInstance->getAdditionalMenuOptions())
                  ->hasSize(1)
                  ->hasKey('ldap');

        $this->Login('normal', 'normal');
        $this
         ->given($this->newTestedInstance)
            ->then
               ->boolean($this->testedInstance->getAdditionalMenuOptions())
                  ->isFalse();
    }

    protected function passwordExpirationMethodsProvider()
    {
        $time = time();

        return [
            [
                'last_update'                     => date('Y-m-d H:i:s', strtotime('-10 years', $time)),
                'expiration_delay'                => -1,
                'expiration_notice'               => -1,
                'expected_expiration_time'        => null,
                'expected_should_change_password' => false,
                'expected_has_password_expire'    => false,
            ],
            [
                'last_update'                     => date('Y-m-d H:i:s', strtotime('-10 days', $time)),
                'expiration_delay'                => 15,
                'expiration_notice'               => -1,
                'expected_expiration_time'        => strtotime('+5 days', $time),
                'expected_should_change_password' => false, // not yet in notice time
                'expected_has_password_expire'    => false,
            ],
            [
                'last_update'                     => date('Y-m-d H:i:s', strtotime('-10 days', $time)),
                'expiration_delay'                => 15,
                'expiration_notice'               => 10,
                'expected_expiration_time'        => strtotime('+5 days', $time),
                'expected_should_change_password' => true,
                'expected_has_password_expire'    => false,
            ],
            [
                'last_update'                     => date('Y-m-d H:i:s', strtotime('-20 days', $time)),
                'expiration_delay'                => 15,
                'expiration_notice'               => -1,
                'expected_expiration_time'        => strtotime('-5 days', $time),
                'expected_should_change_password' => true,
                'expected_has_password_expire'    => true,
            ],
        ];
    }

    /**
     * @dataProvider passwordExpirationMethodsProvider
     */
    public function testPasswordExpirationMethods(
        string $last_update,
        int $expiration_delay,
        int $expiration_notice,
        $expected_expiration_time,
        $expected_should_change_password,
        $expected_has_password_expire
    ) {
        global $CFG_GLPI;

        $user = $this->newTestedInstance();
        $username = 'prepare_for_update_' . mt_rand();
        $user_id = $user->add(
            [
                'name'      => $username,
                'password'  => 'pass',
                'password2' => 'pass'
            ]
        );
        $this->integer($user_id)->isGreaterThan(0);
        $this->boolean($user->update(['id' => $user_id, 'password_last_update' => $last_update]))->isTrue();
        $this->boolean($user->getFromDB($user->fields['id']))->isTrue();

        $cfg_backup = $CFG_GLPI;
        $CFG_GLPI['password_expiration_delay'] = $expiration_delay;
        $CFG_GLPI['password_expiration_notice'] = $expiration_notice;

        $expiration_time = $user->getPasswordExpirationTime();
        $should_change_password = $user->shouldChangePassword();
        $has_password_expire = $user->hasPasswordExpired();

        $CFG_GLPI = $cfg_backup;

        $this->variable($expiration_time)->isEqualTo($expected_expiration_time);
        $this->boolean($should_change_password)->isEqualTo($expected_should_change_password);
        $this->boolean($has_password_expire)->isEqualTo($expected_has_password_expire);
    }


    protected function cronPasswordExpirationNotificationsProvider()
    {
       // create 10 users with differents password_last_update dates
       // first has its password set 1 day ago
       // second has its password set 11 day ago
       // and so on
       // tenth has its password set 91 day ago
        $user = new \User();
        for ($i = 1; $i < 100; $i += 10) {
            $user_id = $user->add(
                [
                    'name'     => 'cron_user_' . mt_rand(),
                    'authtype' => \Auth::DB_GLPI,
                ]
            );
            $this->integer($user_id)->isGreaterThan(0);
            $this->boolean(
                $user->update(
                    [
                        'id' => $user_id,
                        'password_last_update' => date('Y-m-d H:i:s', strtotime('-' . $i . ' days')),
                    ]
                )
            )->isTrue();
        }

        return [
         // validate that cron does nothing if password expiration is not active (default config)
            [
                'expiration_delay'               => -1,
                'notice_delay'                   => -1,
                'lock_delay'                     => -1,
                'cron_limit'                     => 100,
                'expected_result'                => 0, // 0 = nothing to do
                'expected_notifications_count'   => 0,
                'expected_lock_count'            => 0,
            ],
         // validate that cron send no notification if password_expiration_notice == -1
            [
                'expiration_delay'               => 15,
                'notice_delay'                   => -1,
                'lock_delay'                     => -1,
                'cron_limit'                     => 100,
                'expected_result'                => 0, // 0 = nothing to do
                'expected_notifications_count'   => 0,
                'expected_lock_count'            => 0,
            ],
         // validate that cron send notifications instantly if password_expiration_notice == 0
            [
                'expiration_delay'               => 50,
                'notice_delay'                   => 0,
                'lock_delay'                     => -1,
                'cron_limit'                     => 100,
                'expected_result'                => 1, // 1 = fully processed
                'expected_notifications_count'   => 5, // 5 users should be notified (them which has password set more than 50 days ago)
                'expected_lock_count'            => 0,
            ],
         // validate that cron send notifications before expiration if password_expiration_notice > 0
            [
                'expiration_delay'               => 50,
                'notice_delay'                   => 20,
                'lock_delay'                     => -1,
                'cron_limit'                     => 100,
                'expected_result'                => 1, // 1 = fully processed
                'expected_notifications_count'   => 7, // 7 users should be notified (them which has password set more than 50-20 days ago)
                'expected_lock_count'            => 0,
            ],
         // validate that cron returns partial result if there is too many notifications to send
            [
                'expiration_delay'               => 50,
                'notice_delay'                   => 20,
                'lock_delay'                     => -1,
                'cron_limit'                     => 5,
                'expected_result'                => -1, // -1 = partially processed
                'expected_notifications_count'   => 5, // 5 on 7 users should be notified (them which has password set more than 50-20 days ago)
                'expected_lock_count'            => 0,
            ],
         // validate that cron disable users instantly if password_expiration_lock_delay == 0
            [
                'expiration_delay'               => 50,
                'notice_delay'                   => -1,
                'lock_delay'                     => 0,
                'cron_limit'                     => 100,
                'expected_result'                => 1, // 1 = fully processed
                'expected_notifications_count'   => 0,
                'expected_lock_count'            => 5, // 5 users should be locked (them which has password set more than 50 days ago)
            ],
         // validate that cron disable users with given delay if password_expiration_lock_delay > 0
            [
                'expiration_delay'               => 20,
                'notice_delay'                   => -1,
                'lock_delay'                     => 10,
                'cron_limit'                     => 100,
                'expected_result'                => 1, // 1 = fully processed
                'expected_notifications_count'   => 0,
                'expected_lock_count'            => 7, // 7 users should be locked (them which has password set more than 20+10 days ago)
            ],
        ];
    }

    /**
     * @dataProvider cronPasswordExpirationNotificationsProvider
     */
    public function testCronPasswordExpirationNotifications(
        int $expiration_delay,
        int $notice_delay,
        int $lock_delay,
        int $cron_limit,
        int $expected_result,
        int $expected_notifications_count,
        int $expected_lock_count
    ) {
        global $CFG_GLPI, $DB;

        $this->login();

        $crontask = new \CronTask();
        $this->boolean($crontask->getFromDBbyName(\User::getType(), 'passwordexpiration'))->isTrue();
        $crontask->fields['param'] = $cron_limit;

        $cfg_backup = $CFG_GLPI;
        $CFG_GLPI['password_expiration_delay'] = $expiration_delay;
        $CFG_GLPI['password_expiration_notice'] = $notice_delay;
        $CFG_GLPI['password_expiration_lock_delay'] = $lock_delay;
        $CFG_GLPI['use_notifications']  = true;
        $CFG_GLPI['notifications_ajax'] = 1;
        $result = \User::cronPasswordExpiration($crontask);
        $CFG_GLPI = $cfg_backup;

        $this->integer($result)->isEqualTo($expected_result);
        $this->integer(
            countElementsInTable(\Alert::getTable(), ['itemtype' => \User::getType()])
        )->isEqualTo($expected_notifications_count);
        $DB->delete(\Alert::getTable(), ['itemtype' => \User::getType()]); // reset alerts

        $user_crit = [
            'authtype'  => \Auth::DB_GLPI,
            'is_active' => 0,
        ];
        $this->integer(countElementsInTable(\User::getTable(), $user_crit))->isEqualTo($expected_lock_count);
        $DB->update(\User::getTable(), ['is_active' => 1], $user_crit); // reset users
    }
}
