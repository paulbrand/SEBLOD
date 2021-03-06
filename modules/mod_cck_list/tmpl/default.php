<?php
/**
* @version 			SEBLOD 3.x Core ~ $Id: default.php sebastienheraud $
* @package			SEBLOD (App Builder & CCK) // SEBLOD nano (Form Builder)
* @url				http://www.seblod.com
* @editor			Octopoos - www.octopoos.com
* @copyright		Copyright (C) 2013 SEBLOD. All Rights Reserved.
* @license 			GNU General Public License version 2 or later; see _LICENSE.php
**/

defined( '_JEXEC' ) or die;

if ( $show_list_desc == 1 && $description != '' ) {
	echo '<div class="cck_module_desc'.$class_sfx.'">' . JHtml::_( 'content.prepare', $description ) . '</div><div class="clr"></div>';
}
?>
<?php if ( !$raw_rendering ) { ?>
<div class="cck_module_list<?php echo $class_sfx; ?>">
<?php }
if ( $search->content > 0 ) {
	echo ( $raw_rendering ) ? $data : '<div>'.$data.'</div>';
} else {
	include dirname(__FILE__).'/default_items.php';
}
?>
<?php if ( $show_more_link ) { ?>
	<div class="more"><a<?php echo $show_more_class; ?> href="<?php echo $show_more_link; ?>"><?php echo JText::_( 'MOD_CCK_LIST_VIEW_ALL' ); ?></a></div>
<?php } if ( !$raw_rendering ) { ?>
</div>
<?php }
if ( $show_list_desc == 2 && $description != '' ) {
	echo '<div class="cck_module_desc'.$class_sfx.'">' . JHtml::_( 'content.prepare', $description ) . '</div><div class="clr"></div>';
}
?>