<?php
/**
 * @package		akeebasubs
 * @copyright	Copyright (c)2010-2011 Nicholas K. Dionysopoulos / AkeebaBackup.com
 * @license		GNU GPLv3 <http://www.gnu.org/licenses/gpl.html> or later
 */

defined('_JEXEC') or die();

class plgAkeebasubsUserdelete extends JPlugin
{
	/**
	 * Called whenever a subscription is modified. Namely, when its enabled status,
	 * payment status or valid from/to dates are changed.
	 */
	public function onAKSubscriptionChange(KDatabaseRowDefault $row)
	{
		// Only handle expired subscriptions
		if( (($row->state == "C") || ($row->state == "X")) && ($row->enabled == 0) ) {
			$this->onAKUserRefresh($row->user_id);
		}
	}
	
	/**
	 * Called whenever the administrator asks to refresh integration status.
	 * 
	 * @param $user_id int The Joomla! user ID to refresh information for.
	 */
	public function onAKUserRefresh($user_id)
	{
		// Get all of the user's subscriptions
		$subscriptions = KFactory::tmp('admin::com.akeebasubs.model.subscriptions')
			->user_id($user_id)
			->getList();
			
		// Make sure there are subscriptions set for the user
		if(!count($subscriptions)) return;
		
		// Check if the user has active subscriptions
		$active = 0;
		foreach($subscriptions as $sub) {
			if($sub->enabled) {
				$active++;
			} elseif( $sub->state == 'P' ) {
				// Pending payments don't mean that the subscription is expired or invalid (yet)
				$active++;
			}
		}
		
		if(!$active) {
			if(version_compare(JVERSION, '1.6.0', 'ge')) {
				$this->removeJ16($user_id);
			} else {
				$this->removeJ15($user_id);
			}
		}
	}
	
	/**
	 * Removes a user using the Joomla! 1.5 method
	 * @param int $id The user ID to delete
	 */
	private function removeJ15($id)
	{
		$acl			=& JFactory::getACL();
		
		// check for a super admin ... can't delete them
		$objectID 	= $acl->get_object_id( 'users', $id, 'ARO' );
		$groups 	= $acl->get_object_groups( $objectID, 'ARO' );
		$this_group = strtolower( $acl->get_group_name( $groups[0], 'ARO' ) );

		$success = false;
		if ( $this_group == 'super administrator' ) {
			return;
		} else {
			$user =& JUser::getInstance((int)$id);
			$user->delete();
		}
	}
	
	/**
	 * Removes a user using the Joomla! 1.6+ method
	 * @param int $id The user ID to delete
	 */
	private function removeJ16($id)
	{
		$table	= JTable::getInstance('User', 'JTable', array());
		
		// Trigger the onUserBeforeSave event.
		JPluginHelper::importPlugin('user');
		$dispatcher = JDispatcher::getInstance();
		
		// Will not delete Super Administrators
		if(JAccess::check($id, 'core.admin')) return;
		
		if($table->load($id)) {
			$user_to_delete = JFactory::getUser($id);
			$dispatcher->trigger('onUserBeforeDelete', array($table->getProperties()));
			if($table->delete($id)) {
				$dispatcher->trigger('onUserAfterDelete', array($user_to_delete->getProperties(), true, $this->getError()));
			}
		}
		
	}

}