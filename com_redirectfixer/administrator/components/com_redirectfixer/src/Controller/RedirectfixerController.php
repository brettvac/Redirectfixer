<?php
/**
 * @package    Redirectfixer Component
 * @version    1.3
 * @license    GNU General Public License version 2
 */

namespace Naftee\Component\Redirectfixer\Administrator\Controller;

\defined("_JEXEC") or die();

use Joomla\CMS\MVC\Controller\BaseController;
use Joomla\CMS\Factory;
use Joomla\CMS\Language\Text;
use Joomla\CMS\Session\Session;
use Joomla\CMS\Router\Route;

/**
 * Controller for the Redirectfixer component.
 */
class RedirectfixerController extends BaseController
{
    /**
     * Scans articles for URLs matching redirects.
     *
     * @return  void
     * @throws  \Exception  If token validation fails
     */
    public function scan()
    {
        // Check for a valid POST request token to prevent CSRF attacks
        if (!Session::checkToken()) {
            $this->app->enqueueMessage(Text::_("JINVALID_TOKEN"), "error");
            $this->setRedirect(
                Route::_("index.php?option=com_redirectfixer", false),
            );
            return;
        }

        if (!$this->isRedirectPluginEnabled()) {
            $this->app->enqueueMessage(
                Text::_("COM_REDIRECTFIXER_REDIRECT_PLUGIN_DISABLED"),
                "error",
            );
            $this->setRedirect(
                Route::_(
                    "index.php?option=com_redirectfixer&view=Redirectfixer",
                    false,
                ),
            );
            return;
        }

        if (!$this->allowEdit()) {
            $this->app->enqueueMessage(
                Text::_("JLIB_APPLICATION_ERROR_EDIT_NOT_PERMITTED"),
                "error",
            );
            $this->setRedirect(
                Route::_(
                    "index.php?option=com_redirectfixer&view=Redirectfixer",
                    false,
                ),
            );
            return;
        }

        $model = $this->getModel('Redirectfixer');
        $articles = $model->getAffectedArticles();
		
		// Fetch and display model errors ---
        $errors = $model->getErrors();
        if (!empty($errors)) {
            foreach ($errors as $error) {
                $this->app->enqueueMessage($error instanceof \Exception ? $error->getMessage() : $error, 'error');
            }
        }
        
        if (empty($articles)) {
            $this->app->enqueueMessage(
                Text::_('COM_REDIRECTFIXER_NO_ARTICLES'),
                'warning'
            );
        } else {
            $this->app->enqueueMessage(
                Text::sprintf(
                    'COM_REDIRECTFIXER_REDIRECTS_FOUND',
                    count($articles)
                ),
                'message'
            );
        }
        
        // Set the data in the user state for retrieval by the view file
        $this->app->setUserState("com_redirectfixer.articles", $articles);
       
        $this->setRedirect(
            Route::_(
                "index.php?option=com_redirectfixer&view=RedirectfixerReturn",
                false,
            ),
        );
    }

