<?php
/**
 * @package    Redirectfixer Component
 * @version    1.0
 * @license    GNU General Public License version 2
 */

namespace Naftee\Component\Redirectfixer\Administrator\Controller;

\defined('_JEXEC') or die;

use Joomla\CMS\MVC\Controller\AdminController;
use Joomla\CMS\Factory;
use Joomla\CMS\Language\Text;
use Joomla\CMS\Session\Session;
use Joomla\CMS\Router\Route;

/**
 * Controller for the Redirectfixer component.
 */
class RedirectfixerController extends AdminController
{
    /**
     * Scans articles for URLs matching redirects.
     *
     * @return  void
     * @throws  \Exception  If token validation fails
     */
    public function scan()
    {
        Session::checkToken() or $this->sendJsonError(Text::_('JINVALID_TOKEN_NOTICE'), 403);

        $app = Factory::getApplication();
        $model = $this->getModel('Redirectfixer', 'Administrator');

        if (!$this->isRedirectPluginEnabled()) {
            $app->enqueueMessage(Text::_('COM_REDIRECTFIXER_ERROR_REDIRECT_PLUGIN_DISABLED'), 'error');
            $this->setRedirect(Route::_('index.php?option=com_redirectfixer&view=Redirectfixer', false));
            return;
        }

        if (!$this->allowEdit()) {
            $app->enqueueMessage(Text::_('JLIB_APPLICATION_ERROR_EDIT_NOT_PERMITTED'), 'error');
            $this->setRedirect(Route::_('index.php?option=com_redirectfixer&view=Redirectfixer', false));
            return;
        }

        $articles = $model->getAffectedArticles();
        $app->setUserState('com_redirectfixer.articles', $articles);

        if (empty($articles)) {
            $app->enqueueMessage(Text::_('COM_REDIRECTFIXER_NO_ARTICLES'), 'warning');
        } 

        $this->setRedirect(Route::_('index.php?option=com_redirectfixer&view=RedirectfixerReturn', false));
    }

  /**
  * Fixes URLs in articles based on form submission.
  *
  * @return  void
  * @throws  \Exception  If token validation fails
  */
  public function fix()
  {
    // Validate session token
    Session::checkToken() or $this->sendJsonError(Text::_('JINVALID_TOKEN_NOTICE'), 403);

    $app = Factory::getApplication();
    $input = $app->input;

    // Check if the redirect plugin is enabled
    if (!$this->isRedirectPluginEnabled()) {
        $app->enqueueMessage(Text::_('COM_REDIRECTFIXER_ERROR_REDIRECT_PLUGIN_DISABLED'), 'error');
        $this->setRedirect(Route::_('index.php?option=com_redirectfixer&view=Redirectfixer', false));
        return;
    }

    // Check edit permissions
    if (!$this->allowEdit()) {
        $app->enqueueMessage(Text::_('JLIB_APPLICATION_ERROR_EDIT_NOT_PERMITTED'), 'error');
        $this->setRedirect(Route::_('index.php?option=com_redirectfixer&view=Redirectfixer', false));
        return;
    }

    // Retrieve form data
    $jform = $input->get('jform', [], 'array');
    $articles = !empty($jform['redirectfixer']['articles']) ? $jform['redirectfixer']['articles'] : [];

    // Validate articles data
    if (empty($articles)) {
        $app->enqueueMessage(Text::_('COM_REDIRECTFIXER_ERROR_NO_ARTICLES_SUBMITTED'), 'error');
        $this->setRedirect(Route::_('index.php?option=com_redirectfixer&view=Redirectfixer', false));
        return;
    }

    // Prepare data for the model
    $jformData = ['articles' => []];
    foreach ($articles as $index => $article) {
        if (!isset($article['id'], $article['urls']) || !is_array($article['urls']) || empty($article['urls'])) {
            $app->enqueueMessage(Text::sprintf('COM_REDIRECTFIXER_ERROR_INVALID_ARTICLE_DATA', $index), 'warning');
            continue;
        }
        $jformData['articles'][$index] = [
            'id' => (int) $article['id'],
            'urls' => array_values(array_filter($article['urls'], function ($url) {
                return !empty($url['old_url']) && !empty($url['new_url']);
            })),
        ];
    }

    if (empty($jformData['articles'])) {
        $app->enqueueMessage(Text::_('COM_REDIRECTFIXER_ERROR_NO_VALID_ARTICLES'), 'error');
        $this->setRedirect(Route::_('index.php?option=com_redirectfixer&view=Redirectfixer', false));
        return;
    }

    // Get model for article update logic
    $model = $this->getModel('Redirectfixer');
    //$model = $this->getModel('Redirectfixer', 'Administrator');

    $updated = 0;
    $updateAll = $input->getBool('update_all', false);
    $updateSingleId = $input->getInt('update_single_id', -1);

    if ($updateAll) {
        // Fix all redirects in the form
        $updated = $model->updateAllArticles($jformData);
    } elseif ($updateSingleId > 0) {
        // Select only the article from the submitted form data that matches the Id
        $singleArticleData = ['articles' => []];
        foreach ($jformData['articles'] as $index => $article) {
            if ($article['id'] === $updateSingleId) {
                $singleArticleData['articles'][$index] = $article;
            }
        }

        if (empty($singleArticleData['articles'])) {
            $app->enqueueMessage(Text::sprintf('COM_REDIRECTFIXER_ERROR_ARTICLE_NOT_FOUND', $updateSingleId), 'error');
            $this->setRedirect(Route::_('index.php?option=com_redirectfixer&view=Redirectfixer', false));
            return;
        }

        // Fix one article
        $updated = $model->updateSingleArticle($singleArticleData, $updateSingleId);
    } else {
        $app->enqueueMessage(Text::_('COM_REDIRECTFIXER_ERROR_INVALID_FORM_VALUES'), 'error');
        $this->setRedirect(Route::_('index.php?option=com_redirectfixer&view=Redirectfixer', false));
        return;
    }

    // Display result messages
    if ($updated > 0) {
        $app->enqueueMessage(Text::sprintf('COM_REDIRECTFIXER_URL_UPDATED', $updated));
    } else {
        $app->enqueueMessage(Text::_('COM_REDIRECTFIXER_NO_CHANGES_MADE'), 'warning');
    }

    $this->setRedirect(Route::_('index.php?option=com_redirectfixer&view=Redirectfixer', false));
}

    /**
     * Cancels the current operation and clears user state.
     *
     * @return  void
     */
    public function cancel()
    {
        Factory::getApplication()->setUserState('com_redirectfixer.articles', null);
        $this->setRedirect(Route::_('index.php?option=com_redirectfixer&view=Redirectfixer', false));
    }

    /**
     * Checks if the redirect plugin is enabled.
     *
     * @return  bool  True if enabled, false otherwise
     */
    protected function isRedirectPluginEnabled()
    {
        return \Joomla\CMS\Plugin\PluginHelper::isEnabled('system', 'redirect');
    }

    /**
     * Checks if the user has edit permissions.
     *
     * @return  bool  True if allowed, false otherwise
     */
    protected function allowEdit()
    {
        return Factory::getApplication()->getIdentity()->authorise('core.edit', 'com_redirectfixer');
    }

    /**
     * Sends a JSON error response and exits.
     *
     * @param   string  $message  The error message
     * @param   int     $code     The HTTP status code
     * @return  void
     */
    protected function sendJsonError($message, $code)
    {
        Factory::getApplication()->setHeader('Content-Type', 'application/json');
        Factory::getApplication()->setHeader('Status', $code);
        echo json_encode(['error' => true, 'message' => $message]);
        Factory::getApplication()->close();
    }
}