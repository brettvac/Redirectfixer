<?php
/**
 * @package    Redirectfixer Component
 * @version    1.0
 * @license    GNU General Public License version 2
 */

namespace Naftee\Component\Redirectfixer\Administrator\Model;

\defined('_JEXEC') or die;

use Joomla\CMS\MVC\Model\ListModel;
use Joomla\CMS\Factory;
use Joomla\CMS\Form\Form;
use Joomla\CMS\Language\Text;
use Joomla\CMS\Uri\Uri as JUri;
use Joomla\CMS\Filesystem\Path as JPath;
use Joomla\CMS\Table\Table;
use Joomla\CMS\Component\ComponentHelper;

/**
 * Model class for handling redirect URL updates in articles.
 */
class RedirectfixerModel extends ListModel
{
    /**
     * Base URI for the site (frontend root).
     *
     * @var string
     */
    protected $baseUri;

    /**
     * Array of articles to be processed for URL updates.
     *
     * @var array
     */
    protected $articles = [];

    /**
     * Database object.
     *
     * @var \Joomla\Database\DatabaseDriver
     */
    protected $db;

    /**
     * Application object.
     *
     * @var \Joomla\CMS\Application\CMSApplication
     */
    protected $app;

    /**
     * Component parameters.
     *
     * @var \Joomla\Registry\Registry
     */
    protected $params;

    /**
     * Constructor to initialize baseUri, db, app, and params.
     *
     * @param    array    $config    Configuration array
     */
    public function __construct($config = [])
    {
        parent::__construct($config);
        $this->baseUri = JUri::root();
        $this->db = $this->getDatabase();
        $this->app = Factory::getApplication();
        $this->params = ComponentHelper::getParams('com_redirectfixer');
    }

    /**
     * Scans a single article for URLs matching redirects.
     *
     * @param    int    $id    The article ID to scan
     * @return    array    Array of ['old_url' => string, 'new_url' => string] for URLs found in content
     * @throws    \Exception    If database query fails
     */
    protected function scanArticle($id)
    {
        // Fetch article from the database
        $query = $this->db->getQuery(true)
            ->select($this->db->quoteName(['id', 'title', 'introtext', 'fulltext'])) 
            ->from($this->db->quoteName('#__content'))
            ->where($this->db->quoteName('id') . ' = ' . (int) $id);

        try {
            $this->db->setQuery($query);
            $article = $this->db->loadAssoc();
        } catch (\RuntimeException $e) {
            $this->app->enqueueMessage(Text::sprintf('COM_REDIRECTFIXER_ERROR_ARTICLE_NOT_FOUND', $id), 'error');
            return [];
        }

        if (empty($article)) {
            $this->app->enqueueMessage(Text::sprintf('COM_REDIRECTFIXER_ERROR_ARTICLE_NOT_FOUND', $id), 'warning');
            return [];
        }

        // Combine introtext and fulltext for URL extraction
        $content = $article['introtext'] . ' ' . $article['fulltext'];

        // Extract URLs from content
        $urls = $this->extractURLsFromText($content);

        if (empty($urls)) {
            return []; // No URLs found in the article
        }

        // Fetch redirects
        $redirects = $this->getRedirects();

        if (empty($redirects)) {
            $this->app->enqueueMessage(Text::sprintf('COM_REDIRECTFIXER_NO_REDIRECTS_TO_PROCESS', $id), 'warning');
            return [];
        }

        // Normalize redirects once for efficiency
        $normalizedRedirects = [];
        foreach ($redirects as $redirect) {
            if (!empty($redirect['old_url']) && !empty($redirect['new_url'])) {
                $normalizedRedirects[$this->normalizeURL($redirect['old_url'])] = $redirect['new_url'];
            }
        }

        if (empty($normalizedRedirects)) {
            $this->app->enqueueMessage(Text::sprintf('COM_REDIRECTFIXER_NO_VALID_REDIRECTS', $id), 'warning');
            return [];
        }

        // Find matches
        $matches = [];
        foreach ($urls as $url) {
            $normalizedUrl = $this->normalizeURL($url);

            // Check if the normalized URL matches any normalized redirect
            foreach ($normalizedRedirects as $normalizedRedirectOldUrl => $newUrl) {
                if ($this->isUrlMatch($normalizedUrl, $normalizedRedirectOldUrl)) {
                    $matches[] = [
                        'id' => (int) $article['id'],
                        'title' => $article['title'], 
                        'old_url' => $url,
                        'new_url' => $newUrl
                    ];
                }
            }
        }

        return $matches;
    }

