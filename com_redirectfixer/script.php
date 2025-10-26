<?php
/**
 * @package    Redirectfixer Component
 * @version    1.1
 * @license    GNU General Public License version 2
 */

defined('_JEXEC') or die;

use Joomla\CMS\Factory;
use Joomla\CMS\Installer\InstallerAdapter;
use Joomla\CMS\Installer\InstallerScriptInterface;
use Joomla\CMS\Language\Text;

return new class () implements InstallerScriptInterface {

    private string $minimumJoomla = '4.4.0';
    private string $minimumPhp    = '7.2.5';

    public function install(InstallerAdapter $adapter): bool
    {
        Factory::getApplication()->enqueueMessage(Text::_('COM_REDIRECTFIXER_INSTALL_SUCCESS'), 'success');
        return true;
    }

    public function update(InstallerAdapter $adapter): bool
    {
        Factory::getApplication()->enqueueMessage(Text::_('COM_REDIRECTFIXER_UPDATE_SUCCESS'), 'success');
        return true;
    }

    public function uninstall(InstallerAdapter $adapter): bool
    {
        Factory::getApplication()->enqueueMessage(Text::_('COM_REDIRECTFIXER_UNINSTALL_SUCCESS'), 'success');
        return true;
    }

    public function preflight(string $type, InstallerAdapter $adapter): bool
    {
        if (version_compare(PHP_VERSION, $this->minimumPhp, '<')) {
            Factory::getApplication()->enqueueMessage(
                Text::sprintf('JLIB_INSTALLER_MINIMUM_PHP', $this->minimumPhp),
                'error'
            );
            return false;
        }

        if (version_compare(JVERSION, $this->minimumJoomla, '<')) {
            Factory::getApplication()->enqueueMessage(
                Text::sprintf('JLIB_INSTALLER_MINIMUM_JOOMLA', $this->minimumJoomla),
                'error'
            );
            return false;
        }
    
        return true;
    }

    public function postflight(string $type, InstallerAdapter $adapter): bool
    {
        Factory::getApplication()->enqueueMessage(
            Text::_('COM_REDIRECTFIXER_POSTFLIGHT_COMPLETE'),
            'info'
        );
        return true;
    }
};