<?php
/**
 * Extended User Profile
 *
 * @copyright	The ImpressCMS Project http://www.impresscms.org/
 * @license	LICENSE.txt
 * @license	GNU General Public License (GPL) http://www.gnu.org/licenses/old-licenses/gpl-2.0.html
 * @package	modules
 * @since	1.2
 * @author	Jan Pedersen
 * @author	The SmartFactory <www.smartfactory.ca>
 * @author	Sina Asghari (aka stranger) <pesian_stranger@users.sourceforge.net>
 * @version	$Id$
 */

include_once "admin_header.php";
icms_cp_header();

$op = isset($_REQUEST['op']) ? trim($_REQUEST['op']) : 'list';
if($op == 'editordelete') $op = isset($_REQUEST['delete'])?'delete':'edit';
$adminMenuIncluded = false;
$member_handler = icms::handler('icms_member');

switch($op) {
	default:
	case 'list':
		icms::$module->displayAdminMenu(0, _MI_PROFILE_USERS);
		$adminMenuIncluded = true;
		$form = new icms_form_Theme(_AM_PROFILE_EDITUSER, 'form', 'user.php');
		$form->addElement(new icms_form_elements_select_User(_AM_PROFILE_SELECTUSER, 'id'));
		$form->addElement(new icms_form_elements_Hidden('op', 'editordelete'));
		$button_tray = new icms_form_elements_Tray('');
		$button_tray->addElement(new icms_form_elements_Button('', 'edit', _EDIT, 'submit'));
		$button_tray->addElement(new icms_form_elements_Button('', 'delete', _DELETE, 'submit'));
		$form->addElement($button_tray);

		$form->display();
		echo "<br />\n";
		$user_count = $member_handler->getUserCount(new icms_db_criteria_Item('level', '-1'));
			if(count($user_count)>1){
				$form = new icms_form_Theme(_AM_PROFILE_REMOVEDUSERS, 'form', 'user.php');
				$form->addElement(new icms_form_elements_select_User(_AM_PROFILE_SELECTUSER, 'id', false, false, false, false, true, true));
				$form->addElement(new icms_form_elements_Hidden('op', 'editordelete'));
				$button_tray = new icms_form_elements_Tray('');
				$button_tray->addElement(new icms_form_elements_Button('', 'edit', _EDIT, 'submit'));
				$form->addElement($button_tray);
				$form->display();
				echo "<br />\n";
			}
	    break;

	case 'new':
		icms_loadLanguageFile(basename(dirname(__DIR__)), 'main');
		if (!$adminMenuIncluded) icms::$module->displayAdminMenu(0, _MI_PROFILE_USERS);
		include_once('../include/forms.php');
		$obj = $member_handler->createUser();
		$obj->setGroups(array(ICMS_GROUP_USERS));
		$form = getUserForm($obj, false, false, true);
		$form->display();
		break;

	case 'edit':
		icms_loadLanguageFile(basename(dirname(__DIR__)), 'main');
		$obj = $member_handler->getUser((int)$_REQUEST['id']);
		if (in_array(ICMS_GROUP_ADMIN, $obj->getGroups()) && !in_array(ICMS_GROUP_ADMIN, icms::$user->getGroups())) {
			// If not webmaster trying to edit a webmaster - disallow
			redirect_header('user.php', 3, _AM_PROFILE_CANNOTEDITWEBMASTERS);
		}
		icms::$module->displayAdminMenu(0, _MI_PROFILE_USERS);
		include_once('../include/forms.php');
		$form = getUserForm($obj, false, false, true);
		$form->display();
		break;

	case 'save':
		icms_loadLanguageFile(basename(dirname(__DIR__)), 'main');
		if (!icms::$security->check()) redirect_header('user.php', 3, _NOPERM.'<br />'.implode('<br />', icms::$security->getErrors()));
		$uid = 0;
		if (!empty($_POST['uid'])) {
			$uid = (int)$_POST['uid'];
			$user = $member_handler->getUser($uid);
		} else {
			$user = $member_handler->createUser();
			$user->setVar('user_regdate', time());
			$user->setVar('user_avatar', 'blank.gif');
			$user->setVar('uorder', $icmsConfig['com_order']);
			$user->setVar('umode', $icmsConfig['com_mode']);
		}
		$errors = array();
		$stop = '';

		$login_name = isset($_POST['login_name']) ? trim($_POST['login_name']) : '';
		$uname = isset($_POST['uname']) ? trim($_POST['uname']) : '';
		$email = isset($_POST['email']) ? trim($_POST['email']) : '';
		$pass = isset($_POST['password']) ? icms_core_DataFilter::stripSlashesGPC($_POST['password']) : '';
		$vpass = isset($_POST['vpass']) ? icms_core_DataFilter::stripSlashesGPC($_POST['vpass']) : '';

		icms_loadLanguageFile('core', 'user');
		$stop .= icms::handler('icms_member_user')->userCheck($login_name, $uname, $email, (!$user->isNew() && $pass == '') ? false : $pass, $vpass, $user->isNew() ? 0 : $user->getVar('uid'));

		if ($user->getVar('uid') != icms::$user->getVar('uid')) {
			if ($pass != '') {
				$icmspass = new icms_core_Password();
				$pass = $icmspass->encryptPass($pass);
				$user->setVar('pass', $pass);
				$user->setVar('pass_expired', 0);
			}
			$user->setVar('level', (int)$_POST['level']);
		}
		$user->setVar('uname', $uname);
		$user->setVar('login_name', $login_name);
		$user->setVar('email', $email);
		if ($icmsConfigAuth['auth_openid'] == 1) {
			$user->setVar('openid', trim($_POST['openid']));
			$user->setVar('user_viewoid', isset($_POST['user_viewoid']) ? (int)$_POST['user_viewoid'] : 0);
		}

		if ($stop != '') $errors[] = $stop;

		// Dynamic fields
		$profile_handler = icms_getModuleHandler('profile', basename(dirname(__DIR__)), 'profile');
		// Get fields
		$fields = $profile_handler->loadFields();
		// Get ids of fields that can be edited
		$gperm_handler = icms::handler('icms_member_groupperm');
		$editable_fields = $gperm_handler->getItemIds('profile_edit', icms::$user->getGroups(), icms::$module->getVar('mid'));

		$profile = $profile_handler->get($user->getVar('uid'));

		foreach (array_keys($fields) as $i) {
			$fieldname = $fields[$i]->getVar('field_name');
			if (in_array($fields[$i]->getVar('fieldid'), $editable_fields) && ($fields[$i]->getvar('field_type') == 'image' || isset($_REQUEST[$fieldname]))) {
				if (in_array($fieldname, $profile_handler->getUserVars())) {
					$value = $fields[$i]->getValueForSave(trim($_REQUEST[$fieldname]), $user->getVar($fieldname, 'n'));
					$user->setVar($fieldname, $value);
				}
				else {
					$value = $fields[$i]->getValueForSave((isset($_REQUEST[$fieldname]) ? trim($_REQUEST[$fieldname]) : ''), $profile->getVar($fieldname, 'n'));
					$profile->setVar($fieldname, $value);
				}
			}
		}

		$new_groups = isset($_POST['groups']) ? $_POST['groups'] : array();

		if (count($errors) == 0) {
			if ($member_handler->insertUser($user)) {
				$profile->setVar('profileid', $user->getVar('uid'));
				$profile_handler->insert($profile);

				include_once(ICMS_ROOT_PATH.'/modules/system/constants.php');
				if ($gperm_handler->checkRight('system_admin', XOOPS_SYSTEM_GROUP, icms::$user->getGroups(), 1)) {
					//Update group memberships
					$cur_groups = $user->getGroups();

					$added_groups = array_diff($new_groups, $cur_groups);
					$removed_groups = array_diff($cur_groups, $new_groups);

					if (count($added_groups) > 0) {
						foreach ($added_groups as $groupid) {
							$member_handler->addUserToGroup($groupid, $user->getVar('uid'));
						}
					}
					if (count($removed_groups) > 0) {
						foreach ($removed_groups as $groupid) {
							$member_handler->removeUsersFromGroup($groupid, array($user->getVar('uid')));
						}
					}
				}
				if ($user->isNew()) {
					redirect_header('user.php', 2, _AM_PROFILE_USERCREATED, false);
				}
				else {
					redirect_header('user.php', 2, _AM_PROFILE_USERMODIFIED, false);
				}
			}
		}
		else {
			foreach ($errors as $err) $user->setErrors($err);
		}
		$user->setGroups($new_groups);

		icms::$module->displayAdminMenu(0, _MI_PROFILE_USERS);
		include_once('../include/forms.php');
		echo $user->getHtmlErrors();
		$form =& getUserForm($user, $profile);
		$form->display();
		break;

	case 'delete':
		if ($_REQUEST['id'] == icms::$user->getVar('uid')) {
			redirect_header('user.php', 2, _AM_PROFILE_CANNOTDELETESELF);
		}
		$obj = $member_handler->getUser($_REQUEST['id']);
		if (isset($_REQUEST['ok']) && $_REQUEST['ok'] == 1) {
			if (!icms::$security->check()) {
				redirect_header('user.php', 3, implode(',', icms::$security->getErrors()), false);
			}
			$profile_handler = icms_getmodulehandler('profile', basename(dirname(__DIR__)), 'profile');
			$profile = $profile_handler->get($obj->getVar('uid'));
			if ($profile->isNew() || $profile_handler->delete($profile)) {
				if ($member_handler->deleteUser($obj)) {
					redirect_header('user.php', 3, sprintf(_AM_PROFILE_DELETEDSUCCESS, $obj->getVar('uname').' ('.$obj->getVar('email').')'), false);
				} else {
					icms::$module->displayAdminMenu(0, _MI_PROFILE_USERS);
					echo $obj->getHtmlErrors();
				}
			} else {
				icms::$module->displayAdminMenu(0, _MI_PROFILE_USERS);
				echo $profile->getHtmlErrors();
			}
		} else {
			icms_core_Message::confirm(array('ok' => 1, 'id' => (int)$_REQUEST['id'], 'op' => 'delete'), $_SERVER['REQUEST_URI'], sprintf(_AM_PROFILE_RUSUREDEL, $obj->getVar('uname').' ('.$obj->getVar('email').')'));
		}
		break;
}

icms_cp_footer();