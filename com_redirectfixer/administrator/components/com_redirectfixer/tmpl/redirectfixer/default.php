<?php
/**
 * @package    Redirectfixer Component
 * @version    1.1
 * @license    GNU General Public License version 2
 */

\defined('_JEXEC') or die;

use Joomla\CMS\HTML\HTMLHelper;
use Joomla\CMS\Router\Route;
use Joomla\CMS\Language\Text;

?>

<div class="redirectfixer-container">
    <div class="row">
        <div class="col-lg-12">
            <div class="card">
                <div class="card-header">
                    <h2><?php echo Text::_('COM_REDIRECTFIXER'); ?></h2>
                </div>
                <div class="card-body">
                    <p><?php echo Text::_('COM_REDIRECTFIXER_WELCOME'); ?></p>
                    <form action="<?php echo Route::_('index.php?option=com_redirectfixer&task=redirectfixer.scan'); ?>" method="post" name="adminForm" id="adminForm">
                        <?php echo HTMLHelper::_('form.token'); ?>
                        <button type="submit" class="btn btn-primary">
                            <span class="icon-search" aria-hidden="true"></span>
                            <?php echo Text::_('COM_REDIRECTFIXER_SCAN'); ?>
                        </button>
                    </form>
                </div>
            </div>
        </div>
    </div>
</div>