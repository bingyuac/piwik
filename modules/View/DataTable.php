<?php
class Piwik_View_DataTable
{
	protected $dataTableTemplate = 'UserSettings/templates/datatable.tpl';
	
	protected $currentControllerAction;
	protected $moduleNameAndMethod;
	protected $actionToLoadTheSubTable;
	
	protected $JSsearchBox 				= true;
	protected $JSoffsetInformation 		= true;
	protected $JSexcludeLowPopulation 	= true;
	protected $JSsortEnabled 			= true;
	
	protected $mainAlreadyExecuted = false;
	protected $columnsToDisplay = array();
	protected $variablesDefault = array();
	
	const DEFAULT_COLUMN_EXCLUDE_LOW_POPULATION = 2;
	function __construct( $currentControllerAction, 
						$moduleNameAndMethod, 
						$actionToLoadTheSubTable = null)
	{
		$this->currentControllerAction = $currentControllerAction;
		$this->moduleNameAndMethod = $moduleNameAndMethod;
		$this->actionToLoadTheSubTable = $actionToLoadTheSubTable;
		
		$this->idSubtable = Piwik_Common::getRequestVar('idSubtable', false,'int');
		
		$this->variablesDefault['filter_excludelowpop_default'] = 'false';
		$this->variablesDefault['filter_excludelowpop_value_default'] = 'false';	
	}
	
	function getView()
	{
		$this->main();
		return $this->view;
	}
	
	public function setTemplate( $tpl )
	{
		$this->dataTableTemplate = $tpl;
	}
	public function main()
	{
		if($this->mainAlreadyExecuted)
		{
			return;
		}
		$this->mainAlreadyExecuted = true;
		
//		$i=0;while($i<1500000){ $j=$i*$i;$i++;}
		
		// is there a Sub DataTable requested ? 
		// for example do we request the details for the search engine Google?
		
		
		$this->loadDataTableFromAPI();

	
		// We apply a filter to the DataTable, decoding the label column (useful for keywords for example)
		$filter = new Piwik_DataTable_Filter_ColumnCallbackReplace(
									$this->dataTable, 
									'label', 
									'urldecode'
								);
		
		
		$view = new Piwik_View($this->dataTableTemplate);
		
		$view->id 			= $this->getUniqIdTable();
		
		// We get the PHP array converted from the DataTable
		$phpArray = $this->getPHPArrayFromDataTable();
		
		$view->dataTable 	= $phpArray;
		
		$columns = $this->getColumnsToDisplay($phpArray);
		$view->dataTableColumns = $columns;
		
		$nbColumns = count($columns);
		// case no data in the array we use the number of columns set to be displayed 
		if($nbColumns == 0)
		{
			$nbColumns = count($this->columnsToDisplay);
		}
		
		$view->nbColumns = $nbColumns;
		
		$view->javascriptVariablesToSet 
			= $this->getJavascriptVariablesToSet();
		
		$this->view = $view;
	}
	
	protected function getUniqIdTable()
	{
		
		// the $uniqIdTable variable is used as the DIV ID in the rendered HTML
		// we use the current Controller action name as it is supposed to be unique in the rendered page 
		$uniqIdTable = $this->currentControllerAction;

		// if we request a subDataTable the $this->currentControllerAction DIV ID is already there in the page
		// we make the DIV ID really unique by appending the ID of the subtable requested
		if( $this->idSubtable != false)
		{			
			$uniqIdTable = 'subDataTable_' . $this->idSubtable;
		}
		return $uniqIdTable;
	}
	
	public function setColumnsToDisplay( $arrayIds)
	{
		$this->columnsToDisplay = $arrayIds;
	}
	
	protected function isColumnToDisplay( $idColumn )
	{
		// we return true
		// - we didn't set any column to display (means we display all the columns)
		// - the column has been set as to display
		if( count($this->columnsToDisplay) == 0
			|| in_array($idColumn, $this->columnsToDisplay))
		{
			return true;
		}
		return false;
	}
	
	protected function getColumnsToDisplay($phpArray)
	{
		
		$dataTableColumns = array();
		if(count($phpArray) > 0)
		{
			// build column information
			$id = 0;
			foreach($phpArray[0]['columns'] as $columnName => $row)
			{
				if( $this->isColumnToDisplay( $id, $columnName) )
				{
					$dataTableColumns[]	= array('id' => $id, 'name' => $columnName);
				}
				$id++;
			}
		}
		return $dataTableColumns;
	}
	
	protected function getDefaultOrCurrent( $nameVar )
	{
		if(isset($_REQUEST[$nameVar]))
		{
			return $_REQUEST[$nameVar];
		}
		$default = $this->getDefault($nameVar);
		return $default;
	}
	
	protected function getDefault($nameVar)
	{
		if(!isset($this->variablesDefault[$nameVar]))
		{
			return false;
		}
		return $this->variablesDefault[$nameVar];
	}
	public function setExcludeLowPopulation( $value = 30 )
	{
		$this->variablesDefault['filter_excludelowpop_default'] = 2;
		$this->variablesDefault['filter_excludelowpop_value_default'] = $value;	
		$this->variablesDefault['filter_excludelowpop'] = 2;
		$this->variablesDefault['filter_excludelowpop_value'] = $value;	
	}
	
