<?php
/**
 * @package    Redirectfixer Component
 * @version    1.0
 * @license    GNU General Public License version 2
 */

namespace Naftee\Component\Redirectfixer\Administrator\View\Redirectfixer;

\defined('_JEXEC') or die;

use Joomla\CMS\MVC\View\HtmlView as BaseHtmlView;
use Joomla\CMS\Toolbar\ToolbarHelper;
use Joomla\CMS\Language\Text;
use Joomla\CMS\Factory;

/**
 * View class for initiating the redirect fixer scan.
 */
class HtmlView extends BaseHtmlView
{
    /**
     * Application object.
     *
     * @var \Joomla\CMS\Application\CMSApplication
     */
    protected $app;

    /**
     * Array of redirect items.
     *
     * @var array
     */
    protected $items;

    /**
     * Constructor.
     *
     * @param  array  $config  An optional associative array of configuration settings.
     */
    public function __construct($config = [])
    {
        parent::__construct($config);

        $this->app   = Factory::getApplication();
        $this->items = [];
    }

    /**
     * Displays the Scan view.
     *
     * @param   string|null  $tpl  The name of the template file to parse
     * @return  void
     */
    public function display($tpl = null)
    {
        if (!$this->app->getIdentity()->authorise('core.manage', 'com_redirectfixer')) {
            $this->app->enqueueMessage(Text::_('JERROR_ALERTNOAUTHOR'), 'error');
            return;
        }

        $this->addToolbar();

        // Assign data from the model (if needed)
        $this->items = $this->get('Items');

        parent::display($tpl);
    }

    /**
     * Adds the toolbar with scanscan action.
     *
     * @return  void
     */
    protected function addToolbar()
    {
        ToolbarHelper::title(Text::_('COM_REDIRECTFIXER'), 'link redirectfixer');

        $user = $this->app->getIdentity() ?: Factory::getUser();

        if ($user->authorise('core.admin', 'com_redirectfixer')) {
            ToolbarHelper::preferences('com_redirectfixer', '500');
        }
    }
}