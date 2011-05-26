<?php
/**
 * Piwik - Open source web analytics
 * 
 * @link http://piwik.org
 * @license http://www.gnu.org/licenses/gpl-3.0.html GPL v3 or later
 * @version $Id$
 * 
 * @category Piwik_Plugins
 * @package Piwik_CoreAdminHome
 */

/**
 *
 * @package Piwik_CoreAdminHome
 */
class Piwik_CoreAdminHome_Controller extends Piwik_Controller_Admin
{
    const LOGO_HEIGHT = 110;
    const LOGO_WIDTH = 280;
    const LOGO_SMALL_HEIGHT = 50;
    const LOGO_SMALL_WIDTH = 127;
    
	public function index()
	{
		return $this->redirectToIndex('UsersManager', 'userSettings');
	}

	public function generalSettings()
	{
		Piwik::checkUserHasSomeAdminAccess();
		$view = Piwik_View::factory('generalSettings');
		
		if(Piwik::isUserIsSuperUser())
		{
    		$enableBrowserTriggerArchiving = Piwik_ArchiveProcessing::isBrowserTriggerArchivingEnabled();
    		$todayArchiveTimeToLive = Piwik_ArchiveProcessing::getTodayArchiveTimeToLive();
    		$showWarningCron = false;
    		if(!$enableBrowserTriggerArchiving
    			&& $todayArchiveTimeToLive < 3600)
    		{
    			$showWarningCron = true;
    		}
    		$view->showWarningCron = $showWarningCron;
    		$view->todayArchiveTimeToLive = $todayArchiveTimeToLive;
    		$view->enableBrowserTriggerArchiving = $enableBrowserTriggerArchiving;
    	
    		if(!Zend_Registry::get('config')->isFileWritable())
    		{
    			$view->configFileNotWritable = true;
    		}
    		$view->mail = Zend_Registry::get('config')->mail->toArray();

			$view->branding = Zend_Registry::get('config')->branding->toArray();
			
			$directoryWritable = is_writable(Piwik_Common::getPathToPiwikRoot().'/themes/');
			$logoFilesExists = file_exists(Piwik_Common::getPathToPiwikRoot().'/themes/logo.png') && file_exists(Piwik_Common::getPathToPiwikRoot().'/themes/logo-header.png');
			$logoFilesWriteable = is_writeable(Piwik_Common::getPathToPiwikRoot().'/themes/logo.png') && is_writeable(Piwik_Common::getPathToPiwikRoot().'/themes/logo-header.png');
			
			$view->logosWriteable = (($logoFilesExists && $logoFilesWriteable) || (!$logoFilesExists && $directoryWritable));
		}
		
    	$view->language = Piwik_LanguagesManager::getLanguageCodeForCurrentUser();
		$this->setBasicVariablesView($view);
		$view->topMenu = Piwik_GetTopMenu();
		$view->menu = Piwik_GetAdminMenu();
		echo $view->render();
	}
	
	public function setGeneralSettings()
	{
		Piwik::checkUserIsSuperUser();
		$response = new Piwik_API_ResponseBuilder(Piwik_Common::getRequestVar('format'));
		try {
    		$this->checkTokenInUrl();
    		$enableBrowserTriggerArchiving = Piwik_Common::getRequestVar('enableBrowserTriggerArchiving');
    		$todayArchiveTimeToLive = Piwik_Common::getRequestVar('todayArchiveTimeToLive');

    		Piwik_ArchiveProcessing::setBrowserTriggerArchiving((bool)$enableBrowserTriggerArchiving);
    		Piwik_ArchiveProcessing::setTodayArchiveTimeToLive($todayArchiveTimeToLive);
    		
    		// Update email settings
			$mail = Zend_Registry::get('config')->mail;
			$mail->transport = (Piwik_Common::getRequestVar('mailUseSmtp') == '1') ? 'smtp' : '';
			$mail->port = Piwik_Common::getRequestVar('mailPort', '');
			$mail->host = Piwik_Common::getRequestVar('mailHost', '');
			$mail->type = Piwik_Common::getRequestVar('mailType', '');
			$mail->username = Piwik_Common::getRequestVar('mailUsername', '');
			$mail->password = Piwik_Common::getRequestVar('mailPassword', '');
			$mail->encryption = Piwik_Common::getRequestVar('mailEncryption', '');
			Zend_Registry::get('config')->mail = $mail->toArray();
			
			// update branding settings
			$branding = Zend_Registry::get('config')->branding;
			$branding->use_custom_logo = Piwik_Common::getRequestVar('useCustomLogo', '0');
			Zend_Registry::get('config')->branding = $branding->toArray();
			
			$toReturn = $response->getResponse();
		} catch(Exception $e ) {
			$toReturn = $response->getResponseException( $e );
		}
		echo $toReturn;
	}
	
	/**
     * Shows the "Track Visits" checkbox.
     */
    public function optOut()
    {
		$trackVisits = !Piwik_Tracker_IgnoreCookie::isIgnoreCookieFound();

		$nonce = Piwik_Common::getRequestVar('nonce', false);
		$language = Piwik_Common::getRequestVar('language', '');
		if($nonce !== false && Piwik_Nonce::verifyNonce('Piwik_OptOut', $nonce))
		{
			Piwik_Nonce::discardNonce('Piwik_OptOut');
			Piwik_Tracker_IgnoreCookie::setIgnoreCookie();
			$trackVisits = !$trackVisits;
		}
 
		$view = Piwik_View::factory('optOut');
		$view->trackVisits = $trackVisits;
		$view->nonce = Piwik_Nonce::getNonce('Piwik_OptOut', 3600);
		$view->language = Piwik_LanguagesManager_API::getInstance()->isLanguageAvailable($language)
			? $language
			: Piwik_LanguagesManager::getLanguageCodeForCurrentUser();
		echo $view->render();
	}
	
	public function uploadCustomLogo()
	{
		if(!empty($_FILES['customLogo']) && empty($_FILES['customLogo']['error'])) {
			$file = $_FILES['customLogo']['tmp_name'];
			$error = false;
			
			list($width, $height) = getimagesize($file);


			switch($_FILES['customLogo']['type']) {
				case 'image/jpeg':
					$image = imagecreatefromjpeg($file);
					break;
				case 'image/png':
					$image = imagecreatefrompng($file);
					break;
				default:
					echo '0';
					return;
			}

			$logo = imagecreatetruecolor(self::LOGO_WIDTH, self::LOGO_HEIGHT);
			$logoSmall = imagecreatetruecolor(self::LOGO_SMALL_WIDTH, self::LOGO_SMALL_HEIGHT);
			imagecopyresized($logo, $image, 0, 0, 0, 0, self::LOGO_WIDTH, self::LOGO_HEIGHT, $width, $height);
			imagecopyresized($logoSmall, $image, 0, 0, 0, 0, self::LOGO_SMALL_WIDTH, self::LOGO_SMALL_HEIGHT, $width, $height);

			imagepng($logo, Piwik_Common::getPathToPiwikRoot().'/themes/logo.png', 3);
			imagepng($logoSmall, Piwik_Common::getPathToPiwikRoot().'/themes/logo-header.png', 3);
			echo '1';
			return;
		}
		echo '0';
	}
}