	public function setDefaultLimit( $limit )
	{
		$this->variablesDefault['filter_limit'] = $limit;
	}
	
	public function setSortedColumn( $columnId, $order = 'desc')
	{
		$this->variablesDefault['filter_sort_column']= $columnId;
		$this->variablesDefault['filter_sort_order']= $order;
	}
	public function disableSort()
	{
		$this->JSsortEnabled = 'false';		
	}
	public function getSort()
	{
		return $this->JSsortEnabled;		
	}
	
	public function disableOffsetInformation()
	{
		$this->JSoffsetInformation = 'false';		
	}
	public function getOffsetInformation()
	{
		return $this->JSoffsetInformation;
	}
	
	public function disableSearchBox()
	{
		$this->JSsearchBox = 'false';
	}
	public function getSearchBox()
	{
		return $this->JSsearchBox;
	}
	public function disableExcludeLowPopulation()
	{
		$this->JSexcludeLowPopulation = 'false';
	}
	
	public function getExcludeLowPopulation()
	{
		return $this->JSexcludeLowPopulation;
	}
	
	protected function getJavascriptVariablesToSet(	)
	{
		// build javascript variables to set
		$javascriptVariablesToSet = array();
		
		$genericFilters = Piwik_API_Request::getGenericFiltersInformation();
		foreach($genericFilters as $filter)
		{
			foreach($filter as $filterVariableName => $filterInfo)
			{
				// if there is a default value for this filter variable we set it 
				// so that it is propagated to the javascript
				if(isset($filterInfo[1]))
				{
					$javascriptVariablesToSet[$filterVariableName] = $filterInfo[1];
					
					// we set the default specified column and Order to sort by
					// when this javascript variable is not set already
					// for example during an AJAX call this variable will be set in the URL
					// so this will not be executed ( and the default sorted not be used as the sorted column might have changed in the meanwhile)
					if( false !== ($defaultValue = $this->getDefault($filterVariableName)))
					{
						$javascriptVariablesToSet[$filterVariableName] = $defaultValue;
					}
				}
			}
		}
		
//		var_dump($javascriptVariablesToSet);exit;
		//TODO check security of printing javascript variables; inject some JS code here??
		foreach($_GET as $name => $value)
		{
			try{
				$requestValue = Piwik_Common::getRequestVar($name);
			}
			catch(Exception $e) {
				$requestValue = '';
			}
			$javascriptVariablesToSet[$name] = $requestValue;
		}
		
		// at this point there are some filters values we  may have not set, 
		// case of the filter without default values and parameters set directly in this class
		// for example setExcludeLowPopulation
		// we go through all the $this->variablesDefault array and set the variables not set yet
		foreach($this->variablesDefault as $name => $value)
		{
			if(!isset($javascriptVariablesToSet[$name] ))
			{
				$javascriptVariablesToSet[$name] = $value;
			}
		}
		
		
		$javascriptVariablesToSet['action'] = $this->currentControllerAction;
		
		if(!is_null($this->actionToLoadTheSubTable))
		{
			$javascriptVariablesToSet['actionToLoadTheSubTable'] = $this->actionToLoadTheSubTable;
		}
		
//		var_dump($this->variablesDefault);
//		var_dump($javascriptVariablesToSet); exit;
		
		$javascriptVariablesToSet['totalRows'] = $this->dataTable->getRowsCountBeforeLimitFilter();
		
		$javascriptVariablesToSet['show_search'] = $this->getSearchBox();
		$javascriptVariablesToSet['show_offset_information'] = $this->getOffsetInformation();
		$javascriptVariablesToSet['show_exclude_low_population'] = $this->getExcludeLowPopulation();
		$javascriptVariablesToSet['enable_sort'] = $this->getSort();
		
		return $javascriptVariablesToSet;
	}
	
	protected function loadDataTableFromAPI()
	{
		
		// we prepare the string to give to the API Request
		// we setup the method and format variable
		// - we request the method to call to get this specific DataTable
		// - the format = original specifies that we want to get the original DataTable structure itself, not rendered
		$requestString = 'method='.$this->moduleNameAndMethod.'&format=original';
		
		// if a subDataTable is requested we add the variable to the API request string
		if( $this->idSubtable != false)
		{
			$requestString .= '&this->idSubtable='.$this->idSubtable;
		}
		
		$toSetEventually = array(
			'filter_limit',
			'filter_sort_column',
			'filter_sort_order',
			'filter_excludelowpop',
			'filter_excludelowpop_value',
		);
		foreach($toSetEventually as $varToSet)
		{
			$value = $this->getDefaultOrCurrent($varToSet);
			if( false !== $value )
			{
				$requestString .= '&'.$varToSet.'='.$value;
			}
		}
		// We finally make the request to the API
		$request = new Piwik_API_Request($requestString);
		
		// and get the DataTable structure
		$dataTable = $request->process();
		
		$this->dataTable = $dataTable;
	}

	protected function getPHPArrayFromDataTable( )
	{
		$renderer = Piwik_DataTable_Renderer::factory('php');
		$renderer->setTable($this->dataTable);
		$renderer->setSerialize( false );
		$phpArray = $renderer->render();
		return $phpArray;
	}
}