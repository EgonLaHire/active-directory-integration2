<?php
if (!defined('ABSPATH')) {
	die('Access denied.');
}

if (class_exists('Multisite_Ui_BlogConfigurationPage')) {
	return;
}

/**
 * Multisite_Ui_BlogConfigurationPage represents the BlogOption page in WordPress.
 *
 * Multisite_Ui_BlogConfigurationPage holds the methods for interacting with WordPress, displaying the rendered template and saving
 * the data.
 *
 * @author Tobias Hellmann <the@neos-it.de>
 * @author Sebastian Weinert <swe@neos-it.de>
 * @author Danny Meißner <dme@neos-it.de>
 *
 * @access public
 */
class Multisite_Ui_BlogConfigurationPage extends Multisite_View_Page_Abstract
{
	const SUB_ACTION_GENERATE_AUTHCODE = 'generateNewAuthCode';
	const SUB_ACTION_GET_ALL_OPTION_VALUES = 'getAllOptionsValues';
	const SUB_ACTION_PERSIST_OPTION_VALUES = 'persistOptionsValues';
	const SUB_ACTION_VERIFY_AD_CONNECTION = 'verifyAdConnection';

	const VERSION_BLOG_OPTIONS_JS = '1.0';

	const CAPABILITY = 'manage_options';
	const TEMPLATE = 'blog-options-page.twig';
	const NONCE = 'Active Directory Integration Configuration Nonce';

	/** @var Multisite_Ui_BlogConfigurationController */
	private $blogConfigurationController;

	/** @var Core_Validator */
	private $validator;

	/** @var Core_Validator */
	private $verificationValidator;

	/** @var array map the given subActions to the corresponding methods */
	private $actionMapping
		= array(
			self::SUB_ACTION_GENERATE_AUTHCODE     => self::SUB_ACTION_GENERATE_AUTHCODE,
			self::SUB_ACTION_GET_ALL_OPTION_VALUES => self::SUB_ACTION_GET_ALL_OPTION_VALUES,
			self::SUB_ACTION_PERSIST_OPTION_VALUES => self::SUB_ACTION_PERSIST_OPTION_VALUES,
			self::SUB_ACTION_VERIFY_AD_CONNECTION  => self::SUB_ACTION_VERIFY_AD_CONNECTION,
		);

	/** @var bool $isVerification */
	private $isVerification;

	/**
	 * @param Multisite_View_TwigContainer             $twigContainer
	 * @param Multisite_Ui_BlogConfigurationController $blogConfigurationConfigurationControllerController
	 */
	public function __construct(Multisite_View_TwigContainer $twigContainer,
								Multisite_Ui_BlogConfigurationController $blogConfigurationConfigurationControllerController
	)
	{
		parent::__construct($twigContainer);

		$this->blogConfigurationController = $blogConfigurationConfigurationControllerController;
	}

	/**
	 * Get the page title.
	 *
	 * @return string
	 */
	public function getTitle()
	{
		return esc_html__('Configuration', ADI_I18N);
	}

	/**
	 * Get the slug for post requests.
	 *
	 * @return string
	 */
	public function wpAjaxSlug()
	{
		return $this->getSlug();
	}

	/**
	 * Get the menu slug of the page.
	 *
	 * @return string
	 */
	public function getSlug()
	{
		return ADI_PREFIX . 'blog_options';
	}

	/**
	 * Render the page for an admin.
	 */
	public function renderAdmin()
	{
		$this->display(
			self::TEMPLATE, array(
				'nonce' => wp_create_nonce(self::NONCE),// create nonce for security
			)
		);
	}

