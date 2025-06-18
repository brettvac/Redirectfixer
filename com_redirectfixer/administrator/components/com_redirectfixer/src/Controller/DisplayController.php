<?php
/**
 * @package    Redirectfixer Component
 * @version    1.0
 * @license    GNU General Public License version 2
 */

namespace Naftee\Component\Redirectfixer\Administrator\Controller;

\defined('_JEXEC') or die;

use Joomla\CMS\MVC\Controller\BaseController;
use Joomla\CMS\Factory;

class DisplayController extends BaseController
{
    protected $default_view = 'redirectfixer';

    public function display($cachable = false, $urlparams = array())
    {
        $app = Factory::getApplication();
        $viewName = $this->input->get('view', $this->default_view);
        $viewLayout = $this->input->get('layout', 'default');
        $viewType = Factory::getDocument()->getType();

        $view = $this->getView($viewName, $viewType);
        //$view = $this->getView($viewName, $viewType, 'Administrator');
        
        $model = $this->getModel('Redirectfixer');
        //$model = $this->getModel('Redirectfixer', 'Administrator');

        // the View needs a pointer to the Model
        $view->setModel($model, true);
        
        $view->setLayout($viewLayout);
        $view->display();

        return true;
    }
}