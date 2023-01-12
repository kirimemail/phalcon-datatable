<?php

namespace DataTables\Adapters;

use DataTables\CacheHelper;
use DataTables\HashHelper;
use Phalcon\Mvc\Model\Query\Builder;
use Phalcon\Paginator\Adapter\QueryBuilder as PQueryBuilder;

class QueryBuilderWithoutCounter extends AdapterInterface
{
    /** @var Builder $builder */
    protected $builder;
    private $global_search;
    private $column_search;
    private $_bind;
    /** @var CacheHelper $cacheHelper */
    private $cacheHelper;
    private $cache_enabled;
    private $left_off_pagination;
    private $left_off_column;
    private $left_off_first;
    private $left_off_last;
    private $left_off_trigger;

    public function __construct($length, $cache_enabled = false, $cache_di = 'modelsCache', $lifetime = 3600, $left_off_pagination = false, $left_off_column = 'id')
    {
        parent::__construct($length);
        $this->cacheHelper = new CacheHelper($cache_di, $lifetime);
        $this->cache_enabled = $cache_enabled;
        $this->left_off_pagination = $left_off_pagination;
        $this->left_off_column = $left_off_column;
    }

    public function setBuilder($builder)
    {
        $this->builder = $builder;
    }

    public function getResponse()
    {
        /**
         **** CACHE NOT APPLIED YET ==================
        */

        $this->global_search = [];
        $this->column_search = [];
        
        $this->bind('global_search', false, function ($column, $search) {
            $key = "keyg_" . preg_replace("/[^[:alnum:][:space:]]/u", "", $column);
            $this->global_search[] = "{$column} LIKE :{$key}:";
            $this->_bind[$key] = "%{$search}%";
        });

        $this->bind('column_search', false, function ($column, $search) {
            $key = "keyc_" . str_replace(" ", "", preg_replace("/[^[:alnum:][:space:]]/u", "", $column));
            $this->column_search[] = "{$column} LIKE :{$key}:";
            $this->_bind[$key] = "%{$search}%";
        });

        $this->bind('order', false, function ($order) {
            $order_str = implode(', ', $order);
            if (!empty($order) && trim($order_str) != 'asc' && trim($order_str) != 'desc') {
                $this->builder->orderBy($order_str);
            }
            
            // If use left off pagination
            if($this->left_off_pagination){
                $this->leftOffPagination($order_str);
            }
        });

        if (!empty($this->global_search) || !empty($this->column_search)) {
            $where = implode(' OR ', $this->global_search);
            if (!empty($this->column_search)) {
                $where = (empty($where) ? '' : ('(' . $where . ') AND ')) . implode(' AND ', $this->column_search);
            }
            $this->builder->andWhere($where, $this->_bind);
        }

        if(!$this->left_off_pagination){
            $this->builder->limit($this->parser->getLimit(), $this->parser->getOffset());
        }

        $data = $this->builder->getQuery()->execute()->toArray();

        // Hack total to enable next button pagination ===============================
        $total_count = $this->parser->getLimit()+$this->parser->getOffset();
        $filtered_count = $this->parser->getLimit()+$this->parser->getOffset();
        $data_count = count($data);
        
        if($data_count > ($this->parser->getLimit() - 1)){
            $total_count += 1;
            $filtered_count += 1;
        }
        // ===========================================================================

        return $this->formResponse([
            'total' => $total_count,
            'filtered' => $filtered_count,
            'data' => $data
        ]);
    }

    /**
     * Pagination using where id "left off" based by ordering
     *
     * @param string $order_str
     * @return void
     */
    private function leftOffPagination($order_str) {

        // Checking ordering column
        if(strpos($order_str, $this->left_off_column) !== false){

            // Get request parameters
            $params = $this->parser->getParams();

            // Get and set last left off value
            if(isset($params['left_off_last']) && $params['left_off_last'] != ''){
                $this->left_off_last = $params['left_off_last'];
            }

            // Get and set first left off value
            if(isset($params['left_off_first']) && $params['left_off_first'] != ''){
                $this->left_off_first = $params['left_off_first'];
            }

            // Get and set left off trigger (next/prev)
            if(isset($params['left_off_trigger']) && $params['left_off_trigger'] != ''){
                $this->left_off_trigger = $params['left_off_trigger'];
            }

            if(strpos($order_str, 'desc') !== false){ // If ordering is desc

                if($this->left_off_trigger == 'next'){
                    if(!empty($this->left_off_last)) {
                        $this->builder->andWhere($this->left_off_column.' < :id:', [
                            'id' => $this->left_off_last
                        ]);
                    }

                    
                }elseif($this->left_off_trigger == 'prev'){
                    if(!empty($this->left_off_first)) {
                        $this->builder->andWhere($this->left_off_column.' > :id:', [
                            'id' => $this->left_off_first
                        ]);
                    }

                    $this->builder->offset($this->parser->getOffset());
                }

            }else{ // If ordering is asc

                if($this->left_off_trigger == 'next'){
                    if(!empty($this->left_off_last)) {
                        $this->builder->andWhere($this->left_off_column.' > :id:', [
                            'id' => $this->left_off_last
                        ]);
                    }

                    
                }elseif($this->left_off_trigger == 'prev'){
                    if(!empty($this->left_off_first)) {
                        $this->builder->andWhere($this->left_off_column.' < :id:', [
                            'id' => $this->left_off_first
                        ]);
                    }

                    $this->builder->offset($this->parser->getOffset());
                }

            }

            // Set limit
            $this->builder->limit($this->parser->getLimit());
        }
    }
}