<?php
/**
* @version 			SEBLOD 3.x Core ~ $Id: cck.php sebastienheraud $
* @package			SEBLOD (App Builder & CCK) // SEBLOD nano (Form Builder)
* @url				http://www.seblod.com
* @editor			Octopoos - www.octopoos.com
* @copyright		Copyright (C) 2013 SEBLOD. All Rights Reserved.
* @license 			GNU General Public License version 2 or later; see _LICENSE.php
**/

defined( '_JEXEC' ) or die;

// Plugin
class plgContentCCK extends JPlugin
{
	protected $cache	=	false;
	protected $loaded	=	array();
	protected $title	=	'';
	
	// onContentAfterDelete
	public function onContentAfterDelete( $context, $data )
	{
		switch ( $context ) {
			case 'com_content.article':
				$base	=	'content';
				$custom	=	'introtext';
				$pk		=	$data->id;
				break;
			case 'com_categories.category':
				$base	=	'categories';
				$custom	=	'description';
				$pk		=	$data->id;
				break;
			default:
				return true;
		}
		
		preg_match( '#::cck::(.*)::/cck::#U', $data->$custom, $matches );
		$id		=	$matches[1];
		
		if ( ! $id ) {
			return true;
		}
		
		$table	=	JCckTable::getInstance( '#__cck_core', 'id', $id );
		$type	=	$table->cck;
		$table->delete();
		
		// Processing
		JLoader::register( 'JCckToolbox', JPATH_PLATFORM.'/cms/cck/toolbox.php' );
		if ( JCckToolbox::getConfig()->get( 'processing', 0 ) ) {
			$event		=	'onContentAfterDelete';
			$processing	=	JCckDatabaseCache::loadObjectListArray( 'SELECT type, scriptfile FROM #__cck_more_toolbox_processings WHERE published = 1 ORDER BY ordering', 'type' );
			if ( isset( $processing[$event] ) ) {
				foreach ( $processing[$event] as $p ) {
					if ( is_file( JPATH_SITE.$p->scriptfile ) ) {
						include_once JPATH_SITE.$p->scriptfile;	/* Variables: $id, $pk, $type */
					}
				}
			}
		}
		
		$tables	=	JCckDatabase::loadColumn( 'SHOW TABLES' );
		$prefix	= 	JFactory::getApplication()->getCfg( 'dbprefix' );
		
		if ( in_array( $prefix.'cck_store_item_'.$base, $tables ) ) {
			$table	=	JCckTable::getInstance( '#__cck_store_item_'.$base, 'id', $pk );
			if ( $table->id ) {
				$table->delete();
			}
		}
		
		if ( in_array( $prefix.'cck_store_form_'.$type, $tables ) ) {
			$table	=	JCckTable::getInstance( '#__cck_store_form_'.$type, 'id', $pk );
			if ( $table->id ) {
				$table->delete();
			}
		}
		
		return true;
	}

	// onContentBeforeDisplay
	public function onContentBeforeDisplay( $context, &$article, &$params, $limitstart = 0 )
	{
		if ( $this->title ) {
			$article->title	=	$this->title;
		}
		if ( JCck::getConfig_Param( 'hide_edit_icon', 0 ) ) {
			if ( isset( $article->params ) ) {
				$article->params->set( 'access-edit', false );
			}
		}
		
		return '';
	}
	
	// onContentPrepare
	public function onContentPrepare( $context, &$article, &$params, $limitstart = 0 )
	{
		if ( strpos( $article->text, '/cck' ) === false ) {
			return true;
		}
		
		$this->_prepare( $context, $article, $params, $limitstart );
	}
	