    /**
     * Retrieves articles affected by redirects.
     *
     * @return    array    An array of affected articles with their IDs, titles, and redirect URLs
     */
    public function getAffectedArticles()
    {
        $uri = new JUri($this->baseUri);

        // Fetch all article IDs from the content table
        $query = $this->db->getQuery(true)
            ->select($this->db->quoteName('id'))
            ->from($this->db->quoteName('#__content'));

        $this->db->setQuery($query);
        $articleIds = $this->db->loadColumn();

        if (empty($articleIds)) {
            $this->app->enqueueMessage(Text::_('COM_REDIRECTFIXER_NO_ARTICLES'), 'warning');
            return []; // No articles found
        }

        $results = []; // Array of articles which are redirects that need to be corrected

        // Scan each article for redirect matches
        foreach ($articleIds as $id) {
            $matches = $this->scanArticle($id);
            if (!empty($matches)) {
                $results = array_merge($results, $matches);
            }
        }

        return $results;
    }

    /**
    * Updates all articles with new URLs based on form data.
    *
    * @param    array    $jformData    The submitted form data containing articles to update
    * @return    int        Number of articles successfully updated
    * @throws    \Exception    If a database error occurs during the transaction
    */
    public function updateAllArticles($jformData)
    {
        $allMatches = [];

        // Extract and validate articles from form data
        $articles = !empty($jformData['articles']) ? $jformData['articles'] : [];
        if (empty($articles)) {
            $this->app->enqueueMessage(Text::_('COM_REDIRECTFIXER_NO_ARTICLES'), 'warning');
            return 0;
        }

        // Process each article
        try {
            $this->db->transactionStart();
            $updatedArticles = [];

            foreach ($articles as $article) {
                // Validate article ID and URLs
                if (empty($article['id']) || !isset($article['urls']) || !is_array($article['urls']) || empty($article['urls'])) {
                    $this->app->enqueueMessage(Text::sprintf('COM_REDIRECTFIXER_WARNING_INVALID_ARTICLE_DATA', $article['id'] ?? 'unknown'), 'warning');
                    continue;
                }

                $articleId = (int) $article['id'];

                // Prepare form URLs for filtering
                $formUrls = [];
                foreach ($article['urls'] as $url) {
                    if (!empty($url['old_url']) && !empty($url['new_url'])) {
                        $formUrls[$this->normalizeURL($url['old_url'])] = [
                            'old_url' => $url['old_url'],
                            'new_url' => $url['new_url']
                        ];
                    }
                }

                if (empty($formUrls)) {
                    $this->app->enqueueMessage(Text::sprintf('COM_REDIRECTFIXER_WARNING_NO_VALID_URLS', $articleId), 'warning');
                    continue;
                }

                // Scan article content for matching URLs
                $matches = $this->scanArticle($articleId);

                if (empty($matches)) {
                    $this->app->enqueueMessage(Text::sprintf('COM_REDIRECTFIXER_NO_URLS_FOUND_IN_ARTICLE', $articleId), 'warning');
                    continue;
                }

                // Filter matches to include only form-submitted URLs
                $filteredMatches = [];
                foreach ($matches as $match) {
                    $normalizedMatchUrl = $this->normalizeURL($match['old_url']);
                    if (isset($formUrls[$normalizedMatchUrl])) {
                        $filteredMatches[] = [
                            'id' => $match['id'],
                            'title' => $match['title'],
                            'old_url' => $match['old_url'],
                            'new_url' => $formUrls[$normalizedMatchUrl]['new_url']
                        ];
                    }
                }

                if (empty($filteredMatches)) {
                    $this->app->enqueueMessage(Text::sprintf('COM_REDIRECTFIXER_NO_URLS_MATCHED_FORM', $articleId), 'warning');
                    continue;
                }

                // Store matches for user state
                $allMatches = array_merge($allMatches, $filteredMatches);

                // Update article content
               $this->updateArticleContent($articleId, $filteredMatches, $updatedArticles);
            }

            $this->db->transactionCommit();
            $this->app->setUserState('com_redirectfixer.articles', $allMatches);
            return count($updatedArticles);
        } catch (\Exception $e) {
            $this->db->transactionRollback();
            $this->app->enqueueMessage(Text::sprintf('JLIB_DATABASE_ERROR_FUNCTION_FAILED', $e->getMessage()), 'error');
            $this->app->setUserState('com_redirectfixer.articles', $allMatches);
            return 0; // No articles updated
        }
    }

