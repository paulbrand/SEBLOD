<?php
/**
* @version 			SEBLOD 3.x Core ~ $Id: raw.php sebastienheraud $
* @package			SEBLOD (App Builder & CCK) // SEBLOD nano (Form Builder)
* @url				http://www.seblod.com
* @editor			Octopoos - www.octopoos.com
* @copyright		Copyright (C) 2013 SEBLOD. All Rights Reserved.
* @license 			GNU General Public License version 2 or later; see _LICENSE.php
**/

defined( '_JEXEC' ) or die;

$config	=	JCckDev::init( array(), true, array( 'item'=>$this->item, 'tmpl'=>'ajax' ) );
Helper_Include::addDependencies( $this->getName(), $this->getLayout(), 'ajax' );

if ( is_file( JPATH_SITE.'/'.$this->file ) ) {
	include_once JPATH_SITE.'/'.$this->file;
}
?>