	/**
	 * Include JavaScript und CSS Files into WordPress.
	 *
	 * @param $hook
	 */
	public function loadAdminScriptsAndStyle($hook)
	{
		if (strpos($hook, self::getSlug()) === false) {
			return;
		}

		$this->loadSharedAdminScriptsAndStyle();

		wp_enqueue_script(
			'adi2_blog_options_service_persistence', ADI_URL .
			'/js/app/blog-options/services/persistence.service.js', array(), self::VERSION_BLOG_OPTIONS_JS
		);
		wp_enqueue_script(
			'adi2_blog_options_service_data',
			ADI_URL . '/js/app/blog-options/services/data.service.js', array(), self::VERSION_BLOG_OPTIONS_JS
		);

		// add the controller js files
		wp_enqueue_script(
			'adi2_blog_options_controller_blog', ADI_URL .
			'/js/app/blog-options/controllers/blog.controller.js', array(), self::VERSION_BLOG_OPTIONS_JS
		);
		wp_enqueue_script(
			'adi2_blog_options_controller_ajax', ADI_URL .
			'/js/app/blog-options/controllers/ajax.controller.js', array(), self::VERSION_BLOG_OPTIONS_JS
		);
		wp_enqueue_script(
			'adi2_blog_options_controller_general', ADI_URL .
			'/js/app/blog-options/controllers/general.controller.js', array(), self::VERSION_BLOG_OPTIONS_JS
		);
		wp_enqueue_script(
			'adi2_blog_options_controller_environment', ADI_URL .
			'/js/app/blog-options/controllers/environment.controller.js', array(), self::VERSION_BLOG_OPTIONS_JS
		);
		wp_enqueue_script(
			'adi2_blog_options_controller_user', ADI_URL .
			'/js/app/blog-options/controllers/user.controller.js', array(), self::VERSION_BLOG_OPTIONS_JS
		);
		wp_enqueue_script(
			'adi2_blog_options_controller_password', ADI_URL .
			'/js/app/blog-options/controllers/password.controller.js', array(), self::VERSION_BLOG_OPTIONS_JS
		);
		wp_enqueue_script(
			'adi2_blog_options_controller_permission', ADI_URL .
			'/js/app/blog-options/controllers/permission.controller.js', array(), self::VERSION_BLOG_OPTIONS_JS
		);
		wp_enqueue_script(
			'adi2_blog_options_controller_security', ADI_URL .
			'/js/app/blog-options/controllers/security.controller.js', array(), self::VERSION_BLOG_OPTIONS_JS
		);
		wp_enqueue_script(
			'adi2_blog_options_controller_attributes', ADI_URL .
			'/js/app/blog-options/controllers/attributes.controller.js', array(), self::VERSION_BLOG_OPTIONS_JS
		);
		wp_enqueue_script(
			'adi2_blog_options_controller_sync_to_ad', ADI_URL .
			'/js/app/blog-options/controllers/sync-to-ad.controller.js', array(), self::VERSION_BLOG_OPTIONS_JS
		);
		wp_enqueue_script(
			'adi2_blog_options_controller_sync_to_wordpress', ADI_URL .
			'/js/app/blog-options/controllers/sync-to-wordpress.controller.js', array(), self::VERSION_BLOG_OPTIONS_JS
		);
	}