    /**
     * Updates a single article with new URLs based on form data and index.
     *
     * @param    array    $jformData    The submitted form data containing    ['articles' => [index => ['id' => int, 'urls' => [['old_url' => string, 'new_url' => string], ...]], ...]]
     * @param    int        $articleId      The ID of the article to update
     * @return    int    Number of articles successfully updated (0 or 1)
     * @throws    \Exception    If a database error occurs during the transaction
     */
    public function updateSingleArticle($jformData, $articleId)
    {
        // Find the article(s) matching the given article ID
        $targetArticles = [];
        foreach ($jformData['articles'] as $index => $article) {
            if (isset($article['id']) && (int) $article['id'] === (int) $articleId) {
                $targetArticles[$index] = $article;
            }
        }

        if (empty($targetArticles)) {
            $this->app->enqueueMessage(Text::sprintf('COM_REDIRECTFIXER_ERROR_ARTICLE_NOT_FOUND', $articleId), 'error');
            return 0;
        }

        // Process each matching article
        $allMatches = [];
        foreach ($targetArticles as $article) {
            if (empty($article['urls']) || !is_array($article['urls'])) {
                $this->app->enqueueMessage(Text::sprintf('COM_REDIRECTFIXER_WARNING_NO_URLS_FOR_ARTICLE', $articleId), 'warning');
                continue;
            }

            // Prepare form URLs for filtering
            $formUrls = [];
            foreach ($article['urls'] as $url) {
                if (!empty($url['old_url']) && !empty($url['new_url'])) {
                    $formUrls[$this->normalizeURL($url['old_url'])] = [
                        'old_url' => $url['old_url'],
                        'new_url' => $url['new_url']
                    ];
                }
            }

            if (empty($formUrls)) {
                $this->app->enqueueMessage(Text::sprintf('COM_REDIRECTFIXER_WARNING_NO_VALID_URLS', $articleId), 'warning');
                continue;
            }

            // Scan article content for matching URLs
            $matches = $this->scanArticle($articleId);

            if (empty($matches)) {
                $this->app->enqueueMessage(Text::sprintf('COM_REDIRECTFIXER_NO_URLS_FOUND_IN_ARTICLE', $articleId), 'warning');
                continue;
            }

            // Filter matches to include only form-submitted URLs
            $filteredMatches = [];
            foreach ($matches as $match) {
                $normalizedMatchUrl = $this->normalizeURL($match['old_url']);
                if (isset($formUrls[$normalizedMatchUrl])) {
                    $filteredMatches[] = [
                        'id' => $match['id'],
                        'title' => $match['title'],
                        'old_url' => $match['old_url'],
                        'new_url' => $formUrls[$normalizedMatchUrl]['new_url']
                    ];
                }
            }

            if (empty($filteredMatches)) {
                $this->app->enqueueMessage(Text::sprintf('COM_REDIRECTFIXER_NO_URLS_MATCHED_FORM', $articleId), 'warning');
                continue;
            }

            // Store matches for user state
            $allMatches = array_merge($allMatches, $filteredMatches);

            try {
                $this->db->transactionStart();

                // Update article content with filtered matches
                $updatedArticles = [];
                $this->updateArticleContent($articleId, $filteredMatches, $updatedArticles);

                $this->db->transactionCommit();
                $this->app->setUserState('com_redirectfixer.articles', $allMatches);
            } catch (\Exception $e) {
                $this->db->transactionRollback();
                $this->app->enqueueMessage(Text::sprintf('JLIB_DATABASE_ERROR_FUNCTION_FAILED', $e->getMessage()), 'error');
                $this->app->setUserState('com_redirectfixer.articles', $allMatches);
                return 0; // No articles updated on rollback
            }
        }

        return count($updatedArticles);
    }


    /**
    * Retrieves published redirects from the database
    *
    * @return    array    An array of redirects with ['old_url' => string, 'new_url' => string]
    * @throws    \Exception    If database query fails
    */
    protected function getRedirects()
    {
        // Fetch published redirects from the database
        $query = $this->db->getQuery(true)
            ->select($this->db->quoteName(['old_url', 'new_url']))
            ->from($this->db->quoteName('#__redirect_links'))
            ->where($this->db->quoteName('published') . ' = 1');

        try {
            $this->db->setQuery($query);
            $redirects = $this->db->loadAssocList();
        } catch (\RuntimeException $e) {
            $this->app->enqueueMessage(Text::sprintf('COM_REDIRECTFIXER_ERROR_FETCHING_REDIRECTS', $e->getMessage()), 'error');
            return [];
        }

        $validRedirects = [];

        foreach ($redirects as $redirect) {
            $oldUrl = trim($redirect['old_url']);
            $newUrl = trim($redirect['new_url']);

            // Check if old_url is absolute (starts with http:// or https://)
            $isAbsolute = preg_match('#^https?://#i', $oldUrl);

            // For absolute URLs, ensure they start with the site’s base URI
            if ($isAbsolute && strpos($oldUrl, $this->baseUri) !== 0) {
                continue; // Skip external URLs
            }

            // Validate absolute URLs
            if ($isAbsolute && !filter_var($oldUrl, FILTER_VALIDATE_URL)) {
                continue; // Skip invalid absolute URLs
            }

            $validRedirects[] = [
                'old_url' => $oldUrl,
                'new_url' => $newUrl
            ];
        }

        return $validRedirects;
    }

