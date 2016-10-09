<?php

/**
 * H3_MGMT_Admin_Table class.
 *
 * This class contains properties and methods
 * to display tabular data in the administrative backend
 *
 * @package Hitchhiking Hub Management
 * @since 1.1
 */

if ( ! class_exists( 'H3_MGMT_Admin_Table' ) ) :

class H3_MGMT_Admin_Table {

	/**
	 * Class Properties
	 *
	 * @since 1.1
	 */
	public $default_args = array(
		'echo' => true,
		'orderby' => 'name',
		'order' => 'ASC',
		'toggle_order' => 'DESC',
		'page_slug' => 'h3-mgmt-races',
		'base_url' => '',
		'sort_url' => '',
		'profile_url' => '',
		'pagination_url' => '',
		'with_wrap' => false,
		'icon' => 'icon-race',
		'headline' => 'Data Table',
		'data_name' => NULL,
		'headspace' => false,
		'show_empty_message' => true,
		'empty_message' => '',
		'pagination' => false,
		'prev_text' => '&laquo; Previous',
		'next_text' => 'Next &raquo;',
		'total_pages' => 1,
		'current_page' => 1,
		'end_size' => 1,
		'mid_size' => 2,
		'dspl_cnt' => false,
		'count' => 0,
		'cnt_txt' => '',
		'with_bulk' => false,
		'bulk_btn' => 'Execute',
		'bulk_confirm' => '',
		'bulk_name' => 'bulk',
		'bulk_param' => 'todo',
		'bulk_desc' => '',
		'extra_bulk_html' => '',
		'bulk_actions' => array(),
		'filter' => array(),
		'filter_dis_name' => array(),
		'filter_conversion' => array(),
		'pre_filtered' => array(false)
	);
	private $set_args = array();
	public $args = array();
	public $columns = array();
	public $rows = array();

	/**
	 * PHP4 style constructor
	 *
	 * @since 1.1
	 * @access public
	 */
	public function H3_MGMT_Admin_Table( $args, $columns, $rows ) {
		$this->__construct( $args, $columns, $rows );
	}

	/**
	 * PHP5 style constructor
	 *
	 * @since 1.1
	 * @access public
	 */
	public function __construct( $args, $columns, $rows ) {

		$this->default_args['prev_text'] = __( '&laquo; Previous', 'h3-mgmt' );
		$this->default_args['next_text'] = __( 'Next &raquo;', 'h3-mgmt' );
		$this->default_args['bulk_btn'] = __( 'Execute', 'h3-mgmt' );

		$this->set_args = $args;
		$this->args = wp_parse_args( $args, $this->default_args );

		if ( isset( $_GET['orderby'] ) ) {
			$this->args['orderby'] = $_GET['orderby'];
		}
		if ( isset( $_GET['order'] ) && ( 'ASC' === $_GET['order'] || 'DESC' === $_GET['order'] ) ) {
			$order = $_GET['order'];
			if( 'ASC' === $order ) {
				$toggle_order = 'DESC';
			} else {
				$toggle_order = 'ASC';
			}
			$this->args['order'] = $order;
			$this->args['toggle_order'] = $toggle_order;
		}

		$this->args['base_url'] = ! empty( $this->args['base_url'] ) ? $this->args['base_url'] : 'admin.php?page='.$this->args['page_slug'];
		$this->args['sort_url'] = ! empty( $this->args['sort_url'] ) ? $this->args['sort_url'] : $this->args['base_url'];
		$this->args['profile_url'] = ! empty( $this->args['profile_url'] ) ? $this->args['profile_url'] :
			$this->args['sort_url'] . '&orderby=' . $this->args['orderby'] . '&order=' . $this->args['order'];
		$this->args['pagination_url'] = ! empty( $this->args['pagination_url'] ) ? $this->args['pagination_url'] :
			str_replace( '{', '%lcurl%',
				str_replace( '}', '%rcurl%',
					str_replace( ':', '%colon%',
						$this->args['sort_url']
					)
				)
			) .
			'&orderby=' . $this->args['orderby'] . '&order=' . $this->args['order'] . '%_%';

		$this->columns = $columns;
		$this->rows = $rows;
	}

