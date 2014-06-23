<?php
class DataTablesBehavior extends ModelBehavior {
    
    private $cacheEnabled;
    
    private $cacheTTL;
    
    private $_cacheTTLDefault = 300;
    
    private $pipelineEnabled;
    
    private $pipelineNumberOfPages;
    
    private $_pipelineDefaultNumberOfPages;
    
    private $tableName = null;
    
    private $query;
    
    private $data;
    
    private $request;
    
    private $settings = array();
    
    public function setup(Model $Model, $settings = array()) {
        
        // Store settings in object
        $this->settings = $settings;
        
        // Check if request is set
        $this->request = $this->configCheck('request');
        
        // Check if cache is enabled
        $this->cacheEnabled = $this->configCheck('cache');
        
        // Check if Cache ttl is set if it isn't set default
        if($this->cacheEnabled) {
            $cacheTTL = $this->configCheck('cache_ttl');
            if(is_int($cacheTTL)) {
                $this->cacheTTL = $cacheTTL;
            } else {
                $this->cacheTTL = $this->_cacheTTLDefault;
            }
        }
        
        // Check if pipeline is enabled
        $this->pipelineEnabled = $this->configCheck('pipeline');
        
        // Check if number of pages for the pipelie is set if
        // it isn't set default
        if($this->pipelineEnabled) {
            $numberOfPages = $this->configCheck('pipeline_pages');
            if(is_int($numberOfPages)) {
                $this->pipelineNumberOfPages = $numberOfPages;
            } else {
                $this->pipelineNumberOfPages = $this->_pipelineDefaultNumberOfPages;
            }
        }
        
        // Check if table name is set 
        $table = $this->configCheck('tableName');     
        if($table) {
            $this->tableName = $table;
        }        
    }    
    
    /**
     * Check configurations of DataTables Plugin
     * @param type $param
     * @return boolean | mixed
     */ 
    private function configCheck($param) {
        if(isset($this->settings[$param])) {
            $value = $this->settings[$param];
            if($value) {
                return $value;
            }
        }
        return false;
    }
    
    private function limit() {
        $limit = '';

        if ( isset($this->request['start']) && $this->request['length'] != -1 ) {
                $limit = "LIMIT ".intval($this->request['start']).", ".intval($this->request['length']);
        }

        return $limit;    
    }
    
    private function order($request) {
        
    }

    private function filter ( $request, $columns, &$bindings )
    {
        $globalSearch = array();
        $columnSearch = array();
        $dtColumns = SSP::pluck( $columns, 'dt' );

        if ( isset($request['search']) && $request['search']['value'] != '' ) {
                $str = $request['search']['value'];

                for ( $i=0, $ien=count($request['columns']) ; $i<$ien ; $i++ ) {
                        $requestColumn = $request['columns'][$i];
                        $columnIdx = array_search( $requestColumn['data'], $dtColumns );
                        $column = $columns[ $columnIdx ];

                        if ( $requestColumn['searchable'] == 'true' ) {
                                $binding = SSP::bind( $bindings, '%'.$str.'%', PDO::PARAM_STR );
                                $globalSearch[] = "`".$column['db']."` LIKE ".$binding;
                        }
                }
        }

        // Individual column filtering
        for ( $i=0, $ien=count($request['columns']) ; $i<$ien ; $i++ ) {
                $requestColumn = $request['columns'][$i];
                $columnIdx = array_search( $requestColumn['data'], $dtColumns );
                $column = $columns[ $columnIdx ];

                $str = $requestColumn['search']['value'];

                if ( $requestColumn['searchable'] == 'true' &&
                 $str != '' ) {
                        $binding = SSP::bind( $bindings, '%'.$str.'%', PDO::PARAM_STR );
                        $columnSearch[] = "`".$column['db']."` LIKE ".$binding;
                }
        }

        // Combine the filters into a single string
        $where = '';

        if ( count( $globalSearch ) ) {
                $where = '('.implode(' OR ', $globalSearch).')';
        }

        if ( count( $columnSearch ) ) {
                $where = $where === '' ?
                        implode(' AND ', $columnSearch) :
                        $where .' AND '. implode(' AND ', $columnSearch);
        }

        if ( $where !== '' ) {
                $where = 'WHERE '.$where;
        }

        return $where;
    }
    
    private function checkRequest() {
        
    }
    
    public function dataTables(Model $model, $request = null)
    {
        // If request wasn't in setup it must be on function
        if($this->request === false) {
            $this->request = $request;
        }
        
        // Check if is a valid request
        $this->checkRequest();
        
        // Cache logic
        $hash = null;
        if($this->cacheEnabled) {
            $hash = md5(json_encode($this->request));   
            $cache = Cache::read($hash);
            
            if($cache !== false) {
                return $cache;
            }   
        }
        
        // Build the query and run it!
        $this->limit($this->request);
        $this->order($this->request);
        $this->filter($this->request);   
        $this->runQuery();
        
        // Output
        $output = array(
            "draw"            => intval( $this->request['draw'] ),
            "recordsTotal"    => intval( $model->find('count') ),
            "recordsFiltered" => intval( count($this->data) ),
            "data"            => $this->data
        );        
        
        // If cache is on, cache output
        if(!is_null($hash)) {
            Cache::write($hash, $output);
        }
        
        // Return it
        return $output;
    }
        
    
    private function getSchema(Model $model) {
        return array_keys($model->schema());
    }       
}