	/**
	 * Include shared JavaScript und CSS Files into WordPress.
	 */
	protected function loadSharedAdminScriptsAndStyle()
	{
		wp_enqueue_script("jquery");

		wp_enqueue_script('adi2_page', ADI_URL . '/js/page.js', array('jquery'), Multisite_Ui::VERSION_PAGE_JS);

		wp_enqueue_script(
			'angular.min', ADI_URL . '/js/libraries/angular.min.js',
			array(), Multisite_Ui::VERSION_PAGE_JS
		);
		wp_enqueue_script(
			'ng-alertify', ADI_URL . '/js/libraries/ng-alertify.js',
			array('angular.min'), Multisite_Ui::VERSION_PAGE_JS
		);
		wp_enqueue_script(
			'ng-notify', ADI_URL . '/js/libraries/ng-notify.min.js',
			array('angular.min'), Multisite_Ui::VERSION_PAGE_JS
		);
		wp_enqueue_script('ng-busy', ADI_URL . '/js/libraries/angular-busy.min.js',
			array('angular.min'), Multisite_Ui::VERSION_PAGE_JS);

		wp_enqueue_script(
			'adi2_shared_util_array', ADI_URL . '/js/app/shared/utils/array.util.js',
			array(), Multisite_Ui::VERSION_PAGE_JS
		);
		wp_enqueue_script(
			'adi2_shared_util_value', ADI_URL . '/js/app/shared/utils/value.util.js',
			array(), Multisite_Ui::VERSION_PAGE_JS
		);

		wp_enqueue_script('adi2_app_module', ADI_URL . '/js/app/app.module.js', array(), Multisite_Ui::VERSION_PAGE_JS);
		wp_enqueue_script('adi2_app_config', ADI_URL . '/js/app/app.config.js', array(), Multisite_Ui::VERSION_PAGE_JS);

		// add the service js files
		wp_enqueue_script(
			'adi2_shared_service_browser',
			ADI_URL . '/js/app/shared/services/browser.service.js', array(), Multisite_Ui::VERSION_PAGE_JS
		);
		wp_enqueue_script(
			'adi2_shared_service_template',
			ADI_URL . '/js/app/shared/services/template.service.js', array(), Multisite_Ui::VERSION_PAGE_JS
		);
		wp_enqueue_script(
			'adi2_shared_service_notification',
			ADI_URL . '/js/app/shared/services/notification.service.js', array(), Multisite_Ui::VERSION_PAGE_JS
		);
		wp_enqueue_script(
			'adi2_shared_service_list',
			ADI_URL . '/js/app/shared/services/list.service.js', array(), Multisite_Ui::VERSION_PAGE_JS
		);

		wp_enqueue_script(
			'selectizejs', ADI_URL . '/js/libraries/selectize.min.js',
			array('jquery'), Multisite_Ui::VERSION_PAGE_JS
		);
		wp_enqueue_script(
			'selectizeFix', ADI_URL . '/js/libraries/fixed-angular-selectize-3.0.1.js',
			array('selectizejs', 'angular.min'), Multisite_Ui::VERSION_PAGE_JS
		);

		wp_enqueue_style('adi2', ADI_URL . '/css/adi2.css', array(), Multisite_Ui::VERSION_CSS);
		wp_enqueue_style('ng-notify', ADI_URL . '/css/ng-notify.min.css', array(), Multisite_Ui::VERSION_CSS);
		wp_enqueue_style('selectizecss', ADI_URL . '/css/selectize.css', array(), Multisite_Ui::VERSION_CSS);
		wp_enqueue_style('alertify.min', ADI_URL . '/css/alertify.min.css', array(), Multisite_Ui::VERSION_CSS);
	}

	/**
	 * This method listens to post request via wp_ajax_xxx hook.
	 */
	public function wpAjaxListener()
	{
		// die if nonce is not valid
		$this->checkNonce();

		// if user has got insufficient permission, then leave
		if (!$this->currentUserHasCapability()) {
			return;
		}

		$subAction = (!empty($_POST['subAction'])) ? $_POST['subAction'] : '';

		$result = $this->routeRequest($subAction, $_POST);

		if (false !== $result) {
			$this->renderJson($result);
		}
	}

	/**
	 * Check the current request for a sub-action and delegate it to a corresponding method.
	 *
	 * @param $subAction
	 * @param $data
	 *
	 * @return Core_Message|mixed
	 */
	protected function routeRequest($subAction, $data)
	{
		$mappings = $this->getActionMapping();

		if (empty($subAction) || !isset($mappings[$subAction])) {
			return false;
		}

		$targetMethod = $mappings[$subAction];

		return call_user_func(array(&$this, $targetMethod), $data);
	}

	/**
	 * Return the current action mapping for this page.
	 *
	 * @return array
	 */
	protected function getActionMapping()
	{
		return $this->actionMapping;
	}

	/**
	 * Create and return an array with all data used by the frontend.
	 *
	 * @return array
	 */
	protected function getAllOptionsValues()
	{
		$data = $this->twigContainer->getAllOptionsValues();

		foreach ($data as $optionName => $optionData) {
			$permission = $optionData["option_permission"];

			if (Multisite_Configuration_Service::DISABLED_FOR_BLOG_ADMIN > $permission) {
				$data[$optionName]["option_value"] = "";
			}
		}

		return array(
			'options'        => $data,
			'ldapAttributes' => Ldap_Attribute_Description::findAll(),
			'dataTypes'      => Ldap_Attribute_Repository::findAllAttributeTypes(),
			'wpRoles' => Adi_Role_Manager::getRoles(),
		);
	}

