<?php
/**
 * @package    Redirectfixer Component
 * @version    1.3
 * @license    GNU General Public License version 2
 */

namespace Naftee\Component\Redirectfixer\Administrator\View\RedirectfixerReturn;

\defined('_JEXEC') or die;

use Joomla\CMS\MVC\View\HtmlView as BaseHtmlView;
use Joomla\CMS\Toolbar\ToolbarHelper;
use Joomla\CMS\Language\Text;
use Joomla\CMS\Factory;

/**
 * Return view class for displaying and updating the affected articles.
 */
class HtmlView extends BaseHtmlView
{
    /**
     * Displays the view.
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
      
      //Get the form from the model
      $this->form = $this->getModel()->getForm();
                
      //Get the items from the form
      $data = $this->form->getData();
      $this->items = $data->get('redirectfixer.articles', []);
      
      $this->groupedItems = [];

      // Group captured redirects by article ID
      foreach ($this->items as $item) {
          $this->groupedItems[$item['id']]['title'] = $item['title'];
          $this->groupedItems[$item['id']]['matches'][] = $item;
      }
      
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
      $app = Factory::getApplication();
      
        ToolbarHelper::title(Text::_('COM_REDIRECTFIXER_AFFECTED_ARTICLES'), 'link redirectfixer');

        ToolbarHelper::cancel('redirectfixer.cancel', 'JTOOLBAR_CANCEL');
        
        if ($app->getIdentity()->authorise('core.admin', 'com_redirectfixer')) {
          ToolbarHelper::preferences('com_redirectfixer', '550');
        }
    }
}