    /**
     * Fixes URLs in articles based on form submission.
     *
     * @return  void
     * @throws  \Exception  If token validation fails
     */
    public function fix()
    {
        // Check for a valid POST request token to prevent CSRF attacks
        if (!Session::checkToken()) {
            $this->app->enqueueMessage(Text::_("JINVALID_TOKEN"), "error");
            $this->setRedirect(
                Route::_("index.php?option=com_redirectfixer", false),
            );
            return;
        }

        // Check if the redirect plugin is enabled
        if (!$this->isRedirectPluginEnabled()) {
            $this->app->enqueueMessage(
                Text::_("COM_REDIRECTFIXER_REDIRECT_PLUGIN_DISABLED"),
                "error",
            );
            $this->setRedirect(
                Route::_(
                    "index.php?option=com_redirectfixer&view=Redirectfixer",
                    false,
                ),
            );
            return;
        }

        // Check edit permissions
        if (!$this->allowEdit()) {
            $this->app->enqueueMessage(
                Text::_("JLIB_APPLICATION_ERROR_EDIT_NOT_PERMITTED"),
                "error",
            );
            $this->setRedirect(
                Route::_(
                    "index.php?option=com_redirectfixer&view=Redirectfixer",
                    false,
                ),
            );
            return;
        }

        $jform = $this->input->get("jform", [], "array");
       
        // Get the model for the business logic
        $model = $this->getModel("Redirectfixer", "Administrator");
        
        $validArticles = $model->normalizeArticles($jform);

        if (empty($validArticles)) {
            $this->app->enqueueMessage(
                Text::_("COM_REDIRECTFIXER_NO_VALID_ARTICLES"),
                "error",
            );
            $this->setRedirect(
                Route::_(
                    "index.php?option=com_redirectfixer&view=Redirectfixer",
                    false,
                ),
            );
            return;
        }

        $updated = 0;
        $updateAll = $this->input->getBool("update_all", false);
        $updateSingleId = $this->input->getInt("update_single_id", -1);

        if ($updateAll) {
            // Fix all redirects in the form using the expected structure
            $updated = $model->updateAllArticles(
               ["redirectfixer" => ["articles" => $validArticles]]
            );
        } elseif ($updateSingleId > 0) {
            // Select only the article from the submitted form data that matches the Id
            $singleArticleData = [];

            foreach ($validArticles as $article) {
                if ($article["id"] === $updateSingleId) {
                    $singleArticleData[] = $article;
                    break;
                }
            }

            if (empty($singleArticleData)) {
                $this->app->enqueueMessage(
                    Text::sprintf(
                        "COM_REDIRECTFIXER_ARTICLE_NOT_FOUND",
                        $updateSingleId,
                    ),
                    "error",
                );
                $this->setRedirect(
                    Route::_(
                        "index.php?option=com_redirectfixer&view=Redirectfixer",
                        false,
                    ),
                );
                return;
            }

            // Fix one article using the expected jform structure 
            $updated = $model->updateSingleArticle(
                ["redirectfixer" => ["articles" => $singleArticleData]],
                $updateSingleId,
            );
        } else {
            $this->app->enqueueMessage(
                Text::_("COM_REDIRECTFIXER_INVALID_FORM_VALUES"),
                "error",
            );
            $this->setRedirect(
                Route::_(
                    "index.php?option=com_redirectfixer&view=Redirectfixer",
                    false,
                ),
            );
            return;
        }

        // Fetch & display model errors
        $errors = $model->getErrors();
        if (!empty($errors)) {
            foreach ($errors as $error) {
                if ($error instanceof \Exception) {
                    $this->app->enqueueMessage($error->getMessage(), 'error');
                } else {
                    $this->app->enqueueMessage($error, 'error');
                }
            }
        }

        // Display result messages
        if ($updated > 0) {
            $this->app->enqueueMessage(
                Text::sprintf("COM_REDIRECTFIXER_UPDATED", $updated),
				                'message'
            );
        } else {
            $this->app->enqueueMessage(
                Text::_("COM_REDIRECTFIXER_NO_CHANGES_MADE"),
                "warning",
            );
        }

        $this->setRedirect(
            Route::_(
                "index.php?option=com_redirectfixer&view=Redirectfixer",
                false,
            ),
        );
    }

    /**
     * Cancels the current operation and clears user state.
     *
     * @return  void
     */
    public function cancel()
    {
        $this->app->setUserState("com_redirectfixer.articles", null);
        $this->setRedirect(
            Route::_(
                "index.php?option=com_redirectfixer&view=Redirectfixer",
                false,
            ),
        );
    }

    /**
     * Checks if the redirect plugin is enabled.
     *
     * @return  bool  True if enabled, false otherwise
     */
    protected function isRedirectPluginEnabled()
    {
        return \Joomla\CMS\Plugin\PluginHelper::isEnabled("system", "redirect");
    }

    /**
     * Checks if the user has edit permissions.
     *
     * @return  bool  True if allowed, false otherwise
     */
    protected function allowEdit()
    {
        return $this->app
            ->getIdentity()
            ->authorise("core.edit", "com_redirectfixer");
    }
}