    /**
     * Updates the content of a single article by replacing old URLs with new ones.
     *
     * @param    int        $articleId        The ID of the article to update
     * @param    array    $matches         Array of matches containing id, old_url, new_url
     * @param    array    &$updatedArticles Array to track updated article IDs
     * @return    bool    True on success, false on failure
     */
    protected function updateArticleContent($articleId, $matches, &$updatedArticles)
    {
        // Load article content
        $table = Table::getInstance('Content', 'Joomla\\CMS\\Table\\');

        if (!$table->load($articleId)) {
            $this->app->enqueueMessage(Text::sprintf('COM_REDIRECTFIXER_ERROR_ARTICLE_LOAD_FAILED', $articleId), 'error');
            return false;
        }

        // Don't modify articles which are being modified (checked out)
        if ($table->checked_out > 0) {
            $this->app->enqueueMessage(Text::sprintf('COM_REDIRECTFIXER_ERROR_ARTICLE_UPDATE_FAILED', $articleId), 'error');
            return false;
        }

        // Merge intro and main article text
        $content = $table->introtext . ' ' . $table->fulltext;

        $urls = $this->extractURLsFromText($content);

        if (empty($urls)) {
            $this->app->enqueueMessage(Text::sprintf('COM_REDIRECTFIXER_WARNING_NO_URLS_MATCHED', $articleId), 'warning');
            return false;
        }

        $urlMatched = false;

        foreach ($matches as $match) {
            // Validate article data
            $data = [
                'id' => (int) $match['id'],
                'old_url' => trim($match['old_url']),
                'new_url' => trim($match['new_url'])
            ];


            $oldUrl = $data['old_url'];
            $newUrl = $data['new_url'];

            if (!$articleId || !$oldUrl || !$newUrl) {
                $this->app->enqueueMessage(Text::_('COM_REDIRECTFIXER_WARNING_MISSING_ARTICLE_DATA'), 'warning');
                continue;
            }

            // Normalize old_url for comparison
            $normalizedOldUrl = $this->normalizeURL($oldUrl);

            // Use new_url as replacement while preserving original format (absolute/relative )
            $replacementUrl = $newUrl;

            foreach ($urls as $extractedUrl) {
                $normalizedExtractedUrl = $this->normalizeURL($extractedUrl);

                if ($this->isUrlMatch($normalizedExtractedUrl, $normalizedOldUrl)) {
                    $table->introtext = str_replace($extractedUrl, $replacementUrl, $table->introtext);
                    $table->fulltext = str_replace($extractedUrl, $replacementUrl, $table->fulltext);
                    $urlMatched = true;
                }
            }
        }

        if (!$urlMatched) {
            $this->app->enqueueMessage(Text::sprintf('COM_REDIRECTFIXER_WARNING_NO_URLS_MATCHED', $articleId), 'warning');
            return false;
        }

        // Save updated article
        if ($table->store()) {
            $table->checkIn();
            $updatedArticles[] = $articleId;
            return true;
        } else {
            $this->app->enqueueMessage(Text::sprintf('COM_REDIRECTFIXER_WARNING_ARTICLE_UPDATE_FAILED', $articleId), 'warning');
            return false;
        }
    }

    /**
     * Checks if a URL matches a redirect URL (both normalized to absolute).
     *
     * @param    string    $urlPath        The normalized URL path to check
     * @param    string    $redirectUrl    The normalized redirect URL to match against
     * @return    bool    True if URLs match, false otherwise
     */
    protected function isUrlMatch($urlPath, $redirectUrl)
    {
        $result = (string) $urlPath === (string) $redirectUrl;
        return $result;
    }

