<?php
/**
 * @package    Redirectfixer Component
 * @version    1.3
 * @license    GNU General Public License version 2
 */

namespace Naftee\Component\Redirectfixer\Administrator\View\Redirectfixer;

\defined('_JEXEC') or die;

use Joomla\CMS\MVC\View\HtmlView as BaseHtmlView;
use Joomla\CMS\Toolbar\ToolbarHelper;
use Joomla\CMS\Language\Text;
use Joomla\CMS\Factory;

/**
 * View class for displaying the template which initiates the redirect fixer scan.
 */
class HtmlView extends BaseHtmlView
{

    /**
     * Displays the Scan view.
     *
     * @param   string|null  $tpl  The name of the template file to parse
     * @return  void
     */
    public function display($tpl = null)
    {
        $app = Factory::getApplication();
      
        if (!$app->getIdentity()->authorise('core.manage', 'com_redirectfixer')) {
            $app->enqueueMessage(Text::_('JERROR_ALERTNOAUTHOR'), 'error');
            return;
        }

        $this->addToolbar();

        parent::display($tpl);
    }

    /**
     * Adds the toolbar with scan action.
     *
     * @return  void
     */
    protected function addToolbar()
    {
      $app = Factory::getApplication();
      
        ToolbarHelper::title(Text::_('COM_REDIRECTFIXER'), 'link redirectfixer');

        if ($app->getIdentity()->authorise('core.admin', 'com_redirectfixer')) {
            ToolbarHelper::preferences('com_redirectfixer', '500');
        }
    }
}