<?php

/**
 * Copyright (c) 2013 Robin Appelman <icewind@owncloud.com>
 * This file is licensed under the Affero General Public License version 3 or
 * later.
 * See the COPYING-README file.
 */

namespace Test\User;

use OC\Hooks\PublicEmitter;
use OC\User\Account;
use OC\User\AccountMapper;
use OC\User\Backend;
use OC\User\Database;
use OC\User\User;
use OCP\IConfig;
use OCP\IURLGenerator;
use OCP\User\IChangePasswordBackend;
use Symfony\Component\EventDispatcher\EventDispatcher;
use Test\TestCase;

/**
 * Class UserTest
 *
 * @group DB
 *
 * @package Test\User
 */
class UserTest extends TestCase {

	/** @var AccountMapper | \PHPUnit_Framework_MockObject_MockObject */
	private $accountMapper;
	/** @var Account */
	private $account;
	/** @var User */
	private $user;
	/** @var IConfig | \PHPUnit_Framework_MockObject_MockObject */
	private $config;
	/** @var PublicEmitter */
	private $emitter;
	/** @var EventDispatcher | \PHPUnit_Framework_MockObject_MockObject */
	private $eventDispatcher;
	/** @var IURLGenerator | \PHPUnit_Framework_MockObject_MockObject */
	private $urlGenerator;

	public function setUp() {
		parent::setUp();
		$this->accountMapper = $this->createMock(AccountMapper::class);
		$this->config = $this->createMock(IConfig::class);
		$this->account = new Account();
		$this->account->setUserId('foo');
		$this->emitter = new PublicEmitter();
		$this->eventDispatcher = $this->createMock(EventDispatcher::class);
		$this->urlGenerator = $this->getMockBuilder('\OC\URLGenerator')
			->setMethods(['getAbsoluteURL'])
			->disableOriginalConstructor()
			->getMock();

		$this->user = new User($this->account, $this->accountMapper, $this->emitter, $this->config, $this->urlGenerator, $this->eventDispatcher);
	}

	public function testDisplayName() {
		$this->account->setDisplayName('Foo');
		$this->assertEquals('Foo', $this->user->getDisplayName());
	}

	/**
	 * if the display name contain whitespaces only, we expect the uid as result
	 */
	public function testDisplayNameEmpty() {
		$this->assertEquals('foo', $this->user->getDisplayName());
	}

	public function testSetPassword() {
		$this->config->expects($this->once())
			->method('deleteUserValue')
			->with('foo', 'owncloud', 'lostpassword');

		$backend = $this->createMock(IChangePasswordBackend::class);
		/** @var Account | \PHPUnit_Framework_MockObject_MockObject $account */
		$account = $this->createMock(Account::class);
		$account->expects($this->any())->method('getBackendInstance')->willReturn($backend);
		$account->expects($this->any())->method('__call')->with('getUserId')->willReturn('foo');
		$backend->expects($this->once())->method('setPassword')->with('foo', 'bar')->willReturn(true);

		$this->user = new User($account, $this->accountMapper, null, $this->config);
		$this->assertTrue($this->user->setPassword('bar',''));
		$this->assertTrue($this->user->canChangePassword());

	}
	public function testSetPasswordNotSupported() {
		$this->config->expects($this->never())
			->method('deleteUserValue')
			->with('foo', 'owncloud', 'lostpassword');

		$backend = $this->createMock(IChangePasswordBackend::class);
		/** @var Account | \PHPUnit_Framework_MockObject_MockObject $account */
		$account = $this->createMock(Account::class);
		$account->expects($this->any())->method('getBackendInstance')->willReturn($backend);
		$account->expects($this->any())->method('__call')->with('getUserId')->willReturn('foo');
		$backend->expects($this->once())->method('setPassword')->with('foo', 'bar')->willReturn(false);

		$this->user = new User($account, $this->accountMapper, null, $this->config);
		$this->assertFalse($this->user->setPassword('bar',''));
		$this->assertTrue($this->user->canChangePassword());
	}

	public function testSetPasswordNoBackend() {
		$this->assertFalse($this->user->setPassword('bar',''));
		$this->assertFalse($this->user->canChangePassword());
	}

	public function providesChangeAvatarSupported() {
		return [
			[true, true, true],
			[false, true, false],
			[true, false, null]
		];
	}

	/**
	 * @dataProvider providesChangeAvatarSupported
	 */
	public function testChangeAvatarSupported($expected, $implements, $canChange) {
		$backend = $this->getMockBuilder(Database::class)
			->setMethods(['canChangeAvatar', 'implementsActions'])
			->getMock();
		$backend->expects($this->any())->method('canChangeAvatar')->willReturn($canChange);

		/** @var Account | \PHPUnit_Framework_MockObject_MockObject $account */
		$account = $this->createMock(Account::class);
		$account->expects($this->any())->method('getBackendInstance')->willReturn($backend);
		$account->expects($this->any())->method('__call')->with('getUserId')->willReturn('foo');

		$backend->expects($this->any())
			->method('implementsActions')
			->will($this->returnCallback(function ($actions) use ($implements) {
				if ($actions === Backend::PROVIDE_AVATAR) {
					return $implements;
				} else {
					return false;
				}
			}));

		$user = new User($account, $this->accountMapper, null, $this->config);
		$this->assertEquals($expected, $user->canChangeAvatar());
	}

	public function testDelete() {
		$this->accountMapper->expects($this->once())->method('delete')->willReturn($this->account);
		$this->assertTrue($this->user->delete());
	}

	public function testGetHome() {
		$this->account->setHome('/home/foo');
		$this->assertEquals('/home/foo', $this->user->getHome());
	}

	public function testGetBackendClassName() {
		\OC::$server->getUserManager()->registerBackend(new Database());
		$this->account->setBackend(Database::class);
		$this->assertEquals('Database', $this->user->getBackendClassName());
	}

