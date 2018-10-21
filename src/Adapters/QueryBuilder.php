<?php

namespace DataTables\Adapters;

use DataTables\CacheHelper;
use DataTables\HashHelper;
use Phalcon\Mvc\Model\Query\Builder;
use Phalcon\Paginator\Adapter\QueryBuilder as PQueryBuilder;

class QueryBuilder extends AdapterInterface
{
    /** @var Builder $builder */
    protected $builder;
    private $global_search;
    private $column_search;
    private $_bind;
    /** @var CacheHelper $cacheHelper */
    private $cacheHelper;
    private $cache_enabled;

    public function __construct($length, $cache_enabled = false, $cache_di = 'modelsCache', $lifetime = 3600)
    {
        parent::__construct($length);
        $this->cacheHelper = new CacheHelper($cache_di, $lifetime);
        $this->cache_enabled = $cache_enabled;
    }

    public function setBuilder($builder)
    {
        $this->builder = $builder;
    }

    public function getResponse()
    {
        $builder = new PQueryBuilder([
            'builder' => $this->builder,
            'limit' => 1,
            'page' => 1,
        ]);
        if ($this->cache_enabled) {
            if ($cache = $this->cacheHelper->getCache(HashHelper::hash($builder->getQueryBuilder()->getWhere()))) {
                $total = $cache;
            } else {
                $total = $builder->getPaginate();
                $this->cacheHelper->saveCache(HashHelper::hash($builder->getQueryBuilder()->getWhere()), $total);
            }
        } else {
            $total = $builder->getPaginate();
        }
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
            if (!empty($order) && trim(implode(', ', $order)) != 'asc' && trim(implode(', ', $order)) != 'desc') {
                $this->builder->orderBy(implode(', ', $order));
            }
        });
        if (!empty($this->global_search) || !empty($this->column_search)) {
            $where = implode(' OR ', $this->global_search);
            if (!empty($this->column_search)) {
                $where = (empty($where) ? '' : ('(' . $where . ') AND ')) . implode(' AND ', $this->column_search);
            }
            $this->builder->andWhere($where, $this->_bind);
        }
        $builder = new PQueryBuilder([
            'builder' => $this->builder,
            'limit' => $this->parser->getLimit($total->total_items),
            'page' => $this->parser->getPage(),
        ]);
        if ($this->cache_enabled) {
            if ($cache = $this->cacheHelper->getCache(HashHelper::hash($builder->getQueryBuilder()->getPhql() . $this->parser->getPage() . serialize($this->_bind)))) {
                $filtered = $cache;
            } else {
                $filtered = $builder->getPaginate();
                $this->cacheHelper->saveCache(HashHelper::hash($builder->getQueryBuilder()->getPhql() . $this->parser->getPage() . serialize($this->_bind)), $total);
            }
        } else {
            $filtered = $builder->getPaginate();
        }

        return $this->formResponse([
            'total' => $total->total_items,
            'filtered' => $filtered->total_items,
            'data' => $builder->getPaginate()->items->toArray(),
        ]);
    }
}