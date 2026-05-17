<?php
/**
 * @package    Redirectfixer Component
 * @version    1.3
 * @license    GNU General Public License version 2
 */

namespace Naftee\Component\Redirectfixer\Administrator\Model;

\defined('_JEXEC') or die;

use Joomla\CMS\MVC\Model\FormModel;
use Joomla\CMS\Factory;
use Joomla\CMS\Form\Form;
use Joomla\CMS\Language\Text;
use Joomla\CMS\Uri\Uri;
use Joomla\Filesystem\Path;
use Joomla\CMS\Table\Table;
use Joomla\CMS\Component\ComponentHelper;
use Joomla\Database\ParameterType;

/**
 * Model class for handling redirect URL updates in articles.
 */
class RedirectfixerModel extends FormModel
{
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
     * Constructor to initialize baseUri, db, app, and params.
     *
     * @param    array    $config    Configuration array
     */
    public function __construct($config = [])
    {
        parent::__construct($config);
        $this->db = $this->getDatabase();
    }
    
    /**
     * Retrieves articles affected by redirects.
     *
     * @return    array    An array of affected articles with their IDs, titles, and redirect URLs
     */
    public function getAffectedArticles()
    {
        $uri = new Uri(Uri::root());

        // Fetch all article IDs from the content table
        $query = $this->db->getQuery(true)
            ->select($this->db->quoteName('id'))
            ->from($this->db->quoteName('#__content'));

        $this->db->setQuery($query);
        $articleIds = $this->db->loadColumn();

        if (empty($articleIds)) {
            $this->setError(Text::_('COM_REDIRECTFIXER_NO_ARTICLES'));
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
     * Scans a single article for URLs that match defined redirects.
     *
     * @param   int  $id  The ID of the article to scan.
     *
     * @return  array  Array of ['old_url' => string (The URL found in the article), 'new_url' => string (The redirect target URL)]
     *
     * @throws  \Exception  If the database query fails.
     */
    protected function scanArticle($id)
    {
        // Fetch article from the database
        $query = $this->db->getQuery(true)
            ->select($this->db->quoteName(['id', 'title', 'introtext', 'fulltext'])) 
            ->from($this->db->quoteName('#__content'))
            ->where($this->db->quoteName('id') . ' = :id')
            ->bind(':id', $id, ParameterType::INTEGER);

        try {
            $this->db->setQuery($query);
            $article = $this->db->loadAssoc();
        } catch (\RuntimeException $e) {
            $this->setError(Text::sprintf('COM_REDIRECTFIXER_ARTICLE_NOT_FOUND', $id));
            return [];
        }

        if (empty($article)) {
            $this->setError(Text::sprintf('COM_REDIRECTFIXER_ARTICLE_EMPTY', $id));
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
            $this->setError(Text::sprintf('COM_REDIRECTFIXER_NO_REDIRECTS_TO_PROCESS', $id));
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
            $this->setError(Text::sprintf('COM_REDIRECTFIXER_NO_VALID_REDIRECTS', $id));
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
            $this->setError(Text::sprintf('COM_REDIRECTFIXER_FETCHING_REDIRECTS', $e->getMessage()));
            return [];
        }

        $validRedirects = [];

        foreach ($redirects as $redirect) {
            $oldUrl = trim($redirect['old_url']);
            $newUrl = trim($redirect['new_url']);

            // Check if old_url is absolute (starts with http:// or https://)
            $isAbsolute = preg_match('#^https?://#i', $oldUrl);

            // For absolute URLs, ensure they start with the site’s base URI
            if ($isAbsolute && strpos($oldUrl, Uri::root()) !== 0) {
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
     * Extracts URLs from text based on malformed_urls setting.
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
            // Get malformed URLs configuration parameter
            $params = ComponentHelper::getParams('com_redirectfixer');
            $malformedCheck = $params->get('malformed_urls', 'ignore');

            foreach ($hrefMatches[2] as $href) {
                if (preg_match('/^#/', $href)) {
                    continue; // Skip fragment-only URLs (e.g., #anchor)
                }
                if (preg_match('/^mailto:/', $href)) {
                    continue; // Skip mailto links
                }
                if ($malformedCheck === 'ignore') {
                    if (preg_match('/^index\.php\/https?:\/|^index\.php\/[^a-zA-Z0-9\/_-]|^[^a-zA-Z0-9\/_-]/i', $href) ||
                        !preg_match('/^(?:[a-zA-Z0-9_-]+(?:\/[a-zA-Z0-9_-]+)*|index\.php\/[a-zA-Z0-9_-]+)/i', $href)) {
                        continue; // Skip malformed URLs if configured to ignore
                    }
                }
                $urls[] = $href;
            }
              
            $urls = array_unique($urls); // Remove duplicate occurances
        }

        return $urls;
    }   
    
    /**
     * Updates all articles with new URLs based on form data.
     *
     * @param    array    $jformData    The submitted form data containing articles to update
     * @return   int                    Number of articles successfully updated
     */

    public function updateAllArticles($jformData)
    {
        $allMatches = [];
        $updatedArticles = [];

        // Extract and validate articles from form data
        $articles = !empty($jformData['redirectfixer']['articles']) ? $jformData['redirectfixer']['articles'] : [];
        if (empty($articles)) {
            $this->setError(Text::_('COM_REDIRECTFIXER_NO_ARTICLES'));
            return 0;
        }

        try {
            $this->db->transactionStart();

            // Process each article
            foreach ($articles as $article) {
                $this->processArticle($article, $allMatches, $updatedArticles);
            }

            $this->db->transactionCommit();
        } catch (\Exception $e) {
            $this->db->transactionRollback();
            $this->setError(Text::sprintf('JLIB_APPLICATION_ERROR_SAVE_FAILED', $e->getMessage()));
        }

        $app = Factory::getApplication();
        // Set state and return updated count
        $app->setUserState('com_redirectfixer.articles', $allMatches);
        
        return count($updatedArticles);
    } 

    /**
     * Updates a single article with new URLs based on form data and index.
     *
     * @param    array    $jformData    The submitted form data
     * @param    int      $articleId    The ID of the article to update
     * @return   int                    Number of articles successfully updated (0 or 1)
     */
    public function updateSingleArticle($jformData, $articleId)
    {
        $allMatches = [];
        $updatedArticles = [];
        $targetArticle = null;

        // Find the article matching the given article ID
        if (!empty($jformData['redirectfixer']['articles'])) {
            foreach ($jformData['redirectfixer']['articles'] as $article) {
                if (isset($article['id']) && (int) $article['id'] === (int) $articleId) {
                    $targetArticle = $article;
                    break;
                }
            }
        }

        if (!$targetArticle) {
            $this->setError(Text::sprintf('COM_REDIRECTFIXER_ARTICLE_NOT_FOUND', $articleId));
            return 0;
        }

        try {
            $this->db->transactionStart();

            // Process the single matched article
            $this->processArticle($targetArticle, $allMatches, $updatedArticles);

            $this->db->transactionCommit();
        } catch (\Exception $e) {
            $this->db->transactionRollback();
            $this->setError(Text::sprintf('JLIB_APPLICATION_ERROR_SAVE_FAILED', $e->getMessage()));
        }

        $app = Factory::getApplication();
        // Set state and return updated count
        $app->setUserState('com_redirectfixer.articles', $allMatches);

        return count($updatedArticles);
    }

    /**
     * Processes a single article, matching submitted URLs against content and updating it.
     * 
     * @param  array  $article          The article array containing 'id' and 'urls'
     * @param  array  &$allMatches      Reference to array storing all filtered matches for state
     * @param  array  &$updatedArticles Reference to array tracking successfully updated articles
     * @return void
     */
    protected function processArticle($article, &$allMatches, &$updatedArticles)
    {
        // Validate article ID and URLs
        if (empty($article['id']) || empty($article['urls']) || !is_array($article['urls'])) {
            $this->setError(Text::sprintf('COM_REDIRECTFIXER_INVALID_ARTICLE_DATA', $article['id'] ?? 'unknown'));
            return;
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
            $this->setError(Text::sprintf('COM_REDIRECTFIXER_NO_VALID_URLS', $articleId));
            return;
        }

        // Scan article content for matching URLs
        $matches = $this->scanArticle($articleId);

        if (empty($matches)) {
            $this->setError(Text::sprintf('COM_REDIRECTFIXER_NO_URLS_FOUND_IN_ARTICLE', $articleId));
            return;
        }

        // Filter matches to include only form-submitted URLs
        $filteredMatches = [];
        foreach ($matches as $match) {
            $normalizedMatchUrl = $this->normalizeURL($match['old_url']);
            if (isset($formUrls[$normalizedMatchUrl])) {
                $filteredMatches[] = [
                    'id'      => $match['id'],
                    'title'   => $match['title'],
                    'old_url' => $match['old_url'],
                    'new_url' => $formUrls[$normalizedMatchUrl]['new_url']
                ];
            }
        }

        if (empty($filteredMatches)) {
            $this->setError(Text::sprintf('COM_REDIRECTFIXER_NO_URLS_MATCHED_FORM', $articleId));
            return;
        }

        // Store matches for user state (passed by reference)
        $allMatches = array_merge($allMatches, $filteredMatches);

        // Update article content 
        //$this->updateArticleContent($articleId, $filteredMatches, $updatedArticles);
		if (!$this->updateArticleContent($articleId, $filteredMatches, $updatedArticles)) {
          $this->setError(Text::sprintf('COM_REDIRECTFIXER_ARTICLE_UPDATE_FAILED', $articleId));
        }
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
            $this->setError(Text::sprintf('COM_REDIRECTFIXER_ARTICLE_LOAD_FAILED', $articleId));
            return false;
        }

        // Don't modify articles which are being modified (checked out)
        if ($table->isCheckedOut()) {
            $this->setError(Text::sprintf('COM_REDIRECTFIXER_ARTICLE_UPDATE_FAILED', $articleId));
            return false;
        }

        // Merge intro and main article text
        $content = $table->introtext . ' ' . $table->fulltext;

        $urls = $this->extractURLsFromText($content);

        if (empty($urls)) {
            $this->setError(Text::sprintf('COM_REDIRECTFIXER_NO_URLS_MATCHED', $articleId));
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
                $this->setError(Text::_('COM_REDIRECTFIXER_MISSING_ARTICLE_DATA'));
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
            $this->setError(Text::sprintf('COM_REDIRECTFIXER_NO_URLS_MATCHED', $articleId));
            return false;
        }

        // Save updated article
        if ($table->store()) {
            $table->checkIn();
            $updatedArticles[] = $articleId;
            return true;
        } else {
            $this->setError(Text::sprintf('COM_REDIRECTFIXER_ARTICLE_UPDATE_FAILED', $articleId));
            return false;
        }
    
    return true;
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
     * Normalizes a URL to absolute format using the frontend root.
     *
     * @param    string    $url    The URL to normalize
     * @return    string    The absolute URL
     */

    protected function normalizeURL($url)
    {
        $uri = new Uri(Uri::root());

        if (strpos($url, $uri->toString()) === 0) {
            return $url; // URL is already absolute. Return early
        } elseif (strpos($url, '/') === 0) {
            $uri->setPath($url); // Handle paths starting with '/'
        } else {
            // Combine base URI and relative path
            $fullPath = Path::clean($uri->getPath() . '/' . $url);
            $uri->setPath($fullPath);
        }

        return $uri->toString();
    }

    /**
     * Normalizes and validates submitted article redirect data.
     *
     * @param   array  $jform  Submitted form data
     *
     * @return  array  Valid normalized articles
     */
    public function normalizeArticles(array $jform): array
    {
        $articles = $jform['redirectfixer']['articles'] ?? [];

        if (empty($articles) || !is_array($articles)) {
            return [];
        }

        $validArticles = [];

        foreach ($articles as $article) {
            if (
                empty($article['id']) ||
                empty($article['urls']) ||
                !is_array($article['urls'])
            ) {
                continue;
            }

            $urls = array_values(
                array_filter(
                    $article['urls'],
                    static function ($url) {
                        return !empty($url['old_url'])
                            && !empty($url['new_url']);
                    }
                )
            );

            if (empty($urls)) {
                continue;
            }

            $validArticles[] = [
                'id'   => (int) $article['id'],
                'urls' => $urls,
            ];
        }

        return $validArticles;
    }    

    /**
     * Method to get the form object.
     *
     * @param    array    $data      Data for the form (not typically used directly for binding here, but for custom defaults).
     * @param    bool     $loadData  True if the form is to load its own data from loadFormData().
     *
     * @return   \Joomla\CMS\Form\Form|bool A Form object on success, false on failure.
     */
    public function getForm($data = [], $loadData = true)
    {
        $form = $this->loadForm('com_redirectfixer.redirectfixer', 'redirectfixer', [
            'control'   => 'jform',
            'load_data' => $loadData, // This tells loadForm to call loadFormData() if true
        ]);

        if (empty($form)) {
            $this->setError(Text::_('COM_REDIRECTFIXER_FORM_NOT_LOADED'));
            return false; 
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
        $app = Factory::getApplication();
        // Get the raw items from the user state 
        $items = $app->getUserState('com_redirectfixer.articles', []);

        // Prepare form data structure as expected by the form XML 
        $data = [
          'redirectfixer' => [
            'articles' => $items
          ]
        ];
        
        return $data;
        
    }
}