	/**
	 * Constructs the table HTML,
	 * echoes or returns it
	 *
	 * @since 1.1
	 * @access public
	 */
	public function output() {
		global $current_user, $h3_mgmt_admin;
		get_currentuserinfo();

		extract( $this->args, EXTR_SKIP );
		$columns = $this->columns;
		$rows = $this->rows;
		$filter  = $this->set_args['filter'];
		
		$rows = $this->get_filtered_rows( $filter, $rows );

		if( $this->set_args['pre_filtered'][0] === true && !isset( $_GET['filter_name0'] ) && is_array( $rows ) && !empty( $rows ) ){
			foreach( $rows as $row_is ) {
				if( stripslashes( $this->set_args['pre_filtered'][2] == $row_is[$this->set_args['pre_filtered'][1]] ) ) {
					$rows_new[] = $row_is;
				}
			} 
			if( is_array( $rows_new ) && !empty( $rows_new ) ){
			$rows = $rows_new;
			}
		}
			
		$count = count($rows);
		
		$output = '';

		if ( $with_wrap ) {
			$output .= '<div class="wrap">' .
				'<div id="' . $icon . '" class="icon32"><br></div>' .
				'<h2>' . $headline . '</h2><br />';
		}

		if ( $headspace ) {
			$output .= '<br />';
		}

		if (
			! $with_wrap &&
			isset( $this->set_args['headline'] ) &&
			! empty( $this->set_args['headline'] ) &&
			(
				$with_bulk ||
				( ! $with_bulk && ! $dspl_cnt && ! $pagination )
			)
		) {
			$output .= '<h3 class="table-headline">';
			if ( isset( $this->set_args['icon'] ) && ! empty( $this->set_args['icon'] ) ) {
				$output .= '<div class="tbl-icon nt-' . $icon . '"></div>';
			}
			$output .= $headline . '</h3>';
		}
		
		if( !empty($filter) ){
			$filter_id = 0;

			$cat = array();
		
			$output.='	<div class="alignleft actions extra1">
						<form name="filter_form" method="get" action="">
						<input type="hidden" name="page" value="'.$_GET['page'].'" />';
			foreach($filter as $filter_is){
				$output.='	<input type="hidden" name="filter_name'.$filter_id.'" value="'.$filter_is.'" />';
				
				$cat[$filter_is][] = dummy1;
				$cat[$filter_is][] = dummy2;
				if( is_array( $rows ) && !empty( $rows ) ){
					foreach( $rows as $row_is ) {
						if( !in_array($row_is[$filter_is], $cat[$filter_is]) && $row_is[$filter_is] != '') {
							$cat[$filter_is][] = $row_is[$filter_is];
						}
					} 
				}
				$cat[$filter_is] = array_slice($cat[$filter_is], 2);	
				sort($cat[$filter_is]);
				
				$output.='	<div class="alignleft actions extra1" style="padding:0px 5px 0px 0px;">
							<span>'.$this->set_args['filter_dis_name'][$filter_id] .'</span><br>
							<select name="filter_value'.$filter_id.'" id="filter_value">';
							if( isset( $_GET['filter_value'.$filter_id] ) && $_GET['filter_value'.$filter_id] != '-1' && $_GET['filter_value'.$filter_id] != 'all'){
								if( ! isset( $this->set_args['filter_conversion'][$filter_id] ) ) {
									$val_show = stripslashes( $_GET['filter_value'.$filter_id]);
								} else {
									$val_show = $this->convert_data( stripslashes( $_GET['filter_value'.$filter_id]), $this->set_args['filter_conversion'][$filter_id], $row );
								}
								$output.='	<option value="'.stripslashes( $_GET['filter_value'.$filter_id]).'" >'.$val_show.'</option>';
							}else{
								$output.='	<option value="all" >all</option>';
							}
							
							if( isset( $_GET['filter_value'.$filter_id] ) && $_GET['filter_value'.$filter_id] != 'all' ){
								$output.='	<option value="all">show all</option>';
							}
						
							foreach($cat[$filter_is] as $cat_is){
								if( ! isset( $this->set_args['filter_conversion'][$filter_id] ) ) {
									$cat_is_show = $cat_is;
								} else {
									$cat_is_show = $this->convert_data( $cat_is, $this->set_args['filter_conversion'][$filter_id], $row );
								}
								
								if(stripslashes( $_GET['filter_value'.$filter_id]) != $cat_is){
									if(  $cat_is == $this->set_args['pre_filtered'][2] && !isset( $_GET['filter_name0'] ) && $this->set_args['pre_filtered'][1] == $this->set_args['filter'][$filter_id] ){
										$output.='<option selected value="'.$cat_is.'">'.$cat_is_show.'</option>';										
									}else{
										$output.='<option value="'.$cat_is.'">'.$cat_is_show.'</option>';
									}
								}
							};
				$output.='	</select>
							</div>';						
				$filter_id = $filter_id + 1;
			}
			$output.= '	<div class="alignleft actions extra1">
						<span>   </span><br>
						<input type="submit" id="filter" class="button action" value="filter">
						</div>
						</form>
						</div> <br style="clear:left;">';
		}
							
		if ( empty( $rows ) ) {

			if ( $show_empty_message ) {
				if ( ! empty( $empty_message ) ) {
					$message = $empty_message;
				} elseif ( ! empty( $data_name ) ) {
					$message = sprintf( __( 'Currently there is no data of type &quot;%s&quot;...', 'h3-mgmt' ), $data_name );
				} else {
					$message = __( 'Currently there is no data to be displayed here...', 'h3-mgmt' );
				}

				$output .= $h3_mgmt_admin->convert_messages( array ( array (
					'type' => 'message',
					'message' => $message
				) ) );
			}

		} else {

			/* BEGIN: Table Header */

			$table_head = '<tr>';

			if ( $with_bulk && is_array( $bulk_actions ) && ! empty( $bulk_actions ) ) {
				$table_head .= '<th id="bulk" class="manage-column column-bulk check-column" style="" scope="col">'.
						'<input type="checkbox">' .
					'</th>';
			}

			foreach( $columns as $column ) {

				$table_head .= '<th id="' . $column['id'] . '" class="manage-column column-' . $column['id'];

				/* classes to be used in css media queries */
				if( isset( $column['legacy-screen'] ) && false === $column['legacy-screen'] ) {
					$table_head .= ' legacy-screen-hide-pa';
				} elseif( isset( $column['tablet'] ) && false === $column['tablet'] ) {
					$table_head .= ' tablet-hide-pa';
				} elseif( isset( $column['mobile'] ) && false === $column['mobile'] ) {
					$table_head .= ' mobile-hide-pa';
				} elseif( isset( $column['legacy-mobile'] ) && false === $column['legacy-mobile'] ) {
					$table_head .= ' legacy-mobile-hide-pa';
				}

				/* is the table sortable via the data in this column? */
				if( isset( $column['sortable'] ) && true === $column['sortable'] ) {
					/* default initial sorting */
					if( $column['id'] !== $orderby ) {
						$col_order = 'DESC';
						$col_toggle_order = 'ASC';
					/* sorting if this is the column currently sorted by */
					} else {
						$col_order = $order;
						$col_toggle_order = $toggle_order;
					}
					$table_head .= ' sortable ' . strtolower( $col_order );
				}

				$table_head .= '" style="" scope="col">';
				
				$actual_link = "http://$_SERVER[HTTP_HOST]$_SERVER[REQUEST_URI]";
				
				if( isset( $column['sortable'] ) && true === $column['sortable'] ) {
					if( isset( $_GET['orderby'] ) ){
						$table_head .= '<a href="' .
								str_replace($_GET['order'], $col_toggle_order, str_replace($_GET['orderby'], $column['id'], $actual_link)) .
							'">';
					}else{
						$table_head .= '<a href="' .
								// get_site_url() . '/wp-admin/' . //get_option( 'site_url' ) . '/wp-admin/' .
								// $sort_url . '&amp;orderby=' . $column['id'] . '&amp;order=' . $col_toggle_order .
								$actual_link . '&amp;orderby=' . $column['id'] . '&amp;order=' . $col_toggle_order .
							'">';
					}
				}

				$table_head .= '<span>' . $column['title'] . '</span>';

				if( isset( $column['sortable'] ) && true === $column['sortable'] ) {
					$table_head .= '<span class="sorting-indicator"></span></a>';
				}

				$table_head .= '</th>';
			}

			$table_head .= '</tr>';

			/* END: Table Header | BEGIN: Output */

			if( $with_bulk && is_array( $bulk_actions ) && ! empty( $bulk_actions ) ) {
				$output .= '<form action="" class="blk-action-form" method="get">' .
					'<input type="hidden" name="page" value="' . $page_slug . '" />';
				if ( ! empty( $extra_bulk_html ) ) {
					$output .= $extra_bulk_html;
				}
			}

			if(
				( $with_bulk && is_array( $bulk_actions ) && ! empty( $bulk_actions ) ) ||
				$dspl_cnt ||
				$pagination
			) {
				$output .= '<div class="tablenav top">';
			}

			if( $with_bulk && is_array( $bulk_actions ) && ! empty( $bulk_actions ) ) {
				$output .= '<div class="alignleft actions">';
				if ( ! empty( $bulk_desc ) ) {
					$output .= '<span class="desc">' . $bulk_desc . ':&nbsp;</span>';
				}
				if ( 1 < count( $bulk_actions ) ) {
					$output .= '<select name="' . $bulk_param . '" id="' . $bulk_param . '" class="bulk-action simul-select">';
					foreach ( $bulk_actions as $bulk_action ) {
						$output .= '<option value="' . $bulk_action['value'] . '">' . $bulk_action['label'] . '&nbsp;</option>';
					}
					$output .= '</select>';
				} else {
					$bulk_action = $bulk_actions[0];
					$output .= '<input type="hidden" name="' . $bulk_param . '" value="' . $bulk_action['value'] . '" />';
				}
				$output .= '<input type="submit" name="" id="bulk-action-submit" class="button-secondary do-bulk-action" value="' .
						$bulk_btn . '"';
				if ( ! empty( $bulk_confirm ) ) {
					$output .= ' cc="ccc"';// onclick="if ( confirm(\'' . $bulk_confirm . '\') ) { return true; } return false;"';
				}
				$output .= '/></div>';
			} elseif (
				! $with_wrap &&
				isset( $this->set_args['headline'] ) &&
				! empty( $this->set_args['headline'] ) &&
				( $dspl_cnt || $pagination )
			) {
				$output .= '<div class="alignleft"><h3 class="table-headline">';
				if ( isset( $this->set_args['icon'] ) && ! empty( $this->set_args['icon'] ) ) {
					$output .= '<div class="tbl-icon nt-' . $icon . '"></div>';
				}
				$output .= $headline . '</h3></div>';
			}

			if ( $dspl_cnt || $pagination ) {
				$output .= '<div class="tablenav-pages">';
				if ( $dspl_cnt ) {
					$output .= '<span class="displaying-num">' . sprintf( $cnt_txt, $count ) . '</span>';
				}
				if( $pagination ) {
					$pagination_html = paginate_links( array(
						'base' => $pagination_url,
						'format' => '&p=%#%#tbl',
						'prev_text' => $prev_text,
						'next_text' => $next_text,
						'total' => $total_pages,
						'current' => $current_page,
						'end_size' => $end_size,
						'mid_size' => $mid_size,
					));
					$pagination_html = str_replace( '%colon%', ':', str_replace( '%lcurl%', '{', str_replace( '%rcurl%', '}', $pagination_html ) ) );
					$output .= '<span class="pagination-links">' . $pagination_html . '</span>';
				}
				$output .= '</div>';
			}

			if(
				( $with_bulk && is_array( $bulk_actions ) && ! empty( $bulk_actions ) ) ||
				$dspl_cnt ||
				$pagination
			) {
				$output .= '</div>';
			}


			$output .= '<table class="wp-list widefat fixed" cellspacing="0">' .
				'<thead>'. $table_head . '</thead>'.
				'<tfoot>'. $table_head . '</tfoot>'.
				'<tbody>';

			/* BEGIN: Rows */

			$bulk_id = 0;
			foreach ( $rows as $row ) {

				$output .= '<tr valign="middle" class="alternate">';
				if ( $with_bulk && is_array( $bulk_actions ) && ! empty( $bulk_actions ) ) {
					$bulk_val = isset( $row['bulk'] ) ? $row['bulk'] : ( isset( $row['id'] ) ? $row['id'] : 0 );
					$output .= '<th class="check-column" scope="row">' .
							// '<input type="checkbox" name="' . $bulk_name . '" value="' . $bulk_val . '">' .
							'<input type="checkbox" name="' . $bulk_name . '['.$bulk_id.']" value="' . $bulk_val . '">' .
						'</th>';
					$bulk_id = $bulk_id + 1;
				}

				foreach ( $columns as $column ) {
					$capable = false;
					if (
						empty( $column['cap'] ) ||
						false
					) {
						$capable = true;
					}

					$output .= '<td class="column-' . $column['id'];
					if( isset( $column['legacy-screen'] ) && false === $column['legacy-screen'] ) {
						$output .= ' legacy-screen-hide-pa';
					} elseif( isset( $column['tablet'] ) && false === $column['tablet'] ) {
						$output .= ' tablet-hide-pa';
					} elseif( isset( $column['mobile'] ) && false === $column['mobile'] ) {
						$output .= ' mobile-hide-pa';
					} elseif( isset( $column['legacy-mobile'] ) && false === $column['legacy-mobile'] ) {
						$output .= ' legacy-mobile-hide-pa';
					}
					$output .= '">';

					if( isset( $column['strong'] ) && true === $column['strong'] ) {
						$output .= '<strong>';
					}

					if( ! empty( $column['link'] ) && $capable ) {
						$title = empty( $column['link']['title_row_data'] ) ? $column['link']['title'] : sprintf( $column['link']['title'], $row[$column['link']['title_row_data']] );
						$url = empty( $column['link']['url_row_data'] ) ? $column['link']['url'] : sprintf( $column['link']['url'], $row[$column['link']['url_row_data']] );
						$output .= '<a title="' . $title . '" href="' . $url . '">';
					}

					if( ! isset( $column['conversion'] ) ) {
						$output .= $row[$column['id']];
					} else {
						$output .= $this->convert_data( $row[$column['id']], $column['conversion'], $row );
					}

					if( ! empty( $column['link'] ) && $capable ) {
						$output .= '</a>';
					}

					if( isset( $column['strong'] ) && true === $column['strong'] ) {
						$output .= '</strong>';
					}

					if ( ! empty( $column['actions'] ) ) {
						$cap = ! empty( $column['cap'] ) ? $column['cap'] : '';
						$output .= $this->actions( $column['actions'], $row, $column, $cap );
					}

					$output .= '</td>';
				}

				$output .= '</tr>';
			}

			/* END: Rows */

			$output .= '</tbody></table>';

			if(
				( $with_bulk && is_array( $bulk_actions ) && ! empty( $bulk_actions ) ) ||
				$dspl_cnt ||
				$pagination
			) {
				$output .= '<div class="tablenav bottom">';
			}

			if( $with_bulk && is_array( $bulk_actions ) && ! empty( $bulk_actions ) ) {
				$output .= '<div class="alignleft actions no-js-hide">';
				if ( ! empty( $bulk_desc ) ) {
					$output .= '<span class="desc">' . $bulk_desc . ':&nbsp;</span>';
				}
				if ( 1 < count( $bulk_actions ) ) {
					$output .= '<select name="' . $bulk_param . '" id="' . $bulk_param . '" class="bulk-action simul-select">';
					foreach ( $bulk_actions as $bulk_action ) {
						$output .= '<option value="' . $bulk_action['value'] . '">' . $bulk_action['label'] . '&nbsp;</option>';
					}
					$output .= '</select>';
				} else {
					$bulk_action = $bulk_actions[0];
					$output .= '<input type="hidden" name="' . $bulk_param . '" value="' . $bulk_action['value'] . '" />';
				}
				$output .= '<input type="submit" name="" id="bulk-action-submit" class="button-secondary do-bulk-action" value="' .
						$bulk_btn . '"';
				if ( ! empty( $bulk_confirm ) ) {
					$output .= ' onclick="if ( confirm(\'' . $bulk_confirm . '\') ) { return true; } return false;"';
				}
				$output .= '/></div>';
			}

			if ( $dspl_cnt || $pagination ) {
				$output .= '<div class="tablenav-pages">';
				if ( $dspl_cnt ) {
					$output .= '<span class="displaying-num">' . sprintf( $cnt_txt, $count ) . '</span>';
				}
				if( $pagination ) {
					$output .= '<span class="pagination-links">' . $pagination_html . '</span>';
				}
				$output .= '</div>';
			}

			if(
				( $with_bulk && is_array( $bulk_actions ) && ! empty( $bulk_actions ) ) ||
				$dspl_cnt ||
				$pagination
			) {
				$output .= '</div>';
			}

			if( $with_bulk && is_array( $bulk_actions ) && ! empty( $bulk_actions ) ) {
				$output .= '</form>';
			}
		}

		if ( $with_wrap ) {
			$output .= '</div>';
		}

		if ( ! $echo ) {
			return $output;
		} else {
			echo $output;
		}
	}

