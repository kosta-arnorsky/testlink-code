<?php

/**
 * 
 * TestLink Open Source Project - http://testlink.sourceforge.net/
 * This script is distributed under the GNU General Public License 2 or later.
 *
 * @package     TestLink
 * @author      Andreas Simon
 * @copyright   2010,2014 TestLink community
 * @filesource  reqOverview.php
 *
 * List requirements with (or without) Custom Field Data in an ExtJS Table.
 * See TICKET 3227 for a more detailed description of this feature.
 * 
 *    
 */

require_once("../../config.inc.php");
require_once("common.php");
require_once('exttable.class.php');
testlinkInitPage($db,false,false,"checkRights");

$cfield_mgr = new cfield_mgr($db);
$templateCfg = templateConfiguration();
$tproject_mgr = new testproject($db);
$req_mgr = new requirement_mgr($db);

$cfg = getCfg();
$args = init_args($tproject_mgr);

$gui = init_gui($args);
$gui->reqIDs = $tproject_mgr->get_all_requirement_ids($args->tproject_id);

$smarty = new TLSmarty();
if(count($gui->reqIDs) > 0) 
{
  $chronoStart = microtime(true);

  $pathCache = null;
  $imgSet = $smarty->getImages();
  $gui->warning_msg = '';

  // get type and status labels
  $type_labels = init_labels($cfg->req->type_labels);
  $status_labels = init_labels($cfg->req->status_labels);
  
  $labels2get = array('no' => 'No', 'yes' => 'Yes', 'not_aplicable' => null,'never' => null,
                      'req_spec_short' => null,'title' => null, 'version' => null, 'th_coverage' => null, 'th_autocoverage' => null, 'th_regressioncoverage' => null,
                      'th_regressionautocoverage' => null, 'frozen' => null, 'type'=> null,'status' => null,'th_relations' => null, 'requirements' => null,
                      'number_of_reqs' => null, 'number_of_versions' => null, 'requirement' => null,
                      'version_revision_tag' => null, 'week_short' => 'calendar_week_short');
          
  $labels = init_labels($labels2get);
  
  $gui->cfields4req = (array)$cfield_mgr->get_linked_cfields_at_design($args->tproject_id, 1, null, 'requirement', null, 'name');
  $gui->processCF = count($gui->cfields4req) > 0;


  $coverageSet = null;
  $relationCounters = null;

  $version_option = ($args->all_versions) ? requirement_mgr::ALL_VERSIONS : requirement_mgr::LATEST_VERSION; 
  if( $version_option == requirement_mgr::LATEST_VERSION )
  {
    $reqSet = $req_mgr->getByIDBulkLatestVersionRevision($gui->reqIDs,array('outputFormat' => 'mapOfArray'));
  }
  else
  {
    $reqSet = $req_mgr->get_by_id($gui->reqIDs, $version_option,null,array('output_format' => 'mapOfArray'));
    // new dBug($reqSet);
  }  

  
  if($cfg->req->expected_coverage_management) 
  {
    $coverageSet = $req_mgr->getCoverageCounterSet($gui->reqIDs);
  }

  $autoCoverageSet = $req_mgr->getAutoCoverageCounterSet($gui->reqIDs);
  $regressionCoverageSet = $req_mgr->getRegressionCoverageCounterSet($gui->reqIDs);
  $regressionAutoCoverageSet = $req_mgr->getRegressionAutoCoverageCounterSet($gui->reqIDs);

  if($cfg->req->relations->enable) 
  {
    $relationCounters = $req_mgr->getRelationsCounters($gui->reqIDs);
  }

  // array to gather table data row per row
  $rows = array();    
  $rowsExport = array();    
 
  foreach($gui->reqIDs as $id) 
  {
    // now get the rest of information for this requirement
    //if( $version_option == requirement_mgr::ALL_VERSIONS )
    //{
    //  // This need to be refactored in future to improve performance
      //$req = $req_mgr->get_by_id($id, $version_option);
    //}  
    //else
    //{
    //  $req = $reqSet[$id];
    //}  
    $req = $reqSet[$id];

    // create the link to display
    $title = htmlentities($req[0]['req_doc_id'], ENT_QUOTES, $cfg->charset) . $cfg->glue_char . 
             htmlentities($req[0]['title'], ENT_QUOTES, $cfg->charset);
    
    // reqspec-"path" to requirement
    if( !isset($pathCache[$req[0]['srs_id']]) )
    {
      $path = $req_mgr->tree_mgr->get_path($req[0]['srs_id']);
      foreach ($path as $key => $p) 
      {
        $path[$key] = $p['name'];
      }
      $pathCache[$req[0]['srs_id']] = htmlentities(implode("/", $path), ENT_QUOTES, $cfg->charset);
    }         

    foreach($req as $version) 
    {
      // get content for each row to display
      $result = array();
      $resultExport = array();
        
      /**
        * IMPORTANT: 
        * the order of following items in this array has to be
        * the same as column headers are below!!!
        * 
        * should be:
        * 1. path
        * 2. title
        * 3. version
        * 4. frozen (is_open attribute)
        * 5. coverage (if enabled)
        * 6. auto coverage
        * 7. type
        * 8. status
        * 9. relations (if enabled)
        * 10. all custom fields in order of $fields
        */
        
      $result[] = $pathCache[$req[0]['srs_id']];
      $resultExport[] = $pathCache[$req[0]['srs_id']];
        
      $edit_link = '<a href="javascript:openLinkedReqVersionWindow(' . $id . ',' . $version['version_id'] . ')">' . 
                   '<img title="' .$labels['requirement'] . '" src="' . $imgSet['edit'] . '" /></a> ';
      
      $result[] =  '<!-- ' . $title . ' -->' . $edit_link . $title;
      $resultExport[] = $title;
        
      // version and revision number
      // $version_revision = sprintf($labels['version_revision_tag'],$version['version'],$version['revision']);
      // $padded_data = sprintf("%05d%05d", $version['version'], $version['revision']);
      
      // use html comment to sort properly by this column (extjs)
      // USE CARVED IN THE STONE [vxxsyy] to save function calls.
      $result[] = "<!-- " . sprintf("%05d%05d", $version['version'], $version['revision']) . "-->" .
                  "[v{$version['version']}r{$version['revision']}]";
      $resultExport[] = "[v{$version['version']}r{$version['revision']}]";
          
      // use html comment to sort properly by this columns (extjs)
      $result[] = "<!--{$version['creation_ts']}-->" . localizeTimeStamp($version['creation_ts'],$cfg->datetime) . 
                    " ({$version['author']})";
      $resultExport[] = localizeTimeStamp($version['creation_ts'],$cfg->datetime) . " ({$version['author']})";
      
      // 20140914 - 
      // Because we can do this logic thoundands of times, I suppose it will cost less
      // to do not use my other approach of firts assigning instead of using else.
      // 
      // use html comment to sort properly by this column (extjs)
      if( !is_null($version['modification_ts']) && ($version['modification_ts'] != $cfg->neverModifiedTS) )
      {
        $result[] = "<!--{$version['modification_ts']}-->" . localizeTimeStamp($version['modification_ts'],$cfg->datetime) . 
                    " ({$version['modifier']})";
        $resultExport[] = localizeTimeStamp($version['modification_ts'],$cfg->datetime) . " ({$version['modifier']})";
      }
      else
      {
        $result[] = "<!-- 0 -->" . $labels['never'];  
        $resultExport[] = $labels['never'];  
      }  
        
        
      // is it frozen?
      $result[] = ($version['is_open']) ? $labels['no'] : $labels['yes'];
      $resultExport[] = ($version['is_open']) ? $labels['no'] : $labels['yes'];
        
      // coverage
      // use html comment to sort properly by this columns (extjs)
      if($cfg->req->expected_coverage_management) 
      {
        $tc_coverage = isset($coverageSet[$id]) ? $coverageSet[$id]['qty'] : 0;
        $expected = $version['expected_coverage'];
        $coverage_string = "<!-- -1 -->" . $labels['not_aplicable'] . " ($tc_coverage/0)";
        $coverage_stringExport = $labels['not_aplicable'] . " ($tc_coverage/0)";
        if ($expected > 0) 
        {
          $percentage = round(100 / $expected * $tc_coverage, 2);
          $padded_data = sprintf("%010d", $percentage); //bring all percentages to same length
          $coverage_string = "<!-- $padded_data --> {$percentage}% ({$tc_coverage}/{$expected})";
          $coverage_stringExport = "{$percentage}% ({$tc_coverage}/{$expected})";
        }
        $result[] = $coverage_string;
        $resultExport[] = $coverage_stringExport;
      }

      $tc_autoCoverage = isset($autoCoverageSet[$id]) ? $autoCoverageSet[$id]['qty'] : 0;
      $expected = $version['expected_coverage'];
      $autoCoverage_string = "<!-- -1 -->" . $labels['not_aplicable'];
      $autoCoverage_stringExport = $labels['not_aplicable'];
      if ($expected > 0) 
      {
        $percentage = round(100 / $expected * $tc_autoCoverage, 2);
        $padded_data = sprintf("%010d", $percentage); //bring all percentages to same length
        $autoCoverage_string = "<!-- $padded_data --> {$percentage}%";
        $autoCoverage_stringExport = "{$percentage}%";
      }
      $result[] = $autoCoverage_string;
      $resultExport[] = $autoCoverage_stringExport;

      $tc_regressionCoverage = isset($regressionCoverageSet[$id]) ? $regressionCoverageSet[$id]['qty'] : 0;	  
      $regressionCoverage_string = "<!-- -1 -->" . $labels['not_aplicable'] . " ($tc_regressionCoverage/0)";
      $regressionCoverage_stringExport = $labels['not_aplicable'] . " ($tc_regressionCoverage/0)";
      if ($expected > 0) 
      {
        $percentage = round(100 / $expected * $tc_regressionCoverage, 2);
        $padded_data = sprintf("%010d", $percentage); //bring all percentages to same length
        $regressionCoverage_string = "<!-- $padded_data --> {$percentage}% ({$tc_regressionCoverage}/{$expected})";
        $regressionCoverage_stringExport = "{$percentage}% ({$tc_regressionCoverage}/{$expected})";
      }
      $result[] = $regressionCoverage_string;
      $resultExport[] = $regressionCoverage_stringExport;

      $tc_regressionAutoCoverage = isset($regressionAutoCoverageSet[$id]) ? $regressionAutoCoverageSet[$id]['qty'] : 0;
      $regressionAutoCoverage_string = "<!-- -1 --> 0%";
      $regressionAutoCoverage_stringExport = "0%";
      if ($tc_regressionCoverage > 0) 
      {
        $percentage = round(100 / $tc_regressionCoverage * $tc_regressionAutoCoverage, 2);
        $padded_data = sprintf("%010d", $percentage); //bring all percentages to same length
        $regressionAutoCoverage_string = "<!-- $padded_data --> {$percentage}%";
        $regressionAutoCoverage_stringExport = "{$percentage}%";
      }
      $result[] = $regressionAutoCoverage_string;
      $resultExport[] = $regressionAutoCoverage_stringExport;
        
      $result[] = isset($type_labels[$version['type']]) ? $type_labels[$version['type']] : '';
      $resultExport[] = isset($type_labels[$version['type']]) ? $type_labels[$version['type']] : '';
      $result[] = isset($status_labels[$version['status']]) ? $status_labels[$version['status']] : '';
      $resultExport[] = isset($status_labels[$version['status']]) ? $status_labels[$version['status']] : '';
      
      if ($cfg->req->relations->enable) 
      {
        $rx = isset($relationCounters[$id]) ? $relationCounters[$id] : 0;
        $result[] = "<!-- " . str_pad($rx,10,'0') . " -->" . $rx;
        $resultExport[] = $rx;
      }
      
      if($gui->processCF)
      {
        // get custom field values for this req version
        $linked_cfields = (array)$req_mgr->get_linked_cfields($id,$version['version_id']);

        foreach ($linked_cfields as $cf) 
        {
          $verbose_type = $req_mgr->cfield_mgr->custom_field_types[$cf['type']];
          $value = preg_replace('!\s+!', ' ', htmlspecialchars($cf['value'], ENT_QUOTES, $cfg->charset));
          if( ($verbose_type == 'date' || $verbose_type == 'datetime') && is_numeric($value) && $value != 0 )
          {
            $value = strftime( $cfg->$verbose_type . " ({$label['week_short']} %W)" , $value);
          }  
          $result[] = $value;
          $resultExport[] = $value;
        }
      }  
        
      $rows[] = $result;
      $rowsExport[] = $resultExport;
    }
  }
    
  // echo 'Elapsed Time since SCRIPT START to EXT-JS Phase START (sec) =' . round(microtime(true) - $chronoStart);
  // die();
  // -------------------------------------------------------------------------------------------------- 
  // Construction of EXT-JS table starts here    
  if(($gui->row_qty = count($rows)) > 0 ) 
  {
    $version_string = ($args->all_versions) ? $labels['number_of_versions'] : $labels['number_of_reqs'];
    $gui->pageTitle .= " - " . $version_string . ": " . $gui->row_qty;
       
    /**
     * get column header titles for the table
     * 
     * IMPORTANT: 
     * the order of following items in this array has to be
     * the same as row content above!!!
     * 
     * should be:
     * 1. path
     * 2. title
     * 3. version
     * 4. frozen
     * 5. coverage (if enabled)
     * 6. auto coverage
     * 7. regression coverage
     * 8. regression auto coverage
     * 9. type
     * 10. status
     * 11. relations (if enabled)
     * 12. then all custom fields in order of $fields
     */
    $columns = array();
    $columns[] = array('title_key' => 'req_spec_short', 'width' => 200);
    $columns[] = array('title_key' => 'title', 'width' => 150);
    $columns[] = array('title_key' => 'version', 'width' => 30);
    $columns[] = array('title_key' => 'created_on', 'width' => 55);
    $columns[] = array('title_key' => 'modified_on','width' => 55);
      
    $frozen_for_filter = array($labels['yes'],$labels['no']);
    $columns[] = array('title_key' => 'frozen', 'width' => 30, 'filter' => 'list',
                       'filterOptions' => $frozen_for_filter);
        
    if($cfg->req->expected_coverage_management) 
    {
      $columns[] = array('title_key' => 'th_coverage', 'width' => 80);
    }
	
	$columns[] = array('title_key' => 'th_autocoverage', 'width' => 80);
	
	$columns[] = array('title_key' => 'th_regressioncoverage', 'width' => 80);
	
	$columns[] = array('title_key' => 'th_regressionautocoverage', 'width' => 80);
              
    $columns[] = array('title_key' => 'type', 'width' => 60, 'filter' => 'list',
                       'filterOptions' => $type_labels);
    $columns[] = array('title_key' => 'status', 'width' => 60, 'filter' => 'list',
                       'filterOptions' => $status_labels);
      
    if ($cfg->req->relations->enable) 
    {
      $columns[] = array('title_key' => 'th_relations', 'width' => 50, 'filter' => 'numeric');
    }
        
    foreach($gui->cfields4req as $cf) 
    {
      $columns[] = array('title' => htmlentities($cf['label'], ENT_QUOTES, $cfg->charset), 'type' => 'text',
                         'col_id' => 'id_cf_' .$cf['name']);
    }

    // create table object, fill it with columns and row data and give it a title
    $matrix = new tlExtTable($columns, $rows, 'tl_table_req_overview');
    $matrix->title = $labels['requirements'];
        
    // group by Req Spec
    $matrix->setGroupByColumnName($labels['req_spec_short']);
        
    // sort by coverage descending if enabled, otherwise by status
    $sort_name = ($cfg->req->expected_coverage_management) ? $labels['th_coverage'] : $labels['status'];
    $matrix->setSortByColumnName($sort_name);
    $matrix->sortDirection = 'DESC';
        
    // define toolbar
    $matrix->showToolbar = true;
    $matrix->toolbarExpandCollapseGroupsButton = true;
    $matrix->toolbarShowAllColumnsButton = true;
    $matrix->toolbarRefreshButton = true;
    $matrix->showGroupItemsCount = true;
    
    // show custom field content in multiple lines
    $matrix->addCustomBehaviour('text', array('render' => 'columnWrap'));
    $gui->tableSet= array($matrix);
  }

  $chronoStop = microtime(true);
  $gui->elapsedSeconds = round($chronoStop - $chronoStart);
} 