	// _prepare
	protected function _prepare( $context, &$article, &$params, $page = 0 )
	{
		$property	=	'text';
		preg_match( '#::cck::(.*)::/cck::#U', $article->$property, $matches );
	  	if ( ! @$matches[1] ) {
			return;
		}

		$join			=	' LEFT JOIN #__cck_core_folders AS f ON f.id = b.folder';
		$join_select	=	', f.app as folder_app';
		$query			=	'SELECT a.id, a.pk, a.pkb, a.cck, a.storage_location, a.store_id, b.id as type_id, b.alias as type_alias, b.indexed, b.stylesheets,'
						.	' b.options_content, b.options_intro, c.template as content_template, c.params as content_params, d.template as intro_template, d.params as intro_params'.$join_select
						.	' FROM #__cck_core AS a'
						.	' LEFT JOIN #__cck_core_types AS b ON b.name = a.cck'
						.	' LEFT JOIN #__template_styles AS c ON c.id = b.template_content'
						.	' LEFT JOIN #__template_styles AS d ON d.id = b.template_intro'
						.	$join
						.	' WHERE a.id = "'.(string)$matches[1].'"'
						;
		$cck			=	JCckDatabase::loadObject( $query );
		$contentType	=	(string)$cck->cck;
		$article->id	=	(int)$cck->pk;
		if ( ! $contentType ) {
			return;
		}
		
		JPluginHelper::importPlugin( 'cck_storage_location' );
		if ( $context == 'text' ) {
			$client	=	'intro';
		} elseif ( $context == 'com_finder.indexer' ) {
			if ( $cck->indexed == 'none' ) {
				$article->$property		=	'';
				return;
			}
			$client	=	( empty( $cck->indexed ) ) ? 'intro' : $cck->indexed;
		} else {
			if ( $cck->storage_location != '' ) {
				$properties	=	array( 'contexts' );
				$properties	=	JCck::callFunc( 'plgCCK_Storage_Location'.$cck->storage_location, 'getStaticProperties', $properties );
				$client		=	( in_array( $context, $properties['contexts'] ) ) ? 'content' : 'intro';
			} else {
				$client		=	'intro';
			}
		}
		
		// Fields
		$app 	=	JFactory::getApplication();
		$fields	=	array();
		$lang	=	JFactory::getLanguage();
		$user	=	JFactory::getUser();
		$access	=	implode( ',', $user->getAuthorisedViewLevels() );
		
		if ( $client == 'intro' && $this->cache ) {
			$query		=	'SELECT cc.*, c.label as label2, c.variation, c.link, c.link_options, c.markup, c.markup_class, c.typo, c.typo_label, c.typo_options, c.access, c.restriction, c.restriction_options, c.position'
						.	' FROM #__cck_core_type_field AS c'
						.	' LEFT JOIN #__cck_core_types AS sc ON sc.id = c.typeid'
						.	' LEFT JOIN #__cck_core_fields AS cc ON cc.id = c.fieldid'
						.	' WHERE sc.name = "'.$contentType.'" AND sc.published = 1 AND c.client = "'.$client.'" AND c.access IN ('.$access.')'
						.	' ORDER BY c.ordering ASC'
						;
			$fields		=	JCckDatabaseCache::loadObjectList( $query, 'name' );	//#
			if ( ! count( $fields ) && $client == 'intro' ) {
				$client	=	'content';
				$query	=	'SELECT cc.*, c.label as label2, c.variation, c.link, c.link_options, c.markup, c.markup_class, c.typo, c.typo_label, c.typo_options, c.access, c.restriction, c.restriction_options, c.position'
						.	' FROM #__cck_core_type_field AS c'
						.	' LEFT JOIN #__cck_core_types AS sc ON sc.id = c.typeid'
						.	' LEFT JOIN #__cck_core_fields AS cc ON cc.id = c.fieldid'
						.	' WHERE sc.name = "'.$contentType.'" AND sc.published = 1 AND c.client = "'.$client.'" AND c.access IN ('.$access.')'
						.	' ORDER BY c.ordering ASC'
						;
				$fields	=	JCckDatabaseCache::loadObjectList( $query, 'name' );	//#
			}
		} else {
			$query		=	'SELECT cc.*, c.label as label2, c.variation, c.link, c.link_options, c.markup, c.markup_class, c.typo, c.typo_label, c.typo_options, c.access, c.restriction, c.restriction_options, c.position'
						.	' FROM #__cck_core_type_field AS c'
						.	' LEFT JOIN #__cck_core_types AS sc ON sc.id = c.typeid'
						.	' LEFT JOIN #__cck_core_fields AS cc ON cc.id = c.fieldid'
						.	' WHERE sc.name = "'.$contentType.'" AND sc.published = 1 AND c.client = "'.$client.'" AND c.access IN ('.$access.')'
						.	' ORDER BY c.ordering ASC'
						;
			$fields		=	JCckDatabase::loadObjectList( $query, 'name' );	//#
			if ( ! count( $fields ) && $client == 'intro' ) {
				$client	=	'content';
				$query	=	'SELECT cc.*, c.label as label2, c.variation, c.link, c.link_options, c.markup, c.markup_class, c.typo, c.typo_label, c.typo_options, c.access, c.restriction, c.restriction_options, c.position'
						.	' FROM #__cck_core_type_field AS c'
						.	' LEFT JOIN #__cck_core_types AS sc ON sc.id = c.typeid'
						.	' LEFT JOIN #__cck_core_fields AS cc ON cc.id = c.fieldid'
						.	' WHERE sc.name = "'.$contentType.'" AND sc.published = 1 AND c.client = "'.$client.'" AND c.access IN ('.$access.')'
						.	' ORDER BY c.ordering ASC'
						;
				$fields	=	JCckDatabase::loadObjectList( $query, 'name' );	//#
			}
		}
		if ( !isset( $this->loaded[$contentType.'_'.$client.'_options'] ) ) {
			$lang->load( 'pkg_app_cck_'.$cck->folder_app, JPATH_SITE, null, false, false );
			$registry	=	new JRegistry;
			$registry->loadString( $cck->{'options_'.$client} );
			$this->loaded[$contentType.'_'.$client.'_options']	=	$registry->toArray();
			if ( isset( $this->loaded[$contentType.'_'.$client.'_options']['title'] ) ) {
				if ( $this->loaded[$contentType.'_'.$client.'_options']['title'] != '' && $this->loaded[$contentType.'_'.$client.'_options']['title'][0]	==	'{' ) {
					$titles		=	json_decode( $this->loaded[$contentType.'_'.$client.'_options']['title'] );
					$lang_tag	=	JFactory::getLanguage()->getTag();
					$this->loaded[$contentType.'_'.$client.'_options']['title']	=	( isset( $titles->{$lang_tag} ) ) ? $titles->{$lang_tag} : '';
				}
			}
			if ( isset( $this->loaded[$contentType.'_'.$client.'_options']['sef'] ) ) {
				if ( $this->loaded[$contentType.'_'.$client.'_options']['sef'] == '' ) {
					$this->loaded[$contentType.'_'.$client.'_options']['sef']	=	JCck::getConfig_Param( 'sef', '2' );
				}
			}
		}
		
		// Template
		$tpl['home']							=	$app->getTemplate();
		$tpl['folder']							=	$cck->{$client.'_template'};
		$tpl['params']							=	json_decode( $cck->{$client.'_params'}, true );
		$tpl['params']['rendering_css_core']	=	$cck->stylesheets;
		if ( file_exists( JPATH_SITE.'/templates/'.$tpl['home'].'/html/tpl_'.$tpl['folder'] ) ) {
			$tpl['folder']	=	'tpl_'.$tpl['folder'];
			$tpl['root']	=	JPATH_SITE.'/templates/'.$tpl['home'].'/html';
		} else {
			$tpl['root']	=	JPATH_SITE.'/templates';
		}
		$tpl['path']		=	$tpl['root'].'/'.$tpl['folder'];
		if ( ! $tpl['folder'] || ! file_exists( $tpl['path'].'/index.php' ) ) {
			$article->$property		=	str_replace( $article->$property, 'Template Style does not exist. Open the Content Type & save it again. (Intro + Content views)', $article->$property );
			return;
		}
		
		$this->_render( $context, $article, $tpl, $contentType, $fields, $property, $client, $cck );
	}
	