	/**
	 * Generate a new auth code and return it.
	 *
	 * @return array
	 */
	protected function generateNewAuthCode()
	{
		$sanitizer = new Multisite_Option_Sanitizer();
		$newAuthCode = $sanitizer->authcode('newCode', null, null, true);

		return array('newAuthCode' => $newAuthCode);
	}

	/**
	 * Verify connection to AD to recieve domainSid.
	 *
	 * @param array $data
	 * @return array
	 */
	protected function verifyAdConnection($data)
	{
		$data = $data["data"];
		$this->validateVerification($data);

		return $this->verifyInternal($data);
	}

	/**
	 * Verify the connection by the given $data array
	 *
	 * @param array $data
	 * @param null $profileId
	 *
	 * @return array
	 */
	protected function verifyInternal($data, $profileId = null)
	{
		$failedMessage = array(
			"verification_failed" => "Verification failed! Please check your logfile for further information.",
		);
		$objectSid = $this->twigContainer->findActiveDirectoryDomainSid($data);

		if (false === $objectSid) {
			return $failedMessage;
		}

		$domainSid = Core_Util_StringUtil::objectSidToDomainSid($objectSid);
		$domainSidData = $this->prepareDomainSid($domainSid);

		if (false === $domainSid) {
			return $failedMessage;
		}

		$this->persistDomainSid($domainSidData, $profileId);

		return array("verification_successful" => $domainSid);
	}

	/**
	 * Check if the given SID is valid and normalize it for persistence.
	 *
	 * @param      $domainSid
	 *
	 * @return array
	 */
	protected function prepareDomainSid($domainSid)
	{
		if (is_string($domainSid) && $domainSid !== '') {
			return $this->getDomainSidForPersistence($domainSid);
		}

		return false;
	}

	/**
	 * Prepare an array for persistence.
	 *
	 * @param $domainSid
	 *
	 * @return array
	 */
	protected function getDomainSidForPersistence($domainSid)
	{
		return array("domain_sid" => $domainSid);
	}

	/**
	 * Persist the given option values.
	 *
	 * @param $postData
	 *
	 * @return array|boolean
	 */
	protected function persistOptionsValues($postData)
	{
		// is $_POST does not contain data, then return
		if (empty($postData['data'])) {
			return false;
		}

		$data = $postData['data'];

		//check if the permission of the option is high enough for the option to be saved
		$databaseOptionData = $this->twigContainer->getAllOptionsValues();

		foreach ($data as $optionName => $optionValue) {
			$databaseOptionPermission = $databaseOptionData[$optionName]["option_permission"];

			if (Multisite_Configuration_Service::EDITABLE != $databaseOptionPermission) {
				unset($data[$optionName]);
			}
		}

		$this->validate($data);

		return $this->blogConfigurationController->saveBlogOptions($data);
	}

	/**
	 * Delegate call to {@link Multisite_Ui_BlogConfigurationController#saveProfileOptions}.
	 *
	 * @param $data
	 * @param $profileId
	 *
	 * @return array
	 */
	public function persistDomainSid($data, $profileId = null)
	{
		return $this->blogConfigurationController->saveBlogOptions($data);
	}

	/**
	 * Validate the given data using the validator from {@code Multisite_Ui_BlogConfigurationPage#getValidator()}.
	 *
	 * @param $data
	 */
	protected function validate($data)
	{
		$this->validateWithValidator($this->getValidator(), $data);
	}

	/**
	 * Validate the given data using the validator from
	 * {@code Multisite_Ui_BlogConfigurationPage#getVerificationValidator()}.
	 *
	 * @param $data
	 */
	protected function validateVerification($data)
	{
		$this->validateWithValidator($this->getVerificationValidator(), $data);
	}

	/**
	 * Validate the data using the given {@code $validator}.
	 *
	 * @param Core_Validator $validator
	 * @param                $data
	 */
	private function validateWithValidator(Core_Validator $validator, $data)
	{
		$validationResult = $validator->validate($data);

		if (!$validationResult->isValid()) {
			$this->renderJson($validationResult->getResult());
		}
	}

	/**
	 * Get the current capability to check if the user has permission to view this page.
	 *
	 * @return string
	 */
	protected function getCapability()
	{
		return self::CAPABILITY;
	}