	/**
	 * Is invoked if a column has action links associated with it
	 *
	 * @todo make more dynamic
	 *
	 * @since 1.1
	 * @access private
	 */
	private function actions( $actions, $row, $column, $cap = '' ) {
		global $current_user;
		get_currentuserinfo();

		$output = '<br /><div class="row-actions">';

		$url = $this->args['base_url'];

		if( ! empty( $row[$column['id']] ) ) {
			$name = $row[$column['id']];
		} elseif( ! empty( $row['name'] ) ) {
			$name = $row['name'];
		} elseif( ! empty( $row['destination'] ) ) {
			$name = $row['destination'];
		} else {
			$name = __( 'this event', 'h3-mgmt' );
		}

		$action_count = count( $actions );
		$flipper = true;

		for ( $i = 0; $i < $action_count; $i++ ) {

			$cur_cap = '';
			if ( ! empty( $cap ) ) {
				if ( is_array( $cap ) ) {
					$cap_cnt = count( $cap );
					if ( ( $cap_cnt - 1 ) >= $i ) {
						$cur_cap = $cap[$i];
					} else {
						$cur_cap = $cap[$cap_cnt-1];
					}
				} else {
					$cur_cap = $cap;
				}
			}

			if (
				empty( $cur_cap ) ||
				(
					( 'race' === $cur_cap || 'races' === $cur_cap ) &&
					(
						$current_user->has_cap( 'h3_mgmt_edit_races' )
					)
				) ||
				(
					( 'team' === $cur_cap || 'teams' === $cur_cap ) &&
					(
						$current_user->has_cap( 'h3_mgmt_edit_teams' )
					)
				) ||
				(
					( 'sponsor' === $cur_cap || 'sponsors' === $cur_cap ) &&
					(
						$current_user->has_cap( 'h3_mgmt_edit_sponsors' )
					)
				)
			) {
				switch( $actions[$i] ) {
					case 'edit':
						$output .= '<span class="edit">' .
							'<a title="' .
								sprintf( __( 'Edit %s', 'h3-mgmt' ), $name ) .
								'" href="' . $url . '&amp;todo=edit&amp;id=' . $row['id'] . '">' .
								__( 'Edit', 'h3-mgmt' ) .
							'</a></span>';
					break;

					case 'delete':
					case 'delete-user':
						$output .= '<span class="delete">' .
							'<a title="' .
								sprintf( __( 'Delete %s', 'h3-mgmt' ), $name ) .
							'" onclick="if ( confirm(\'' .
									ucfirst( sprintf( __( 'Really delete &quot;%s&quot;?', 'h3-mgmt' ), $name ) ) .
								'\') ) { return true; } return false;" ' .
								'href="' . $url . '&amp;todo=delete&amp;id=' .
								$row['id'] . '" class="submitdelete">' .
								__( 'Delete', 'h3-mgmt' ) .
							'</a></span>';
					break;

					case 'waiver':
						$output .= '<span class="edit"><a title="' .
								__( "This participant's waiver form has been received", 'h3-mgmt' ) .
								'" href="' . $url . '&amp;todo=waiver-set&amp;id=' . $row['id'] . '">' .
								__( 'Waiver received!', 'h3-mgmt' ) .
							'</a></span> | ' .
							'<span class="delete">' .
								'<a title="' .
									__( 'Ooops, that was too quick. The waiver actually has not been received yet...', 'h3-mgmt' ) .
								'" onclick="if ( confirm(\'' .
										__( 'Really reset the waiver status of this participant to &quot;not received&quot;??', 'h3-mgmt' ) .
									'\') ) { return true; } return false;" ' .
									'href="' . $url . '&amp;todo=waiver-unset&amp;id=' .
									$row['id'] . '" class="submitdelete">' .
									__( 'Not yet received...', 'h3-mgmt' ) .
								'</a>' .
							'</span>';
					break;

					case 'liveticker':
						$output .= '<span class="edit"><a title="' .
								__( "Enable the Liveticker function for the race!", 'h3-mgmt' ) .
								'" href="' . $url . '&amp;todo=liveticker-set&amp;id=' . $row['id'] . '">' .
								__( 'Enable Liveticker!', 'h3-mgmt' ) .
							'</a></span> | ' .
							'<span class="delete">' .
								'<a title="' .
									__( 'Disable the Liveticker function for the race!', 'h3-mgmt' ) .
								'" onclick="if ( confirm(\'' .
										__( 'Really disable the Liveticker function for the race??', 'h3-mgmt' ) .
									'\') ) { return true; } return false;" ' .
									'href="' . $url . '&amp;todo=liveticker-unset&amp;id=' .
									$row['id'] . '" class="submitdelete">' .
									__( 'Disable Liveticker!', 'h3-mgmt' ) .
								'</a>' .
							'</span>';
					break;

					case 'active':
						$output .= '<span class="edit"><a title="' .
								__( "Set as active Race / Event!", 'h3-mgmt' ) .
								'" href="' . $url . '&amp;todo=active&amp;id=' . $row['id'] . '">' .
								__( 'Set as active!', 'h3-mgmt' ) .
							'</a></span>';
					break;
					
					case 'liveticker_front':
						$output .= '<span class="edit"><a title="' .
								__( "Show the Liveticker content at the Homepage", 'h3-mgmt' ) .
								'" href="' . $url . '&amp;todo=liveticker-front-set&amp;id=' . $row['id'] . '">' .
								__( 'Show Liveticker!', 'h3-mgmt' ) .
							'</a></span> | ' .
							'<span class="delete">' .
								'<a title="' .
									__( 'Hide the Liveticker content at the Homepage (Liveticker not started yet!)', 'h3-mgmt' ) .
								'" onclick="if ( confirm(\'' .
										__( 'Really hide the Liveticker content at the Homepage??', 'h3-mgmt' ) .
									'\') ) { return true; } return false;" ' .
									'href="' . $url . '&amp;todo=liveticker-front-unset&amp;id=' .
									$row['id'] . '" class="submitdelete">' .
									__( 'Hide Liveticker!', 'h3-mgmt' ) .
								'</a>' .
							'</span>';
					break;

					case 'donation':
						$output .= '<span class="edit"><a title="' .
								__( "Enable the Donation Tool function for the race!", 'h3-mgmt' ) .
								'" href="' . $url . '&amp;todo=donation-set&amp;id=' . $row['id'] . '">' .
								__( 'Enable Donation Tool!', 'h3-mgmt' ) .
							'</a></span> | ' .
							'<span class="delete">' .
								'<a title="' .
									__( 'Disable the Liveticker function for the race!', 'h3-mgmt' ) .
								'" onclick="if ( confirm(\'' .
										__( 'Really disable the Donation Tool function for the race??', 'h3-mgmt' ) .
									'\') ) { return true; } return false;" ' .
									'href="' . $url . '&amp;todo=donation-unset&amp;id=' .
									$row['id'] . '" class="submitdelete">' .
									__( 'Disable Donation Tool!', 'h3-mgmt' ) .
								'</a>' .
							'</span>';
					break;

					case 'package':
						$output .= '<span class="edit"><a title="' .
								__( "This participant has paid for his/her HitchPackage", 'h3-mgmt' ) .
								'" href="' . $url . '&amp;todo=paid&amp;id=' . $row['id'] . '">' .
								__( 'HitchPackage paid!', 'h3-mgmt' ) .
							'</a></span> | ' .
							'<span class="delete">' .
								'<a title="' .
									__( 'Ooops, that was too quick. The package has actually not been paid yet...', 'h3-mgmt' ) .
								'" onclick="if ( confirm(\'' .
										__( 'Really reset the HitchPackage status of this participant to &quot;not paid&quot;?', 'h3-mgmt' ) .
									'\') ) { return true; } return false;" ' .
									'href="' . $url . '&amp;todo=unpaid&amp;id=' .
									$row['id'] . '" class="submitdelete">' .
									__( 'Not yet paid...', 'h3-mgmt' ) .
								'</a>' .
							'</span>';
					break;

					case 'sponsor-payment':
						$output .= '<span class="edit"><a title="' .
								__( "This sponsors payment has been received", 'h3-mgmt' ) .
								'" href="' . $url . '&amp;todo=paid&amp;id=' . $row['id'] . '">' .
								__( 'Sponsor has paid!', 'h3-mgmt' ) .
							'</a></span> | ' .
							'<span class="delete">' .
								'<a title="' .
									__( "Ooops, that was too quick. The sponsor's payment has actually not been received yet...", 'h3-mgmt' ) .
								'" onclick="if ( confirm(\'' .
										__( 'Really reset the Sponsors payment status to &quot;not paid&quot;?', 'h3-mgmt' ) .
									'\') ) { return true; } return false;" ' .
									'href="' . $url . '&amp;todo=unpaid&amp;id=' .
									$row['id'] . '" class="submitdelete">' .
									__( 'Not yet paid...', 'h3-mgmt' ) .
								'</a>' .
							'</span>';
					break;

					case 'sponsor-show':
						$output .= '<span class="edit"><a title="' .
								__( "This sponsor should appear in the front end", 'h3-mgmt' ) .
								'" href="' . $url . '&amp;todo=show&amp;id=' . $row['id'] . '">' .
								__( 'Show Sponsor!', 'h3-mgmt' ) .
							'</a></span> | ' .
							'<span class="delete">' .
								'<a title="' .
									__( "This sponsor should be hidden from the front end", 'h3-mgmt' ) .
								'" onclick="if ( confirm(\'' .
										__( 'Really hide the Sponsor from the front end', 'h3-mgmt' ) .
									'\') ) { return true; } return false;" ' .
									'href="' . $url . '&amp;todo=hide&amp;id=' .
									$row['id'] . '" class="submitdelete">' .
									__( 'Hide Sponsor...', 'h3-mgmt' ) .
								'</a>' .
							'</span>';
					break;
				}

				if ( ($i + 1) < $action_count ) {
					$output .= $flipper ? ' | ' : '<br />';
					$flipper = ! $flipper;
				}
			} else {
				$output .= '&nbsp;';
			}
		}

		$output .= '</div>';

		return $output;
	}