	public function providesChangeDisplayName() {
		return [
			[true, true],
			[false, false]
		];
	}
	/**
	 * @dataProvider providesChangeDisplayName
	 */
	public function testCanChangeDisplayName($expected, $implements) {
		$backend = $this->getMockBuilder(Database::class)
			->setMethods(['implementsActions'])
			->getMock();

		/** @var Account | \PHPUnit_Framework_MockObject_MockObject $account */
		$account = $this->getMockBuilder(Account::class)
			->setMethods(['getBackendInstance', 'getDisplayName', 'setDisplayName'])
			->getMock();
		$account->expects($this->any())->method('getBackendInstance')->willReturn($backend);
		$account->expects($this->any())->method('getDisplayName')->willReturn('foo');
		$account->expects($this->any())->method('setDisplayName')->willReturn($implements);

		$backend->expects($this->any())
			->method('implementsActions')
			->will($this->returnCallback(function ($actions) use ($implements) {
				if ($actions === Backend::SET_DISPLAYNAME) {
					return $implements;
				} else {
					return false;
				}
			}));

		$user = new User($account, $this->accountMapper, null, $this->config);
		$this->assertEquals($expected, $user->canChangeDisplayName());

		if ($expected) {
			$this->accountMapper->expects($this->once())
				->method('update');
		}

		$this->assertEquals($expected, $user->setDisplayName('Foo'));
	}

	/**
	 * don't allow display names containing whitespaces only
	 */
	public function testSetDisplayNameEmpty() {
		$this->account->setDisplayName('');
		$this->assertFalse($this->user->setDisplayName(' '));
		$this->assertEquals('foo', $this->user->getDisplayName());
	}

	public function testSetDisplayNameNotSupported() {
		$backend = $this->getMockBuilder(Database::class)
			->setMethods(['implementsActions'])
			->getMock();

		/** @var Account | \PHPUnit_Framework_MockObject_MockObject $account */
		$account = $this->createMock(Account::class);
		$account->expects($this->any())->method('getBackendInstance')->willReturn($backend);
		$account->expects($this->any())->method('__call')->with('getDisplayName')->willReturn('foo');

		$backend->expects($this->any())
			->method('implementsActions')
			->will($this->returnCallback(function ($actions) {
				return false;
			}));

		$user = new User($account, $this->accountMapper, null, $this->config);
		$this->assertFalse($user->setDisplayName('Foo'));
		$this->assertEquals('foo',$user->getDisplayName());
	}

	public function testSetPasswordHooks() {
		$hooksCalled = 0;
		$test = $this;

		/**
		 * @param User $user
		 * @param string $password
		 */
		$hook = function ($user, $password) use ($test, &$hooksCalled) {
			$hooksCalled++;
			$test->assertEquals('foo', $user->getUID());
			$test->assertEquals('bar', $password);
		};

		$emitter = new PublicEmitter();
		$emitter->listen('\OC\User', 'preSetPassword', $hook);
		$emitter->listen('\OC\User', 'postSetPassword', $hook);

		$backend = $this->createMock(IChangePasswordBackend::class);
		/** @var Account | \PHPUnit_Framework_MockObject_MockObject $account */
		$account = $this->createMock(Account::class);
		$account->expects($this->any())->method('getBackendInstance')->willReturn($backend);
		$account->expects($this->any())->method('__call')->with('getUserId')->willReturn('foo');
		$backend->expects($this->once())->method('setPassword')->with('foo', 'bar')->willReturn(true);

		$this->user = new User($account, $this->accountMapper, $emitter, $this->config);

		$this->user->setPassword('bar','');
		$this->assertEquals(2, $hooksCalled);
	}

	public function testDeleteHooks() {
		$hooksCalled = 0;
		$test = $this;

		/**
		 * @param User $user
		 */
		$hook = function ($user) use ($test, &$hooksCalled) {
			$hooksCalled++;
			$test->assertEquals('foo', $user->getUID());
		};

		$this->emitter->listen('\OC\User', 'preDelete', $hook);
		$this->emitter->listen('\OC\User', 'postDelete', $hook);

		$this->assertTrue($this->user->delete());
		$this->assertEquals(2, $hooksCalled);
	}

	public function testSetEnabledHook(){
		$this->eventDispatcher->expects($this->exactly(2))
			->method('dispatch')
			->with(
				$this->callback(
					function($eventName){
						if ($eventName === User::class . '::postSetEnabled' ){
							return true;
						}
						return false;
					}
				),
				$this->anything()
			)
		;

		$this->user->setEnabled(false);
		$this->user->setEnabled(true);
	}

	public function testGetCloudId() {
		$this->urlGenerator
				->expects($this->any())
				->method('getAbsoluteURL')
				->withAnyParameters()
				->willReturn('http://localhost:8888/owncloud');
		$this->assertEquals("foo@localhost:8888/owncloud", $this->user->getCloudId());
	}

	/**
	 * @dataProvider setTermsData
	 * @param array $terms
	 * @param array $expected
	 */
	public function testSettingAccountTerms(array $terms, array $expected) {
		$account = $this->getMockBuilder(Account::class)->getMock();
		$account->expects($this->once())->method('__call')->with('getId')->willReturn('foo');

		$this->accountMapper->expects($this->once())
			->method('setTermsForAccount')
			->with('foo', $expected);

		// Call the method
		$user = new User($account, $this->accountMapper, null, $this->config);
		$user->setSearchTerms($terms);
	}

	public function setTermsData() {
		return [
			'normal terms' => [['term1'], ['term1']],
			'too long terms' => [['term1', str_repeat(".", 192)], ['term1', str_repeat(".", 191)]]
		];
	}

}