	// _render
	protected function _render( $context, &$article, $tpl, $contentType, $fields, $property, $client, $cck )
	{
		$app		=	JFactory::getApplication();
		$dispatcher	=	JDispatcher::getInstance();
		$user		=	JFactory::getUser();
		$params		=	array( 'template'=>$tpl['folder'], 'file'=>'index.php', 'directory'=>$tpl['root'] );
		
		$lang	=	JFactory::getLanguage();
		$lang->load( 'com_cck_default', JPATH_SITE );
		
		JPluginHelper::importPlugin( 'cck_field' );
		JPluginHelper::importPlugin( 'cck_field_link' );
		JPluginHelper::importPlugin( 'cck_field_restriction' );
		$p_sef		=	isset( $this->loaded[$contentType.'_'.$client.'_options']['sef'] ) ? $this->loaded[$contentType.'_'.$client.'_options']['sef'] : JCck::getConfig_Param( 'sef', '2' );
		$p_title	=	isset( $this->loaded[$contentType.'_'.$client.'_options']['title'] ) ? $this->loaded[$contentType.'_'.$client.'_options']['title'] : '';
		$p_typo		=	isset( $this->loaded[$contentType.'_'.$client.'_options']['typo'] ) ? $this->loaded[$contentType.'_'.$client.'_options']['typo'] : 1;
		if ( $p_typo ) {
			JPluginHelper::importPlugin( 'cck_field_typo' );
		}
		
		jimport( 'cck.rendering.document.document' );
		$doc		=	CCK_Document::getInstance( 'html' );
		$positions	=	array();
		if ( $client == 'intro' /* && $this->cache */ ) {
			$positions_more	=	JCckDatabaseCache::loadObjectList( 'SELECT * FROM #__cck_core_type_position AS a LEFT JOIN #__cck_core_types AS b ON b.id = a.typeid'
																 . ' WHERE b.name = "'.$contentType.'" AND a.client ="'.$client.'"', 'position' );	// todo::improve
		} else {
			$positions_more	=	JCckDatabase::loadObjectList( 'SELECT * FROM #__cck_core_type_position AS a LEFT JOIN #__cck_core_types AS b ON b.id = a.typeid'
															. ' WHERE b.name = "'.$contentType.'" AND a.client ="'.$client.'"', 'position' );	// todo::improve
		}
		
		// Fields
		if ( count( $fields ) ) {
			JPluginHelper::importPlugin( 'cck_storage' );
			$config	=	array( 'author'=>0,
							   'client'=>$client,
   							   'doSEF'=>$p_sef,
							   'doTranslation'=>JCck::getConfig_Param( 'language_jtext', 0 ),
							   'doTypo'=>$p_typo,
							   'fields'=>array(),
							   'id'=>$cck->id,
							   'isNew'=>0,
							   'Itemid'=>$app->input->getInt( 'Itemid', 0 ),
							   'location'=>$cck->storage_location,
							   'pk'=>$article->id,
							   'pkb'=>$cck->pkb,
							   'storages'=>array(),
							   'store_id'=>(int)$cck->store_id,
							   'type'=>$cck->cck,
							   'type_id'=>(int)$cck->type_id,
							   'type_alias'=>( $cck->type_alias ? $cck->type_alias : $cck->cck )
							);
			
			foreach ( $fields as $field ) {
				$field->typo_target	=	'value';
				$fieldName			=	$field->name;
				$value				=	'';
				$name				=	( ! empty( $field->storage_field2 ) ) ? $field->storage_field2 : $fieldName; //-
				if ( $fieldName ) {
					$Pt	=	$field->storage_table;
					if ( $Pt && ! isset( $config['storages'][$Pt] ) ) {
						$config['storages'][$Pt]	=	'';
						$dispatcher->trigger( 'onCCK_Storage_LocationPrepareContent', array( &$field, &$config['storages'][$Pt], $config['pk'], &$config, &$article ) );
					}
					
					$dispatcher->trigger( 'onCCK_StoragePrepareContent', array( &$field, &$value, &$config['storages'][$Pt] ) );
					if ( is_string( $value ) ) {
						$value		=	trim( $value );
					}
					if ( $p_title != '' && $p_title == $field->name ) {
						$this->title	=	$value;
					}
					$hasLink	=	( $field->link != '' ) ? 1 : 0;
					$dispatcher->trigger( 'onCCK_FieldPrepareContent', array( &$field, $value, &$config ) );
					$target		=	$field->typo_target;
					// -- CONDITIONS
					if ( $hasLink ) {
						$dispatcher->trigger( 'onCCK_Field_LinkPrepareContent', array( &$field, &$config ) );
						if ( $field->link ) {
							JCckPluginLink::g_setHtml( $field, $target );
						}
					}
					if ( @$field->typo && $field->$target !== '' && $p_typo ) {
						$dispatcher->trigger( 'onCCK_Field_TypoPrepareContent', array( &$field, $field->typo_target, &$config ) );
					} else {
						$field->typo	=	'';
					}
					$position					=	$field->position;
					$positions[$position][]		=	$fieldName;
				}
			}
			
			// Merge
			if ( count( $config['fields'] ) ) {
				$fields				=	array_merge( $fields, $config['fields'] );	// Test: a loop may be faster.
				$config['fields']	=	NULL;
				unset( $config['fields'] );
			}
		}
		
		// BeforeRender
		if ( isset( $config['process']['beforeRenderContent'] ) && count( $config['process']['beforeRenderContent'] ) ) {
			foreach ( $config['process']['beforeRenderContent'] as $process ) {
				if ( $process->type ) {
					JCck::callFunc_Array( 'plg'.$process->group.$process->type, 'on'.$process->group.'BeforeRenderContent', array( $process->params, &$fields, &$config['storages'], &$config ) );
				}
			}
		}
		
		// Finalize
		$doc->fields	=	&$fields;
		$infos			=	array( 'context'=>$context, 'params'=>$tpl['params'], 'path'=>$tpl['path'], 'root'=>JURI::root( true ), 'template'=>$tpl['folder'], 'theme'=>$tpl['home'] );
		$doc->finalize( 'content', $contentType, $client, $positions, $positions_more, $infos, $cck->id );
		
		$data					=	$doc->render( false, $params );
		$article->$property		=	str_replace( $article->$property, $data, $article->$property );
	}
}
?>