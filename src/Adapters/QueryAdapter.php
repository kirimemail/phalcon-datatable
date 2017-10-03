<?php

namespace DataTables\Adapters;

class QueryAdapter extends AdapterInterface
{

	protected $query = [];
	protected $column = [];
	protected $global = [];
	protected $order = [];

	public function setQuery(array $query)
	{
		$this->query = $query;
	}

	public function getResponse()
	{
		// init
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
		$order = $this->parser->getOrder();
		if ($this->parser->getColumns()[$order[0]['column']]['orderable'] == 'true') {
			$order_by = ' ORDER BY ' . $this->parser->getColumns()[$order[0]['column']]['name'] . ' ' . $order[0]['dir'];
		}

		// set limit
		$limit = ' LIMIT ' . $this->parser->getOffset() . ',' . $this->parser->getLimit();

		if (!empty($group_by)) {
			$total = $db->query($select_count . $from . $where . $group_by)->numRows();
			$filtered = $db->query($select_count . $from . $where . $where_or . $group_by)->numRows();
		} else {
			$total = $db->query($select_count . $from . $where . $group_by)->fetch()['total'];
			$filtered = $db->query($select_count . $from . $where . $where_or . $group_by)->fetch()['total'];
		}

		return $this->formResponse([
					'total' => $total,
					'filtered' => $filtered,
					'data' => $db->query($select . $from . $where . $where_or . $group_by . $order_by . $limit)->fetchAll(),
		]);
	}

}