    /**
     * Extracts URLs from text based on query_strings setting.
     *
     * @param    string    $text    The text to extract URLs from
     * @return    array    Array of unique URLs
     */
    protected function extractURLsFromText($text)
    {
        $urls = []; // List of extracted URLs

        // Extract href attributes from text
        preg_match_all('/href=(["\'])(.*?)\1/i', $text, $hrefMatches);

        if (!empty($hrefMatches[2])) {
            // Get query strings configuration
            $queryStrings = $this->params->get('query_strings', 'ignore');

            foreach ($hrefMatches[2] as $href) {
                if (preg_match('/^#/', $href)) {
                    continue; // Skip fragment-only URLs (e.g., #anchor)
                }

                if (preg_match('/^mailto:/', $href)) {
                    continue; // Skip mailto links
                }

                if (preg_match('/^index\.php\/https?:\/|^index\.php\/[^a-zA-Z0-9\/_-]|^[^a-zA-Z0-9\/_-]/i', $href)) {
                    continue; // Skip malformed URLs (e.g., index.php/https:/..., invalid characters)
                }

                if (!preg_match('/^(?:[a-zA-Z0-9_-]+(?:\/[a-zA-Z0-9_-]+)*|index\.php\/[a-zA-Z0-9_-]+)/i', $href)) {
                    continue; // Validate relative URL format
                }

                if ($queryStrings === 'strip' && strpos($href, '?') !== false) {
                    $href = strtok($href, '?');
                } elseif ($queryStrings === 'ignore' && strpos($href, '?') !== false) {
                    continue; // Handle query strings based on configuration
                }

                $urls[] = $href;
            }
            $urls = array_unique($urls); // Remove duplicate occurances

        }

        return $urls;
    }

    /**
     * Normalizes a URL to absolute format using the frontend root.
     *
     * @param    string    $url    The URL to normalize
     * @return    string    The absolute URL
     */

    protected function normalizeURL($url)
    {
        $uri = new JUri($this->baseUri);

        if (strpos($url, $uri->toString()) === 0) {
            return $url; // URL is already absolute. Return early
        } elseif (strpos($url, '/') === 0) {
            $uri->setPath($url); // Handle paths starting with '/'
        } else {
            // Combine base URI and relative path
            $fullPath = JPath::clean($uri->getPath() . '/' . $url);
            $uri->setPath($fullPath);
        }

        return $uri->toString();
    }

      /**
     * Method to get the table object.
     *
     * @param   string  $type    The table name.
     * @param   string  $prefix  The class prefix.
     * @param   array   $options An optional array of options for the table.
     *
     * @return  JTable  A JTable object
     *
     * @since   1.6
     */
    public function getTable(
        $type = "Content",
        $prefix = "Joomla\\CMS\\Table\\",
        $options = [],
    ) {
        return Table::getInstance($type, $prefix, $options);
    }

        /**
     * Method to get the form object.
     *
     * @param    array    $data      Data for the form (not typically used directly for binding here, but for custom defaults).
     * @param    bool     $loadData  True if the form is to load its own data from loadFormData().
     *
     * @return   \Joomla\CMS\Form\Form|null A Form object on success, null on failure.
     */
    public function getForm($data = [], $loadData = true)
    {
        $form = $this->loadForm('com_redirectfixer.redirectfixer', 'redirectfixer', [
            'control'   => 'jform',
            'load_data' => $loadData, // This tells loadForm to call loadFormData() if true
        ]);

        if (empty($form)) {
            $this->app->enqueueMessage(Text::_('COM_REDIRECTFIXER_FORM_NOT_LOADED'), 'error');
            return null; 
        }

        return $form;
    }

    /**
     * Method to get the data that should be injected in the form.
     * This method is automatically called by JForm when $loadData is true in getForm().
     *
     * @return    array    The data for the form.
     */
    protected function loadFormData()
    {
        // Get the raw items from the user state 
        $items = $this->getItem();

        // Prepare form data structure as expected by the form XML
        $formData = ['redirectfixer' => ['articles' => []]];
        $groupedItems = [];

        foreach ($items as $item) {
            $id = $item['id'];
            if (!isset($groupedItems[$id])) {
                $groupedItems[$id] = [
                    'id'    => $id,
                    'title' => $item['title'],
                    'urls'  => []
                ];
            }
            $groupedItems[$id]['urls'][] = [
                'old_url' => $item['old_url'],
                'new_url' => $item['new_url']
            ];
        }

        $formData['redirectfixer']['articles'] = array_values($groupedItems);

        return $formData;
    }

    /**
     * Method to get the raw data from the user state.
     * In this component's context, the "item" is the array of affected articles
     * stored in the user state, prior to grouping for the form.
     *
     * @return    array    An array of article data (id, title, old_url, new_url).
     */
    protected function getItem()
    {
        // Retrieve the raw array of affected articles from the user state
        $items = $this->app->getUserState('com_redirectfixer.articles', []);

        return $items;
    }
}