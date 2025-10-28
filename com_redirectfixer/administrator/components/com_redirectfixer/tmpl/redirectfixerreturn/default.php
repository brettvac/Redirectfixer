<?php
/**
 * @package    Redirectfixer Component
 * @version    1.2
 * @license    GNU General Public License version 2
 */

\defined('_JEXEC') or die;

use Joomla\CMS\Language\Text;
use Joomla\CMS\HTML\HTMLHelper;
use Joomla\CMS\Router\Route;
use Joomla\CMS\Factory;

HTMLHelper::_('behavior.formvalidator');
HTMLHelper::_('behavior.keepalive');

$app = Factory::getApplication();
$messages = $app->getMessageQueue();

$hasErrors = false; // Check if there are any error messages during article updating
foreach ($messages as $message) {
    if (($message['type'] ?? '') === 'error') {
        $hasErrors = true;
        break;
    }
}

?>
<form action="<?php echo Route::_('index.php?option=com_redirectfixer'); ?>" method="post" name="adminForm" id="adminForm">
    <h2><?php echo Text::_('COM_REDIRECTFIXER_AFFECTED_ARTICLES'); ?></h2>
    
    <?php if (empty($this->items) && !$hasErrors) : ?>
        <p><?php echo Text::_('COM_REDIRECTFIXER_NO_ARTICLES'); ?></p>
    <?php else : ?>
        <?php
        // Group captured redirects by article ID
        $groupedItems = [];
        foreach ($this->items as $item) {
            $id = $item['id'];
            if (!isset($groupedItems[$id])) {
                $groupedItems[$id] = [
                    'title' => $item['title'],
                    'matches' => []
                ];
            }
            $groupedItems[$id]['matches'][] = $item;
        }
        ?>
        <p><?php echo Text::sprintf('COM_REDIRECTFIXER_TOTAL_REDIRECTS_FOUND', count($this->items)); ?></p>
     
       <p>
            <button type="submit" name="update_all" value="1" class="btn btn-primary mx-3"><?php echo Text::_('COM_REDIRECTFIXER_FIX_ALL'); ?></button>
        </p>

        <table class="table table-striped table-hover">
            <thead>
                <tr>
                    <th><?php echo Text::_('COM_REDIRECTFIXER_TITLE'); ?></th>
                    <th><?php echo Text::_('COM_REDIRECTFIXER_OLD_URL'); ?></th>
                    <th><?php echo Text::_('COM_REDIRECTFIXER_NEW_URL'); ?></th>
                    <th><?php echo Text::_('COM_REDIRECTFIXER_ACTION'); ?></th>
                </tr>
            </thead>
            <tbody>
        <?php $index = 0; ?>
        <?php foreach ($groupedItems as $id => $group) : ?>
        <tr>
            <td><?php echo $this->escape($group['title']); ?></td>
            <td>
                <ul>
                    <?php foreach ($group['matches'] as $j => $match) : ?>
                        <li>
                            <input type="hidden" name="jform[redirectfixer][articles][<?php echo $index; ?>][urls][<?php echo $j; ?>][old_url]" value="<?php echo $this->escape($match['old_url']); ?>">
                            <?php echo $this->escape($match['old_url']); ?>
                        </li>
                    <?php endforeach; ?>
                </ul>
            </td>
            <td>
                <ul>
                    <?php foreach ($group['matches'] as $j => $match) : ?>
                        <li>
                            <input type="hidden" name="jform[redirectfixer][articles][<?php echo $index; ?>][urls][<?php echo $j; ?>][new_url]" value="<?php echo $this->escape($match['new_url']); ?>">
                            <?php echo $this->escape($match['new_url']); ?>
                        </li>
                    <?php endforeach; ?>
                </ul>
            </td>
            <td>
                <input type="hidden" name="jform[redirectfixer][articles][<?php echo $index; ?>][id]" value="<?php echo $this->escape($id); ?>">
                <button type="submit" name="update_single_id" value="<?php echo $id; ?>" class="btn btn-primary">
                    <?php echo Text::_('COM_REDIRECTFIXER_FIX'); ?>
                </button>
            </td>
          </tr>
          <?php $index++; ?>
      <?php endforeach; ?>
         </tbody>
        </table>
    <?php endif; ?>
    <input type="hidden" name="task" value="redirectfixer.fix">
    <?php echo HTMLHelper::_('form.token'); ?>
</form>