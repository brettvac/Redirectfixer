<?php
/**
 * @package    Redirectfixer Component
 * @version    1.0
 * @license    GNU General Public License version 2
 */

namespace Naftee\Component\Redirectfixer\Administrator\View\RedirectfixerReturn;

\defined('_JEXEC') or die;

use Joomla\CMS\MVC\View\HtmlView as BaseHtmlView;
use Joomla\CMS\Toolbar\ToolbarHelper;
use Joomla\CMS\Language\Text;
use Joomla\CMS\Factory;

/**
 * View class for displaying and updating affected articles.
 */
class HtmlView extends BaseHtmlView
{
    /**
     * Array of affected articles.
     *
     * @var array
     */
    protected $items = [];

    /**
     * Displays the view.
     *
     * @param   string|null  $tpl  The name of the template file to parse
     * @return  void
     */
    public function display($tpl = null)
    {
        if (!Factory::getApplication()->getIdentity()->authorise('core.manage', 'com_redirectfixer')) {
            Factory::getApplication()->enqueueMessage(Text::_('JERROR_ALERTNOAUTHOR'), 'error');
            return;
        } 

        $this->items = Factory::getApplication()->getUserState('com_redirectfixer.articles', []);
        $this->addToolbar();
        parent::display($tpl);
    }

    /**
     * Adds the toolbar with update and navigation actions.
     *
     * @return  void
     */
    protected function addToolbar()
    {
        ToolbarHelper::title(Text::_('COM_REDIRECTFIXER_AFFECTED_ARTICLES'), 'link redirectfixer');

        ToolbarHelper::cancel('redirectfixer.cancel', 'JTOOLBAR_CANCEL');

        $user = Factory::getApplication()->getIdentity() ?: Factory::getUser();
        if ($user->authorise('core.admin', 'com_redirectfixer')) {
            ToolbarHelper::preferences('com_redirectfixer', '500');
        }
    }
}