<?php

use Drupal\Tests\UnitTestCase;
use PHPUnit\Framework\TestCase;
use Drupal\social_auth\Event\BeforeRedirectEvent;
use Drupal\social_auth\SocialAuthDataHandler;
use Symfony\Component\EventDispatcher\Event;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Drupal\social_auth\Event\FailedAuthenticationEvent;
use Drupal\social_auth\Event\SocialAuthEvents;
use Drupal\user\UserInterface;
use Drupal\social_auth\Event\UserEvent;
use Drupal\social_auth\Event\UserFieldsEvent;



class EventTest extends UnitTestCase {
  protected $data_handler;
  protected $pluginId = 'drupal123';
  protected $destination = 'drupal123';
  protected $error = "error404";
  protected $response = 'drupal';
  protected $user = 'drupaluser';
  protected $user_fields;


  /**
   * __construct function
   */
  public function __construct() {
       parent::__construct();
   }

  /**
   * {@inheritdoc}
   */

  public function setUp() {
    parent::setUp();
  }

  /**
   * test for class testBeforeRedirectEvent
   */

  public function testBeforeRedirectEvent () {
    $this->data_handler = $this->createMock(SocialAuthDataHandler::class);
    $collection = $this->getMockBuilder('Drupal\social_auth\Event\BeforeRedirectEvent')
                       ->setConstructorArgs(array($this->data_handler, $this->pluginId, $this->destination))
                       ->setMethods(array('getDataHandler', 'getPluginId', 'getDestination'))
                       ->getMock();

    $this->assertTrue(
      method_exists($collection, 'getDataHandler'),
      'BeforeRedirectEvent does not have getDataHandler function/method'
      );
    $this->assertTrue(
      method_exists($collection, 'getPluginId'),
      'BeforeRedirectEvent does not have getPluginId function/method'
    );
    $this->assertTrue(
      method_exists($collection, 'getDestination'),
      'BeforeRedirectEvent does not have getDestination function/method'
    );
    $collection->method('getPluginId')
               ->willReturn($this->pluginId);
    $collection->method('getDestination')
               ->willReturn($this->destination);
    $collection->method('getDataHandler')
               ->willReturn($this->data_handler);
    $this->assertSame('drupal123', $collection->getPluginId());
    $this->assertSame('drupal123', $collection->getDestination());
  }

  /**
   * test for class FailedAuthenticationEvent
   */

   public function testFailedAuthenticationEvent () {
     $this->data_handler = $this->createMock(SocialAuthDataHandler::class);
     $collection = $this->getMockBuilder('Drupal\social_auth\Event\FailedAuthenticationEvent')
                        ->setConstructorArgs(array($this->data_handler, $this->pluginId, $this->error))
                        ->setMethods(array('getDataHandler', 'getPluginId', 'getError'))
                        ->getMock();
     // $reflector = new ReflectionClass( 'Drupal\social_auth\Event\FailedAuthenticationEvent' );
     // $method = $reflector->getMethod('setResponse');
     // $method->setAccessible( true );
     // $method->invokeArgs($collection, array($responses));
     $responses = $this->createMock(RedirectResponse::class);
     $collection->setResponse($responses);
     $this->assertEquals($responses, $collection->getResponse());
     // var_dump($collection->hasResponse());
     $this->assertEquals(true, $collection->hasResponse());
     $collection->method('getError')
                ->willReturn($this->error);
     $collection->method('getPluginId')
                ->willReturn($this->pluginId);
     $collection->method('getDataHandler')
                ->willReturn($this->data_handler);
     $this->assertEquals('error404', $collection->getError());
     $this->assertEquals('drupal123', $collection->getPluginId());
   }

   /**
    * tests for class SocialAuthEvents
    */

   public function testSocialAuthEvents () {
     $reflection = new ReflectionClass('Drupal\social_auth\Event\SocialAuthEvents');
     $user_fields = $reflection->getConstant('USER_FIELDS');
     $user_created = $reflection->getConstant('USER_CREATED');
     $user_login = $reflection->getConstant('USER_LOGIN');
     $user_redirect = $reflection->getConstant('BEFORE_REDIRECT');
     $faield_auth = $reflection->getConstant('FAILED_AUTH');
     $this->assertEquals('social_auth.user.fields',
            $reflection->getConstant('USER_FIELDS'),
            'The constant values is not matched');

     $this->assertEquals('social_auth.user.created',
            $reflection->getConstant('USER_CREATED'),
            'The constant values is not matched');

     $this->assertEquals('social_auth.user.login',
            $reflection->getConstant('USER_LOGIN'),
            'The constant values is not matched');

     $this->assertEquals('social_auth.before_redirect',
            $reflection->getConstant('BEFORE_REDIRECT'),
            'The constant values is not matched');

     $this->assertEquals('social_auth.failed_authentication',
            $reflection->getConstant('FAILED_AUTH'),
            'The constant values is not matched');
   }

   /**
    * tests for class UserEvent
    */

    public function testUserEvent () {
      $this->user = $this->createMock(UserInterface::class);
      $collection = $this->getMockBuilder('Drupal\social_auth\Event\UserEvent')
                         ->setConstructorArgs(array($this->user, $this->pluginId))
                         ->getMock();
      $collection->method('getPluginId')
          ->willReturn($this->pluginId);
      $collection->method('getUser')
          ->willReturn($this->user);
      $this->assertSame('drupal123', $collection->getPluginId());
    }

    /**
     * tests for class UserFieldsEvent
     */

    public function testUserFieldsEvent () {
      // $this->user = $this->createMock(UserInterface::class);
      $this->user_fields = array(1,2,3);
      $collection = $this->getMockBuilder('Drupal\social_auth\Event\UserFieldsEvent')
                         ->setConstructorArgs(array($this->user_fields, $this->pluginId))
                         ->setMethods(array('getPluginId'))
                         ->getMock();
      $collection->method('getPluginId')
                 ->willReturn($this->pluginId);
      $collection->setUserFields($this->user_fields);
      $this->assertEquals($this->user_fields, $collection->getUserFields());
      $this->assertSame('drupal123', $collection->getPluginId());
    }
}


 ?>