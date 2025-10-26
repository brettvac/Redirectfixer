<?php
/**
 * @package    Redirectfixer Component
 * @version    1.1
 * @license    GNU General Public License version 2
 */

namespace Naftee\Component\Redirectfixer\Administrator\Controller;

\defined('_JEXEC') or die;

use Joomla\CMS\MVC\Controller\BaseController;
use Joomla\CMS\Factory;

class DisplayController extends BaseController
{
    // Specify the default view to load when no view is explicitly requested in the URL
    protected $default_view = 'redirectfixer';

    public function display($cachable = false, $urlparams = array())
    {
        $viewName = $this->input->get('view', $this->default_view);        
        $viewType = Factory::getDocument()->getType();
        
        $viewLayout = $this->input->get('layout', 'default');
        
        $model = $this->getModel('Redirectfixer', 'Administrator');
        
        $view = $this->getView($viewName, $viewType, 'Administrator');

        // the View needs a pointer to the Model
        $view->setModel($model, true);
        
        $view->setLayout($viewLayout);
        
        $view->display();

    }
}