	/**
	 * Converts raw data to a more presentable format
	 * (Used in edge cases, *should* be avoided or done outside this class, ideally)
	 *
	 * @since 1.1
	 * @access private
	 */
	private function convert_data( $data, $conversion_type, $row ) {
		global $h3_mgmt_races, $h3_mgmt_utilities;

		$output = $data;
		switch( $conversion_type ) {

			case 'boolean':
				if( 1 == $data ) {
					$output = '<span class="positive">' . __( 'Yes!', 'h3-mgmt' ) . '</span>';
				} else {
					$output = '<span class="negative">' . __( 'No.', 'h3-mgmt' ) . '</span>';
				}
			break;
			
			case 'status':
				if( 0 == $data ) {
					$output = '<span class="status">' . __( 'bevor registration', 'h3-mgmt' ) . '</span>';
				}elseif( 1 == $data ) {
					$output = '<span class="status">' . __( 'registration open', 'h3-mgmt' ) . '</span>';
				}elseif( 2 == $data ) {
					$output = '<span class="status">' . __( 'registration closed and bevor race start', 'h3-mgmt' ) . '</span>';
				}elseif( 3 == $data ) {
					$output = '<span class="status">' . __( 'race started', 'h3-mgmt' ) . '</span>';
				}
			break;

			case 'date':
				$output = $h3_mgmt_utilities->h3_strftime( $data );
			break;

			case 'race-name':
				$output = $h3_mgmt_races->get_name( $data, 'race' );
			break;

			case 'route-name':
				$output = $h3_mgmt_races->get_name( $data, 'route' );
			break;

			case 'type':
				if( 'owner' === $data ) {
					$output = '<span>' . __( 'Owner', 'h3-mgmt' ) . '</span>';
				} elseif( 'sponsor' === $data ) {
					$output = '<span>' . __( 'Sponsor', 'h3-mgmt' ) . '</span>';
				}
			break;

			case 'method':
				if( 'debit' === $data ) {
					$output = '<span>' . __( 'Debit', 'h3-mgmt' ) . '</span>';
				} elseif( 'paypal' === $data ) {
					$output = '<span>' . __( 'PayPal', 'h3-mgmt' ) . '</span>';
				}
			break;

			case 'donation':
				$cpk = intval( $data ) / 20;
				$output = '<span>' . $data . ' &euro;</span>';
			break;

			case 'paid':
				$method = isset( $row['method'] ) ? $row['method'] : '';
				if( 1 == $data ) {
					$output = '<span class="positive">' . __( 'Yes.', 'h3-mgmt' ) . '</span>';
				} elseif( 0 == $data ) {
					$output = '<span class="negative">' . __( 'No!', 'h3-mgmt' ) . '</span>';
				}
			break;

			case 'mates':
				$output = '<strong>' . count( $data ) . ' ' . __( 'HitchMates', 'h3-mgmt' ) . '</strong><br />';

				$last_key = key( array_slice( $data, -1, 1, true ) );
				foreach( $data as $user_id => $mate_arr ) {
					$output .= $mate_arr['name'] . ' (' . $mate_arr['age'] . ', ' . $mate_arr['city'] . ')';
					if( $last_key != $user_id ) {
						$output .= '<br />';
					}
				}
			break;
		}

		return $output;
	}

	/**
	 * Returns table rows after filtering
	 *
	 * @since 1.1
	 * @access private
	 */
	public function get_filtered_rows( $filter, $rows ) {
		$filter_id = 0;
		if( isset( $_GET['filter_name0'] ) ) {
			foreach( $filter as $filter_is ){
				if( $_GET['filter_value'.$filter_id] != '' && $_GET['filter_value'.$filter_id] != '-1' && $_GET['filter_value'.$filter_id] != 'all' ){
					$rows_new = array();
					foreach( $rows as $row_is ) {
						if( stripslashes( $_GET['filter_value'.$filter_id]) == $row_is[$_GET['filter_name'.$filter_id]] ) {
							$rows_new[] = $row_is;
						}
					} 
					$rows = $rows_new;
				}
				$filter_id = $filter_id + 1;
			}
		}
		return $rows;
	}
	
} // class

endif; // class exists
	
?>