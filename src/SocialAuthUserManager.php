<?php

/**
 * @file
 * Contains \Drupal\social_auth\SocialAuthUserManager.
 */

namespace Drupal\social_auth;

use Drupal\user\Entity\User;
use Drupal\user\UserInterface;
use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Logger\LoggerChannelFactoryInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Entity\EntityStorageException;

/**
 * Contains all logic that is related to Drupal user management.
 */
class SocialAuthUserManager {

  protected $configFactory;
  protected $loggerFactory;
  protected $entityTypeManager;

  /**
   * Constructor.
   *
   * @param \Drupal\Core\Config\ConfigFactoryInterface $config_factory
   *   Used for accessing Drupal configuration.
   * @param \Drupal\Core\Logger\LoggerChannelFactoryInterface $logger_factory
   *   Used for logging errors.
   * @param \Drupal\Core\Entity\EntityTypeManagerInterface $entity_type_manager
   *   Used for loading and creating Drupal user objects.
   */
  public function __construct(ConfigFactoryInterface $config_factory, LoggerChannelFactoryInterface $logger_factory, EntityTypeManagerInterface $entity_type_manager) {
    $this->configFactory      = $config_factory;
    $this->loggerFactory      = $logger_factory;
    $this->entityTypeManager  = $entity_type_manager;
  }

  /**
   * Loads existing Drupal user object by given property and value.
   *
   * Note that first matching user is returned. Email address and account name
   * are unique so there can be only zero or one matching user when
   * loading users by these properties.
   *
   * @param string $field
   *   User entity field to search from.
   * @param string $value
   *   Value to search for.
   *
   * @return \Drupal\user\Entity\User|false
   *   Drupal user account if found
   *   False otherwise
   */
  public function loadUserByProperty($field, $value) {
    $users = $this->entityTypeManager
      ->getStorage('user')
      ->loadByProperties(array($field => $value));

    if (!empty($users)) {
      return current($users);
    }

    // If user was not found, return FALSE.
    return FALSE;
  }

  /**
   * Create a new user account.
   *
   * @param string $name
   *   User's name on Facebook.
   * @param string $email
   *   User's email address.
   *
   * @param string $message
   *   Drupal message if user could not be created
   *
   * @return \Drupal\user\Entity\User|false Drupal user account if user was created
   * Drupal user account if user was created
   * False otherwise
   */
  public function createUser($name, $email) {
    // Make sure we have everything we need.
    if (!$name || !$email) {
      $this->loggerFactory
        ->get('social_auth')
        ->error('Failed to create user. Name: @name, email: @email', array('@name' => $name, '@email' => $email));
      return FALSE;
    }

    // Check if site configuration allows new users to register.
    if ($this->registrationBlocked()) {

      $this->loggerFactory
        ->get('social_auth')
        ->warning('Failed to create user. User registration is disabled in Drupal account settings. Name: @name, email: @email.', array('@name' => $name, '@email' => $email));

      return FALSE;
    }

    // Set up the user fields.
    // - Username will be user's name on Facebook.
    // - Password can be very long since the user doesn't see this.
    $fields = array(
      'name' => $this->generateUniqueUsername($name),
      'mail' => $email,
      'init' => $email,
      'pass' => $this->userPassword(32),
      'status' => $this->getNewUserStatus(),
    );

    // Create new user account.
    $new_user = $this->entityTypeManager
      ->getStorage('user')
      ->create($fields);

    // Try to save the new user account.
    try {
      $new_user->save();

      $this->loggerFactory
        ->get('social_auth')
        ->notice('New user created. Username @username, UID: @uid', array('@username' => $new_user->getAccountName(), '@uid' => $new_user->id()));

      return $new_user;
    }

    catch (EntityStorageException $ex) {
      $this->loggerFactory
        ->get('social_auth')
        ->error('Could not create new user. Exception: @message', array('@message' => $ex->getMessage()));
    }

    return FALSE;
  }

  /**
   * Logs the user in.
   *
   * @param \Drupal\user\Entity\User $drupal_user
   *   User object.
   *
   * @return bool
   *   True if login was successful
   *   False if the login was blocked
   */
  public function loginUser(User $drupal_user) {

    // Check that the account is active and log the user in.
    if ($drupal_user->isActive()) {
      $this->userLoginFinalize($drupal_user);

      return TRUE;
    }

    $this->loggerFactory
      ->get('social_auth')
      ->warning('Login for user @user prevented. Account is blocked.', array('@user' => $drupal_user->getAccountName()));
    return FALSE;
  }

  /**
   * Checks if user registration is blocked in Drupal account settings.
   *
   * @return bool
   *   True if registration is blocked
   *   False if registration is not blocked
   */
  protected function registrationBlocked() {
    // Check if Drupal account registration settings is Administrators only.
    if ($this->configFactory
      ->get('user.settings')
      ->get('register') == 'admin_only') {
      return TRUE;
    }

    return FALSE;
  }

  /**
   * Ensures that Drupal usernames will be unique.
   *
   * Drupal usernames will be generated so that the user's full name on Facebook
   * will become user's Drupal username. This method will check if the username
   * is already used and appends a number until it finds the first available
   * username.
   *
   * @param string $name
   *   User's full name on Facebook.
   *
   * @return string
   *   Unique username
   */
  protected function generateUniqueUsername($name) {
    $base = trim($name);
    $i = 1;
    $candidate = $base;
    while ($this->loadUserByProperty('name', $candidate)) {
      $i++;
      $candidate = $base . " " . $i;
    }
    return $candidate;
  }

  /**
   * Returns the status for new users.
   *
   * @return int
   *   Value 0 means that new accounts remain blocked and require approval.
   *   Value 1 means that visitors can register new accounts without approval.
   */
  protected function getNewUserStatus() {
    if ($this->configFactory
      ->get('user.settings')
      ->get('register') == 'visitors') {
      return 1;
    }

    return 0;
  }

  /**
   * Wrapper for user_password.
   *
   * We need to wrap the legacy procedural Drupal API functions so that we are
   * not using them directly in our own methods. This way we can unit test our
   * own methods.
   * @see user_password
   *
   * @param int $length
   *
   * @return string
   */
  protected function userPassword($length) {
    return user_password($length);
  }

  /**
   * Wrapper for user_login_finalize.
   *
   * We need to wrap the legacy procedural Drupal API functions so that we are
   * not using them directly in our own methods. This way we can unit test our
   * own methods.
   *
   * @see user_password
   */
  protected function userLoginFinalize(UserInterface $account) {
    user_login_finalize($account);
  }

}