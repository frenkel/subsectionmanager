<?php

	/**
	 * @package subsectionmanager
	 */
	/**
	 * This field provides inline subsection management. 
	 */
	if(!defined('__IN_SYMPHONY__')) die('<h2>Symphony Error</h2><p>You cannot directly access this file</p>');

	require_once(EXTENSIONS . '/subsectionmanager/lib/class.subsectionmanager.php');
	if(!class_exists('Stage')) {
		require_once(EXTENSIONS . '/subsectionmanager/lib/stage/class.stage.php');
	}

	Class fieldSubsectionmanager extends Field {
		
		/**
		 * @see http://symphony-cms.com/learn/api/2.2/toolkit/field/#__construct
		 */
		function __construct(&$parent) {
			parent::__construct($parent);
			$this->_name = __('Subsection Manager');
			$this->_required = true;
		}

		/**
		 * @see http://symphony-cms.com/learn/api/2.2/toolkit/field/#canFilter
		 */
		function canFilter(){
			return true;
		}

		/**
		 * @see http://symphony-cms.com/learn/api/2.2/toolkit/field/#allowDatasourceParamOutput
		 */
		function allowDatasourceParamOutput(){
			return true;
		}

		/**
		 * @see http://symphony-cms.com/learn/api/2.2/toolkit/field/#displaySettingsPanel
		 */
		function displaySettingsPanel(&$wrapper, $errors=NULL) {

			// Basics
			parent::displaySettingsPanel($wrapper, $errors);

		/*-----------------------------------------------------------------------*/

			// Get current section id
			$section_id = Symphony::Engine()->Page->_context[1];

			// Related section
			$label = new XMLElement('label', __('Subsection'));
			$sectionManager = new SectionManager(Symphony::Engine());
		  	$sections = $sectionManager->fetch(NULL, 'ASC', 'name');
			$options = array(
				array('', false, __('None Selected')),
			);
			if(is_array($sections) && !empty($sections)) {
				foreach($sections as $section) {
					$options[] = array($section->get('id'), ($section->get('id') == $this->get('subsection_id')), $section->get('name'));
				}
			}
			$label->appendChild(Widget::Select('fields[' . $this->get('sortorder') . '][subsection_id]', $options, array('class' => 'subsectionmanager')));
			if(isset($errors['subsection_id'])) {
				$wrapper->appendChild(Widget::wrapFormElementWithError($label, $errors['subsection_id']));
			}
			else {
				$wrapper->appendChild($label);
			}

		/*-----------------------------------------------------------------------*/

			// Filter input
			$label = new XMLElement('label', __('Filter items by tags or categories') . '<i>' . __('Comma separated, alt+click for negation') . '</i>', array('class' => 'filter', 'style' => 'display: none;'));
			$label->appendChild(Widget::Input('fields[' . $this->get('sortorder') . '][filter_tags]', $this->get('filter_tags')));
			$wrapper->appendChild($label);

			// Filter suggestions
			if(is_array($sections) && !empty($sections)) {
				
				// Get values
				$values = array();
				foreach($sections as $section) {
				
					// Don't include the current section
					if($section->get('id') != $section_id) {
						$fields = $section->fetchFields();
						
						// Continue if fields exist
						if(is_array($fields)) {
							foreach($fields as $field) {
								
								// Fetch only taglist or select boxes
								if($field->get('type') == 'taglist' || $field->get('type') == 'select' ) {
									
									// Fetch dynamic filter values
									$dynamic = Symphony::Database()->fetchCol(
										'value',
										"SELECT DISTINCT `value` FROM `tbl_entries_data_" . $field->get('id') . "` LIMIT 100"
									);						
									
									// Fetch static filter values
									$static = explode(', ', $field->get('static_options'));

									// Merge dynamic and static values
									$filters = array_unique(array_merge($dynamic, $static));
									
									$relation = 'section' . $section->get('id');
									foreach($filters as $value) {
										if(!empty($value)) {
											$values[$value][] = $relation;
										}
									}				

								}
							}
							
						}
						
					}
				}
				
				// Generate list
				if(!empty($values)) {
					$filter = new XMLElement('ul', NULL, array('class' => 'tags negation subsectionmanager'));
					foreach($values as $handle => $fields) {
						$filter->appendChild(new XMLElement('li', $handle, array('rel' => implode(' ', $fields))));
					}
					$wrapper->appendChild($filter);
				}
					
			}

		/*-----------------------------------------------------------------------*/

			// Behaviour
			$fieldset = Stage::displaySettings(
				$this->get('id'), 
				$this->get('sortorder'), 
				__('Behaviour')
			);

			// Handle missing settings
			if(!$this->get('id') && $errors == NULL) {
				$this->set('allow_multiple', 1);
				$this->set('show_preview', 1);
				$this->set('recursion_levels', 1);
				$this->set('allow_quantities', 0);
			}
			
			// Get settings
			$div = $fieldset->getChildren();

			// Setting: allow multiple
			$setting = new XMLElement('label', '<input name="fields[' . $this->get('sortorder') . '][allow_multiple]" value="1" type="checkbox"' . ($this->get('allow_multiple') == 0 ? '' : ' checked="checked"') . '/> ' . __('Allow selection of multiple items') . ' <i>' . __('This will switch between single and multiple item lists') . '</i>');
			$div[0]->appendChild($setting);

			// Setting: disallow editing
			$setting = new XMLElement('label', '<input name="fields[' . $this->get('sortorder') . '][lock]" value="1" type="checkbox"' . ($this->get('lock') == 0 ? '' : ' checked="checked"') . '/> ' . __('Disallow item editing') . ' <i>' . __('This will lock items and disable the inline editor') . '</i>');
			$div[0]->appendChild($setting);

			// Setting: allow quantities
			$setting = new XMLElement('label', '<input name="fields[' . $this->get('sortorder') . '][allow_quantities]" value="1" type="checkbox"' . ($this->get('allow_quantities') == 0 ? '' : ' checked="checked"') . '/> ' . __('Allow item quantities') . ' <i>' . __('This will enable selecting the same item multiple times') . '</i>');
			$div[0]->appendChild($setting);
			
			// Append behaviour settings
			$wrapper->appendChild($fieldset);

		/*-----------------------------------------------------------------------*/

			// Display
			$fieldset = new XMLElement('fieldset', '<legend>' . __('Display') . '</legend>');

			$div = new XMLElement('div', NULL, array('class' => 'group'));
			
			// Caption input
			$div->appendChild($this->__groupContentGenerator('caption', __('Caption'), $sections, $errors));
			
			// Custom drop text
			$div->appendChild($this->__groupContentGenerator('droptext', __('Drop text'), $sections, $errors));

			$fieldset->appendChild($div);

			// Preview options
			$label = new XMLElement('label');
			$input = Widget::Input('fields[' . $this->get('sortorder') . '][show_preview]', 1, 'checkbox');
			if($this->get('show_preview') != 0) {
				$input->setAttribute('checked', 'checked');
			}
			$label->setValue(__('%s Show thumbnail images', array($input->generate())));
			$fieldset->appendChild($label);			
			$wrapper->appendChild($fieldset);

		/*-----------------------------------------------------------------------*/

			// XML Output
			$fieldset = new XMLElement('fieldset', '<legend>' . __('XML Output') . '</legend>');

			// Recursion levels
			$label = new XMLElement('label');
			$recursion_levels = $this->get('recursion_levels');
			$select = Widget::Select('fields[' . $this->get('sortorder') . '][recursion_levels]', array(
				array('0', ($recursion_levels == 0), __('Ignore')),
				array('1', ($recursion_levels == 1), __('Output 1 level down')),
				array('2', ($recursion_levels == 2), __('Output 2 levels down')),
				array('3', ($recursion_levels == 3), __('Output 3 levels down')),
			));
			$label->setValue(__('Nested subsections %s', array($select->generate())));
			$fieldset->appendChild($label);

			$wrapper->appendChild($fieldset);

		/*-----------------------------------------------------------------------*/

			// General
			$fieldset = new XMLElement('fieldset', '<legend>' . __('General') . '</legend>');
			$this->appendShowColumnCheckbox($fieldset);
			$this->appendRequiredCheckbox($fieldset);
			$wrapper->appendChild($fieldset);

			// Compatibility with 1.x
			$wrapper->appendChild(Widget::Input('fields[' . $this->get('sortorder') . '][included_fields]', $this->get('included_fields'), 'hidden'));

		}
		
		/**
		 * 
		 */
		private function __groupContentGenerator($name, $title, $sections, $errors) {
			$container = new XMLElement('div');
			$label = new XMLElement('label', $title);
			$label->appendChild(Widget::Input('fields[' . $this->get('sortorder') . '][' . $name . ']', htmlspecialchars($this->get($name))));
			
			// Append Caption
			if(isset($errors[$name])) {
				$container->appendChild(Widget::wrapFormElementWithError($label, $errors[$name]));
			}
			else {
				$container->appendChild($label);
			}
			
			// Caption suggestions		
			if(is_array($sections) && !empty($sections) && !isset($errors[$name])) {
				
				// Get values
				$values = array();
				foreach($sections as $section) {
				
					// Don't include the current section
					if($section->get('id') != $section_id) {
						$fields = $section->fetchFields();
						
						// Continue if fields exist
						if(is_array($fields)) {
							foreach($fields as $field) {
								$values[$field->get('element_name')][] = 'section' . $section->get('id');
							}
						}
						
					}
				}
				
				// Generate list
				if(is_array($values)) {
					$filter = new XMLElement('ul', NULL, array('class' => 'tags inline'));
					foreach($values as $handle => $fields) {
						$filter->appendChild(new XMLElement('li', '{$' . $handle . '}', array('rel' => implode(' ', $fields))));
					}
					$container->appendChild($filter);
				}
				
			}
			
			return $container;
		}

		/**
		 * @see http://symphony-cms.com/learn/api/2.2/toolkit/field/#checkFields
		 */
		function checkFields(&$errors, $checkForDuplicates=true) {

			if(!is_array($errors)) $errors = array();

			// Check if a related section has been selected
			if($this->get('subsection_id') == '') {
				$errors['subsection_id'] = __('This is a required field.');
			}

			// Check if caption content is well formed
			if($this->get('caption')) {
				try {
					simplexml_load_string('<li>' . $this->get('caption') . '</li>');
				}
				catch(Exception $e) {
					$errors['caption'] = __('%s has to be well-formed. Please check opening and closing tags.', array(__('Caption')));
				}
			}

			// Check if droptext content is well formed
			if($this->get('droptext')) {
				try {
					simplexml_load_string('<li>' . $this->get('droptext') . '</li>');
				}
				catch(Exception $e) {
					$errors['droptext'] = __('%s has to be well-formed. Please check opening and closing tags.', array(__('Drop text')));
				}
			}

			parent::checkFields($errors, $checkForDuplicates);
		}

		/**
		 * @see http://symphony-cms.com/learn/api/2.2/toolkit/field/#commit
		 */
		function commit() {

			// Prepare commit
			if(!parent::commit()) return false;
			$id = $this->get('id');
			if($id === false) return false;

			// Set up fields
			$fields = array();
			$fields['field_id'] = $id;
			$fields['subsection_id'] = $this->get('subsection_id');
			$fields['allow_multiple'] = ($this->get('allow_multiple') ? 1 : 0);
			$fields['allow_quantities'] = ($this->get('allow_quantities') ? 1 : 0);
			$fields['show_preview'] = ($this->get('show_preview') ? 1 : 0);
			$fields['lock'] = ($this->get('lock') ? 1 : 0);

			// Save new stage settings for this field
			Stage::saveSettings($this->get('id'), $this->get('stage'), 'subsectionmanager');

			// Clean up filter values
			if($this->get('filter_tags') != '') {
				$tags = explode(",", $this->get('filter_tags'));
				foreach($tags as &$tag) {
					$tag = trim($this->cleanValue($tag));
					$list[] = $tag;
				}
				$fields['filter_tags'] = implode(', ', $list);
			}

			// Item caption
			$fields['caption'] = $this->get('caption');
			if($this->get('caption') == '') {
			
		  		// Fetch fields in subsection
				$subsection_fields = Symphony::Database()->fetch(
					"SELECT element_name, type
					FROM tbl_fields
					WHERE parent_section = '" . $this->get('subsection_id') . "'
					ORDER BY sortorder ASC
					LIMIT 10"
				);
				
				// Generate default caption
				$text = $file = '';
				foreach($subsection_fields as $subfield) {
					if($text != '' && $file != '') break;
					if(strpos($subfield['type'], 'upload') === false) {
						if($text == '') $text = '{$' . $subfield['element_name'] . '}';
					}
					else {
						if($file == '') $file = '{$' . $subfield['element_name'] . '}';				
					}
				}
				
				// Caption markup
				if($text != '' && $file != '') {
					$fields['caption'] = $text . '<br /> <em>' . $file . '</em>';
				}
				else {
					$fields['caption'] = $text . $file;
				}
								
			}
			
			// Drop text
			$fields['droptext'] = $this->get('droptext');

			// XML Output
			$fields['recursion_levels'] = intval($this->get('recursion_levels'));
			if(empty($fields['recursion_levels'])) $fields['recursion_levels'] = 0; // Make sure it is 0, not NULL

			// Data source fields - keep them stored for compatibility with 1.x
			$included_fields = $this->get('included_fields');
			if(is_array($included_fields)) $included_fields = implode(',', $included_fields);
			if(empty($included_fields)) $included_fields = NULL;
			$fields['included_fields'] = $included_fields;

			// Delete old field settings
			Symphony::Database()->query(
				"DELETE FROM `tbl_fields_".$this->handle()."` WHERE `field_id` = '$id' LIMIT 1"
			);

			// Save new field setting
			$settings = Symphony::Database()->insert($fields, 'tbl_fields_' . $this->handle());

			// Remove old secion association
			$this->removeSectionAssociation($id);

			// Save new section association
			$association = $this->createSectionAssociation(NULL, $this->get('subsection_id'), $id, $id, false);
			
			if($settings && $association) {
				return true;
			}
			else {
				return false;
			}
			
		}

		/**
		 * @see http://symphony-cms.com/learn/api/2.2/toolkit/field/#createSectionAssociation
		 */
		public function createSectionAssociation($parent_section_id = null, $child_section_id = null, $child_field_id = null, $parent_field_id = null, $show_association = false){

			if(is_null($parent_section_id) && is_null($child_section_id) && (is_null($parent_field_id) || !$parent_field_id)) return false;

			if(is_null($parent_section_id )) {
				$parent_section_id = Symphony::Database()->fetchVar('parent_section', 0,
					"SELECT `parent_section` FROM `tbl_fields` WHERE `id` = '$parent_field_id' LIMIT 1"
				);
			}

			$fields = array(
				'parent_section_id' => $parent_section_id,
				'parent_section_field_id' => $parent_field_id,
				'child_section_id' => $child_section_id,
				'child_section_field_id' => $child_field_id,
				'hide_association' => ($show_association ? 'no' : 'yes')
			);

			return Symphony::Database()->insert($fields, 'tbl_sections_association');
		}

		/**
		 * If you need to fetch the pure data this field returns, please use getDefaultPublishContent()
		 *
		 * @see http://symphony-cms.com/learn/api/2.2/toolkit/field/#displayPublishPanel
		 */
		function displayPublishPanel(&$wrapper, $data=NULL, $flagWithError=NULL, $fieldnamePrefix=NULL, $fieldnamePostfix=NULL) {
			if(!is_array($data['relation_id'])) $data['relation_id'] = array($data['relation_id']);
			if(!is_array($data['quantity'])) $data['quantity'] = array($data['quantity']);

			// Houston, we have problem: we've been called out of context!
			$callback = Administration::instance()->getPageCallback();
			if($callback['context']['page'] != 'edit' && $callback['context']['page'] != 'new') {
				$this->getDefaultPublishContent($wrapper, $data);
				return;
			}

			// Append styles
			Symphony::Engine()->Page->addStylesheetToHead(URL . '/extensions/subsectionmanager/lib/stage/stage.publish.css', 'screen', 101, false);
			Symphony::Engine()->Page->addStylesheetToHead(URL . '/extensions/subsectionmanager/assets/subsectionmanager.publish.css', 'screen', 102, false);

			// Append scripts
			Symphony::Engine()->Page->addScriptToHead(URL . '/extensions/subsectionmanager/lib/stage/stage.publish.js', 103, false);
			Symphony::Engine()->Page->addScriptToHead(URL . '/extensions/subsectionmanager/assets/subsectionmanager.publish.js', 104, false);
			Symphony::Engine()->Page->addScriptToHead(URL . '/extensions/subsectionmanager/lib/resize/jquery.ba-resize.js', 105, false);

			// Get Subsection
			$subsection = new SubsectionManager($this->_Parent);
			$content = $subsection->generate($this->get('id'), $this->get('subsection_id'), $data['relation_id'], false, $this->get('recursion_levels'));

			// Setup field name
			$fieldname = 'fields' . $fieldnamePrefix . '['. $this->get('element_name') . ']' . $fieldnamePostfix;

			// Setup storage values
			$label = Widget::Label($this->get('label'), $links);
			$label->appendChild(Widget::Input($fieldname . '[]', '', 'text', array('class' => 'subsectionmanager storage template')));

			$order = '';
			if(!empty($data['relation_id'])) {
				if($this->get('allow_quantities') == 1) {
					$counters = array_combine($data['relation_id'], $data['quantity']);
				}
				else {
					$counters = array_fill_keys($data['relation_id'], 1);
				}

				$order = implode(',', array_keys($counters));

				foreach($content['options'] as $option) {
					if(empty($option[1])) continue;

					$label->appendChild(Widget::Input($fieldname . '[' . $option[0] . ']', $counters[$option[0]], 'text', array('title' => $option[2], 'class' => 'subsectionmanager storage')));
				}
			}

			// Setup sorting
			// TODO: not needed anymore, but Stage may depend on it. Shouldn't Stage create one when needed?
			$input = Widget::Input('fields[sort_order][' . $this->get('id') . ']', $order, 'hidden');
			$label->appendChild($input);

			// Setup relation id
			$input = Widget::Input('fields[subsection_id][' . $this->get('id') . ']', $this->get('subsection_id'), 'hidden');
			$label->appendChild($input);
			$wrapper->appendChild($label);

			// Get stage settings
			$settings = ' ' . implode(' ', Stage::getComponents($this->get('id')));
			
			// Create stage
			$stage = new XMLElement('div', NULL, array('class' => 'stage' . $settings . ($this->get('show_preview') == 1 ? ' preview' : '') . ($this->get('allow_multiple') == 1 ? ' multiple' : ' single') . ($this->get('lock') == 1 ? ' locked' : '') . ($this->get('allow_quantities') ? ' nonunique' : ' unique')));
			$content['empty'] = '<li class="empty message"><span>' . __('There are no selected items') . '</span></li>';
			$selected = new XMLElement('ul', $content['empty'] . $content['html'], array('class' => 'selection'));
			$stage->appendChild($selected);
			
			// Append item template
			$thumb = '<img src="' . URL . '/extensions/subsectionmanager/assets/images/new.gif" width="40" height="40" class="thumb" />';
			$item = new XMLElement('li', $thumb . '<span>' . __('New item') . '<br /><em>' . __('Please fill out the form below.') . '</em></span><a class="destructor">&#215;</a>', array('class' => 'template create preview'));
			$selected->appendChild($item);
			
			// Append drawer template
			$subsection_handle = Symphony::Database()->fetchVar('handle', 0,
				"SELECT `handle`
				FROM `tbl_sections`
				WHERE `id` = '" . $this->get('subsection_id') . "'
				LIMIT 1"
			);
			$create_new = URL . '/symphony/publish/' . $subsection_handle;
			$item = new XMLElement('li', '<iframe name="subsection-' . $this->get('element_name') . '" src="about:blank" target="' . $create_new . '"  frameborder="0"></iframe>', array('class' => 'drawer template'));
			$selected->appendChild($item);

			// Error handling
			if($flagWithError != NULL) {
				$wrapper->appendChild(Widget::wrapFormElementWithError($stage, $flagWithError));
			}
			else {
				$wrapper->appendChild($stage);
			}

		}
		
		/**
		 * Get default publish content
		 */
		function getDefaultPublishContent(&$wrapper, $data = NULL) {
			
			// Get items
			$subsection = new SubsectionManager($this->_Parent);
			$content = $subsection->generate($this->get('id'), $this->get('subsection_id'), $data['relation_id'], true, $this->get('recursion_levels'));
			
			// Append items
			$select = Widget::Select(null, $content['options']);
			$wrapper->appendChild($select);
		}

		/**
		 * @see http://symphony-cms.com/learn/api/2.2/toolkit/field/#checkPostFieldData
		 */
		public function checkPostFieldData($data, &$message, $entry_id = null) {
			if(!is_array($data)) $data = array($data);
			$data = array_filter($data);

			$status = parent::checkPostFieldData($data, $message, $entry_id);
			if($status != self::__OK__) return $status;

			if(empty($data) || !is_array($data)) return self::__OK__;

			if($this->get('allow_multiple') == 0 && count($data) > 1) {
				$message = __("'%s' allows single item only.", array($this->get('label')));
				return self::__INVALID_FIELDS__;
			}

			if($this->get('allow_quantities') == 0) {
				foreach($data as $entry_id => $quantity) {
					if($quantity > 1) {
						$message = __("'%s' allows only unique values.", array($this->get('label')));
						return self::__INVALID_FIELDS__;
					}
				}
			}

			return self::__OK__;
		}

		/**
		 * @see http://symphony-cms.com/learn/api/2.2/toolkit/field/#processRawFieldData
		 */
		function processRawFieldData($data, &$status, $simulate=false, $entry_id=NULL) {
			$status = self::__OK__;
			if(!is_array($data) && !is_null($data)) return array('relation_id' => $data);
			if(empty($data)) return NULL;

			$result = array();
			$maxQuantity = ($this->get('allow_quantities') == 0 ? 1 : 4294967295); // Maximum value of MySQL's unsigned INT type.
			foreach($data as $entry_id => $quantity) {
				if(empty($entry_id) || empty($quantity) || $quantity > $maxQuantity) continue;
				$result['relation_id'][] = intval($entry_id);
				$result['quantity'][] = intval($quantity);
			}

			return $result;
		}

		/**
		 * @see http://symphony-cms.com/learn/api/2.2/toolkit/field/#createTable
		 */
		function createTable(){
			return Symphony::Database()->query(
				"CREATE TABLE IF NOT EXISTS `tbl_entries_data_" . $this->get('id') . "` (
				  `id` int(11) unsigned NOT NULL auto_increment,
				  `entry_id` int(11) unsigned NOT NULL,
				  `relation_id` int(11) unsigned DEFAULT NULL,
				  `quantity` int(11) unsigned DEFAULT '1',
				  PRIMARY KEY (`id`),
				  KEY `entry_id` (`entry_id`),
				  KEY `relation_id` (`relation_id`)
				) TYPE=MyISAM;"
			);
		}

		/**
		 * @see http://symphony-cms.com/learn/api/2.2/toolkit/field/#prepareTableValue
		 */
		function prepareTableValue($data, XMLElement $link=NULL) {
			if(empty($data['relation_id'])) return NULL;

			// Single select
			if($this->get('allow_multiple') == 0 || count($data['relation_id']) === 1) {
				$subsection = new SubsectionManager($this->_Parent);
				$content = $subsection->generate($this->get('id'), $this->get('subsection_id'), $data['relation_id'], false, $this->get('recursion_levels'));
				
				// Link?
				if($link) {
					$href = $link->getAttribute('href');
					$item = '<a href="' . $href . '">' . $content['preview'] . '</a>';
				}
				else {
					$item = $content['preview'];
				}
				
				return '<div class="subsectionmanager">' . $item . '</div>';
			}
						
			// Multiple select
			else {
				$count = count($data['relation_id']);
				return parent::prepareTableValue(array('value' => ($count > 1) ? $count . ' ' . __('items') : $count . ' ' . __('item')), $link);
			}
		}
		
		/**
		 * @see http://symphony-cms.com/learn/api/2.2/toolkit/field/#fetchIncludableElements
		 */
		public function fetchIncludableElements($break = false) {
			static $done = array();

			$id = $this->get('parent_section');
			if($done[$id] >= $this->get('recursion_levels') + 1) return array();	
			$done[$id] += 1;

			$includable = array();


				// Fetch subsection fields
				$sectionManager = new SectionManager(Symphony::Engine());
				$section = $sectionManager->fetch($this->get('subsection_id'));
				$fields = $section->fetchFields();
				
				foreach($fields as $field) {
					$field_id = $field->get('id');

						$elements = $field->fetchIncludableElements(true);
					
					foreach($elements as $element) {
						$includable[] = $this->get('element_name') . ': ' . $element;
					}
				}

			$done[$id] -= 1;
			return $includable;
		}
		
		/**
		 * @see http://symphony-cms.com/learn/api/2.2/toolkit/field/#buildDSRetrivalSQL
		 */
		public function buildDSRetrivalSQL($data, &$joins, &$where, $andOperation=false) {

		    // Current field id
		    $field_id = $this->get('id');

		    // Filters connected with AND
		    if($andOperation) {
		        foreach($data as $key => $value) {
		            $joins .= " LEFT JOIN `tbl_entries_data_$field_id` AS `t$field_id$key` ON (`e`.`id` = `t$field_id$key`.entry_id) ";
		            $where .= " AND `t$field_id$key`.relation_id = '$value' ";
		        }
		    }

		    // Filters connected with OR
		    else {
		        $joins .= " LEFT JOIN `tbl_entries_data_$field_id` AS `t$field_id` ON (`e`.`id` = `t$field_id`.entry_id) ";
		        $where .= " AND `t$field_id`.relation_id IN ('" . @implode("', '", $data) . "') ";
		    }

		    return true;
		}

		/**
		 * Subsection entries are pre-processed in the extension driver and stored in 
		 * extension_subsectionmanager::$storage with other helpful data. If you are building 
		 * custom data sources, please use extension_subsectionmanager::storeSubsectionFields() 
		 * to store subsection fields and extension_subsectionmanager::preloadSubsectionEntries() 
		 * to preload subsection entries.
		 *
		 * @see http://symphony-cms.com/learn/api/2.2/toolkit/field/#appendFormattedElement
		 * @todo Sorting should be handled via system id
		 */
		public function appendFormattedElement(&$wrapper, $data, $encode = false, $mode = null) {
			static $done = array();

			// Unify data
			if(empty($data['relation_id'])) $data['relation_id'] = array();
			if(!is_array($data['relation_id'])) $data['relation_id'] = array($data['relation_id']);

			// Create subsection element
			$entryManager = new EntryManager(Symphony::Engine());
			$subsection = new XMLElement($this->get('element_name'));
			$subsection->setAttribute('field-id', $this->get('id'));
			$subsection->setAttribute('subsection-id', $this->get('subsection_id'));

			// Generate output
			foreach($data['relation_id'] as $index => $entry_id) {

				// Populate entry element
				$entry = extension_subsectionmanager::$storage['entries'][$entry_id];
				
				// Fetch missing entries
				if(empty($entry)) {
					$entry = $entryManager->fetch($entry_id, $this->get('subsection_id'));
					
					// Store entry
					$entry = $entry[0];
					extension_subsectionmanager::$storage['entries'][$entry_id] = $entry;
				}

				if(empty($entry)) {
					// TODO: Looks like related entry was deleted and data was not removed.
					//       Should SubsectionManager gather deleted IDs and remove them from DB at exit?
					//       Or register delegate to always delete them when entry is deleted?
					continue;
				}

				// Create item
				$item = new XMLElement('item', NULL, array('id' => $entry_id, 'quantity' => $data['quantity'][$index]));
				$subsection->appendChild($item);
				
				// Process entry for Data Source
				if(!empty(extension_subsectionmanager::$storage['fields'][$mode][$this->get('id')])) {
					foreach(extension_subsectionmanager::$storage['fields'][$mode][$this->get('id')] as $field_id => $modes) {
						$entry_data = $entry->getData($field_id);
						$field = $entryManager->fieldManager->fetch($field_id);
						
						// No modes
						if(empty($modes)) {
							$field->appendFormattedElement($item, $entry_data, $encode, $mode, $entry_id);
						}
						
						// With modes
						else {
							foreach($modes as $m) {
								$field->appendFormattedElement($item, $entry_data, $encode, $m, $entry_id);
							}
						}						
					}
				}

				// Process entry for anyone else
				else {
					$engine = Symphony::Engine();
					if($engine instanceof Administration) {
					
						// Check for recursion first
						$id = $this->get('parent_section');
						if($done[$id] >= $this->get('recursion_levels') + 1) return array();	
						$done[$id] += 1;
					
						// Now output data
						$callback = Administration::instance()->getPageCallback();
						if($callback['context']['page'] == 'edit' || $callback['context']['page'] != 'new') {
							static $fieldManager = NULL;
							if(empty($fieldManager)) {
								$fieldManager = new FieldManager(Symphony::Engine());
								if(empty($fieldManager)) return;
							}

							$data = $entry->getData();
							
							// Add fields:
							foreach($data as $field_id => $values) {
								if(empty($field_id)) continue;
				
								$field = $fieldManager->fetch($field_id);
								$field->appendFormattedElement($item, $values, false, null, $entry_id);
							}
						}

						$done[$id] -= 1;
					}
				}
			}
			
			// Append subsection
			$subsection->setAttribute('items', count($data['relation_id']));
			$wrapper->appendChild($subsection);
		}

		/**
		 * @see http://symphony-cms.com/learn/api/2.2/toolkit/field/#fetchAssociatedEntrySearchValue
		 *
		 * `$data` would contain the related entries, but is usually `null` when called from the frontend
		 * (when the field is not included in the DS, and only then "associated entry count" makes sense)
		 */
		public function fetchAssociatedEntrySearchValue($data, $field_id = null, $parent_entry_id = null){
			if(!is_null($parent_entry_id)) {
				return $parent_entry_id;
			}
		}

		/**
		 * @see http://symphony-cms.com/learn/api/2.2/toolkit/field/#fetchAssociatedEntryCount
		 */
		public function fetchAssociatedEntryCount($value){
			if(isset($value)) {
				return Symphony::Database()->fetchVar('count', 0, "SELECT count(*) AS `count` FROM `tbl_entries_data_".$this->get('id')."` WHERE `entry_id` = '".intval($value)."'");
			} 
			else {
				return 0;
			}
		}
		
		/**
		 * @see http://symphony-cms.com/learn/api/2.2/toolkit/field/#getParameterPoolValue
		 */
		public function getParameterPoolValue($data) {
			if(is_array($data['relation_id'])) {
				return implode(", ", $data['relation_id']);
			}
			else {
				return $data['relation_id'];
			}
		}

		/**
		 * @see http://symphony-cms.com/learn/api/2.2/toolkit/field/#displayDatasourceFilterPanel
		 */
		function displayDatasourceFilterPanel(&$wrapper, $data=NULL, $errors=NULL, $fieldnamePrefix=NULL, $fieldnamePostfix=NULL) {

			parent::displayDatasourceFilterPanel($wrapper, $data, $errors, $fieldnamePrefix, $fieldnamePostfix);
			$text = new XMLElement('p', __('Use comma separated entry ids for filtering.'), array('class' => 'help') );
			$wrapper->appendChild($text);

		}

		/**
		 * @see http://symphony-cms.com/learn/api/2.2/toolkit/field/#getExampleFormMarkup
		 */
		public function getExampleFormMarkup() {
			return Widget::Select('fields['.$this->get('element_name').']', array(array('...')));
		}

	}