	/**
	 * Get the current nonce value.
	 *
	 * @return mixed
	 */
	protected function getNonce()
	{
		return self::NONCE;
	}

	/**
	 * Get the validator for the default save action.
	 *
	 * @return Core_Validator
	 */
	public function getValidator()
	{
		if (null === $this->validator) {
			$validator = $this->getSharedValidator();

			//PROFILE
			$notEmptyMessage = __('This value must not be empty.', ADI_I18N);
			$notEmptyRule = new Multisite_Validator_Rule_NotEmptyOrWhitespace($notEmptyMessage);
			$validator->addRule(Adi_Configuration_Options::PROFILE_NAME, $notEmptyRule);

			//ENVIRONMENT
			$invalidValueMessage = __('The given value is invalid.', ADI_I18N);
			$invalidSelectValueRule = new Multisite_Validator_Rule_SelectValueValid($invalidValueMessage,
				Multisite_Option_Encryption::getValues());
			$validator->addRule(Adi_Configuration_Options::ENCRYPTION, $invalidSelectValueRule);

			//USER
			$accountSuffixMessage = __(
				'Account Suffix does not match the required style. (e.g. "@company.local")',
				ADI_I18N
			);
			$accountSuffixRule = new Multisite_Validator_Rule_AccountSuffix($accountSuffixMessage, '@');
			$validator->addRule(Adi_Configuration_Options::ACCOUNT_SUFFIX, $accountSuffixRule);

			$defaultEmailDomainMessage = __('Please remove the "@", it will be added automatically.', ADI_I18N);
			$defaultEmailDomainRule = new Multisite_Validator_Rule_DefaultEmailDomain($defaultEmailDomainMessage);
			$validator->addRule(Adi_Configuration_Options::DEFAULT_EMAIL_DOMAIN, $defaultEmailDomainRule);

			//SECURITY
			$maxLoginAttempts = __('Maximum login attempts has to be numeric and cannot be negative.', ADI_I18N);
			$maxLoginAttemptsRule = new Multisite_Validator_Rule_PositiveNumericOrZero($maxLoginAttempts);
			$validator->addRule(Adi_Configuration_Options::MAX_LOGIN_ATTEMPTS, $maxLoginAttemptsRule);

			$blockTimeMessage = __('Blocking Time has to be numeric and cannot be negative.', ADI_I18N);
			$blockTimeRule = new Multisite_Validator_Rule_PositiveNumericOrZero($blockTimeMessage);
			$validator->addRule(Adi_Configuration_Options::BLOCK_TIME, $blockTimeRule);

			$adminEmailMessage = __(
				'Admin email does not match the required style. (e.g. "admin@company.local")',
				ADI_I18N
			);
			$adminEmailRule = new Multisite_Validator_Rule_AdminEmail($adminEmailMessage, '@');
			$validator->addRule(Adi_Configuration_Options::ADMIN_EMAIL, $adminEmailRule);

			//PERMISSIONS
			$disallowedRoleMessage = __('The role super admin can only be set inside a profile.', ADI_I18N);
			$disallowedRoleRule = new Multisite_Validator_Rule_DisallowSuperAdminInBlogConfig($disallowedRoleMessage);
			$validator->addRule(Adi_Configuration_Options::ROLE_EQUIVALENT_GROUPS, $disallowedRoleRule);

			//ATTRIBUTES
			$noDefaultAttributeNameMessage = __(
				'Cannot use default attribute names for custom attribute mapping.',
				ADI_I18N
			);
			$noDefaultAttributeNameRule = new Multisite_Validator_Rule_NoDefaultAttributeName(
				$noDefaultAttributeNameMessage
			);
			$validator->addRule(Adi_Configuration_Options::ADDITIONAL_USER_ATTRIBUTES, $noDefaultAttributeNameRule);

			$attributeMappingNullMessage = __(
				'Ad Attribute / Data Type / WordPress Attribute cannot be empty!',
				ADI_I18N
			);
			$attributeMappingNullRule = new Multisite_Validator_Rule_AttributeMappingNull($attributeMappingNullMessage);
			$validator->addRule(Adi_Configuration_Options::ADDITIONAL_USER_ATTRIBUTES, $attributeMappingNullRule);

			$metakeyConflictMessage = __('You cannot use the same WordPress Attribute multiple times.', ADI_I18N);
			$metakeyConflictRule = new Multisite_Validator_Rule_WordPressMetakeyConflict($metakeyConflictMessage);
			$validator->addRule(Adi_Configuration_Options::ADDITIONAL_USER_ATTRIBUTES, $metakeyConflictRule);

			$adAttributeConflictMessage = __('You cannot use the same Ad Attribute multiple times.', ADI_I18N);
			$adAttributeConflictRule = new Multisite_Validator_Rule_AdAttributeConflict($adAttributeConflictMessage);
			$validator->addRule(Adi_Configuration_Options::ADDITIONAL_USER_ATTRIBUTES, $adAttributeConflictRule);

			//SYNC TO AD
			// conditional rule for our sync_to_ad_global_user value
			$message = __('Username has to contain a suffix.', ADI_I18N);
			$syncToActiveDirectorySuffixRule = new Multisite_Validator_Rule_ConditionalSuffix(
				$message, '@', array(
					'sync_to_ad_use_global_user' => true,
				)
			);
			$validator->addRule(Adi_Configuration_Options::SYNC_TO_AD_GLOBAL_USER, $syncToActiveDirectorySuffixRule);

			//SYNC TO WORDPRESS
			// conditional rule for our sync_to_wordpress_user value
			$syncToWordPressSuffixRule = new Multisite_Validator_Rule_ConditionalSuffix(
				$message, '@', array(
					'sync_to_wordpress_enabled' => true,
				)
			);
			$validator->addRule(Adi_Configuration_Options::SYNC_TO_WORDPRESS_USER, $syncToWordPressSuffixRule);

			$this->validator = $validator;
		}

		return $this->validator;
	}

