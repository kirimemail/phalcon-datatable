<?php

namespace DataTables\Adapters;

use DataTables\CacheHelper;
use DataTables\HashHelper;

class QueryAdapter extends AdapterInterface
{

    protected $query = [];
    protected $column = [];
    protected $global = [];
    protected $order = [];

    /** @var CacheHelper $cacheHelper */
    private $cacheHelper;

    public function __construct($length, $cache_di = 'modelsCache', $lifetime = 86400)
    {
        parent::__construct($length);
        $this->cacheHelper = new CacheHelper($cache_di, $lifetime);
    }

    public function setQuery(array $query)
    {
        $this->query = $query;
    }

    public function getResponse()
    {
        // init
        /** @var \Phalcon\Db\AdapterInterface $db */
        $db = \Phalcon\DI::getDefault()->getShared('db');

        // generates query
        $select = 'SELECT ' . $this->query['select'];
        $select_count = 'SELECT COUNT(*) AS total';

        $from = ' FROM ' . $this->query['from'];
        $where = ' WHERE ' . (is_array($this->query['where']) ? implode(' AND ', $this->query['where']) : $this->query['where']);
        $group_by = isset($this->query['group_by']) ? ' GROUP BY ' . $this->query['group_by'] : '';

        // search
        $where_or = [];

        if (!empty($search = $this->parser->getSearch()['value'])) {
            foreach ($this->parser->getColumns() as $column) {
                if ($column['searchable'] == 'true' && $column['name'] != '') {
                    $where_or[] = $column['name'] . " LIKE '%" . $search . "%' ";
                }
            }
        }

        $where_or = implode(' OR ', $where_or);

        if (!empty($where_or)) {
            $where_or = ' AND (' . $where_or . ')';
        }

        // order
        $order_by = '';
        $orders = $this->parser->getOrder();
        if (!$orders) {
            $order_by .= '';
        } else {
            $order_by .= ' ORDER BY ';
            foreach ($orders as $order) {
                $column = $this->parser->getColumnByIdFull($order['column']);
                if ($column['orderable'] == 'true' && isset($column['data']) && !empty($column['data'])) {
                    $order_by .= $column['data'] . ' ' . $order['dir'] . ' , ';
                }
            }
            if ($order_by === ' ORDER BY ') {
                $order_by = '';
            }
            $order_by = rtrim($order_by, ', ');
        }

        // set limit
        $limit = ' LIMIT ' . $this->parser->getOffset() . ',' . $this->parser->getLimit();

        if (!empty($group_by)) {
            if ($cache = $this->cacheHelper->getCache(HashHelper::hash($select_count . $from . $where . $group_by))) {
                $total = $cache->numRows();
            } else {
                $temp = $db->query($select_count . $from . $where . $group_by);
                $this->cacheHelper->saveCache(HashHelper::hash($select_count . $from . $where . $group_by), $temp);
                $total = $temp->numRows();
            }
            if ($cache = $this->cacheHelper->getCache(HashHelper::hash($select_count . $from . $where . $where_or . $group_by))) {
                $filtered = $cache->numRows();
            } else {
                $temp = $db->query($select_count . $from . $where . $where_or . $group_by);
                $this->cacheHelper->saveCache(HashHelper::hash($select_count . $from . $where . $where_or . $group_by), $temp);
                $filtered = $temp->numRows();
            }
        } else {
            if ($cache = $this->cacheHelper->getCache(HashHelper::hash($select_count . $from . $where . $group_by))) {
                $total = $cache->fetch()['total'];
            } else {
                $temp = $db->query($select_count . $from . $where . $group_by);
                $this->cacheHelper->saveCache(HashHelper::hash($select_count . $from . $where . $group_by), $temp);
                $total = $temp->fetch()['total'];
            }
            if ($cache = $this->cacheHelper->getCache(HashHelper::hash($select_count . $from . $where . $where_or . $group_by))) {
                $filtered = $cache->fetch()['total'];
            } else {
                $temp = $db->query($select_count . $from . $where . $where_or . $group_by);
                $this->cacheHelper->saveCache(HashHelper::hash($select_count . $from . $where . $where_or . $group_by), $temp);
                $filtered = $temp->fetch()['total'];
            }
        }

        return $this->formResponse([
            'total' => $total,
            'filtered' => $filtered,
            'data' => $db->query($select . $from . $where . $where_or . $group_by . $order_by . $limit)->fetchAll(),
        ]);
    }

}