switch($_GET['export'])
{
	case 'csv':
		$columnNames = array();
		foreach ($columns as $column)
		{
			$columnNames[] = isset($column['title']) ? $column['title'] : lang_get($column['title_key']);
		}
		
		header('Content-Type: text/csv; charset=utf-8');
		header('Content-Disposition: attachment; filename='.date("Ymd_His").'_requirements.csv');
		
		// create a file pointer connected to the output stream
		$output = fopen('php://output', 'w');
		// output the column headings
		fputcsv($output, $columnNames);
		foreach ($rowsExport as $row)
		{
			fputcsv($output, $row);
		}

		//var_dump($columnNames);
		//var_dump($rowsExport);
		break;
	default:
		$smarty->assign('gui',$gui);
		$smarty->display($templateCfg->template_dir . $templateCfg->default_template);
}


/**
 * initialize user input
 * 
 * @param resource &$tproject_mgr reference to testproject manager
 * @return array $args array with user input information
 */
function init_args(&$tproject_mgr)
{
  $args = new stdClass();

  $all_versions = isset($_REQUEST['all_versions']) ? true : false;
  $all_versions_hidden = isset($_REQUEST['all_versions_hidden']) ? true : false;
  if ($all_versions) {
    $selection = true;
  } else if ($all_versions_hidden) {
    $selection = false;
  } else if (isset($_SESSION['all_versions'])) {
    $selection = $_SESSION['all_versions'];
  } else {
    $selection = false;
  }
  $args->all_versions = $_SESSION['all_versions'] = $selection;
  
  $args->tproject_id = intval(isset($_SESSION['testprojectID']) ? $_SESSION['testprojectID'] : 0);
  $args->tproject_name = isset($_SESSION['testprojectName']) ? $_SESSION['testprojectName'] : '';
  if($args->tproject_id > 0) 
  {
    $tproject_info = $tproject_mgr->get_by_id($args->tproject_id);
    $args->tproject_name = $tproject_info['name'];
    $args->tproject_description = $tproject_info['notes'];
  }
  
  return $args;
}


/**
 * initialize GUI
 * 
 * @param stdClass $argsObj reference to user input
 * @return stdClass $gui gui data
 */
function init_gui(&$argsObj) 
{
  $gui = new stdClass();
  
  $gui->pageTitle = lang_get('caption_req_overview');
  $gui->warning_msg = lang_get('no_linked_req');
  $gui->tproject_name = $argsObj->tproject_name;
  $gui->all_versions = $argsObj->all_versions;
  $gui->tableSet = null;
  
  return $gui;
}


/**
 *
 */
function getCfg()
{
  $cfg = new stdClass();
  $cfg->glue_char = config_get('gui_title_separator_1');
  $cfg->charset = config_get('charset');
  $cfg->req = config_get('req_cfg');
  $cfg->date = config_get('date_format');
  $cfg->datetime = config_get('timestamp_format');

  // on requirement creation motification timestamp is set to default value "0000-00-00 00:00:00"
  $cfg->neverModifiedTS = "0000-00-00 00:00:00";

  // $cfg->req->expected_coverage_management = FALSE;   // FORCED FOR TEST

  return $cfg;
}


/*
 * rights check function for testlinkInitPage()
 */
function checkRights(&$db, &$user)
{
  return $user->hasRight($db,'mgt_view_req');
}