	/**
	 * Get the validator with all necessary rules for the verification.
	 *
	 * @return Core_Validator
	 */
	public function getVerificationValidator()
	{
		if (null == $this->verificationValidator) {
			$validator = $this->getSharedValidator();

			$verifyUsernameMessage = __(
				'Verification Username does not match the required style. (e.g. "Administrator@test.ad")', ADI_I18N
			);
			$verifyUsernameRule = new Multisite_Validator_Rule_AdminEmail($verifyUsernameMessage, '@');
			$validator->addRule(Adi_Configuration_Options::VERIFICATION_USERNAME, $verifyUsernameRule);

			$verifyUsernameEmptyMessage = __(
				'Verification Username does not match the required style. (e.g. "Administrator@test.ad")', ADI_I18N
			);
			$verifyUsernameEmptyRule = new Multisite_Validator_Rule_NotEmptyOrWhitespace($verifyUsernameEmptyMessage);
			$validator->addRule(Adi_Configuration_Options::VERIFICATION_USERNAME, $verifyUsernameEmptyRule);

			$verifyPasswordMessage = __('Verification Password cannot be empty.', ADI_I18N);
			$verifyPasswordRule = new Multisite_Validator_Rule_NotEmptyOrWhitespace($verifyPasswordMessage);
			$validator->addRule(Adi_Configuration_Options::VERIFICATION_PASSWORD, $verifyPasswordRule);

			$this->verificationValidator = $validator;
		}

		return $this->verificationValidator;
	}

	/**
	 * Return a validator with the shared rules.
	 */
	protected function getSharedValidator()
	{
		$validator = new Core_Validator();

		//ENVIRONMENT
		$portMessage = __('Port has to be numeric and in the range from 0 - 65535.', ADI_I18N);
		$portRule = new Multisite_Validator_Rule_Port($portMessage);
		$validator->addRule(Adi_Configuration_Options::PORT, $portRule);

		$networkTimeoutMessage = __('Network timeout has to be numeric and cannot be negative.', ADI_I18N);
		$networkTimeoutRule = new Multisite_Validator_Rule_PositiveNumericOrZero($networkTimeoutMessage);
		$validator->addRule(Adi_Configuration_Options::NETWORK_TIMEOUT, $networkTimeoutRule);

		return $validator;
	}
}