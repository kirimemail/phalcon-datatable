<?php

namespace DataTables;

use Phalcon\Di;
use Phalcon\Mvc\User\Component;

class ParamsParser extends Component
{
    protected $params = [];
    protected $page = 1;

    public function __construct($limit)
    {
        $params = [
            'draw' => null,
            'start' => 1,
            'length' => $limit,
            'columns' => [],
            'search' => [],
            'order' => []
        ];
        $request = Di::getDefault()->get('request');
        $requestParams = $request->isPost() ? $request->getPost() : $request->getQuery();
        $this->params = (array) $requestParams + $params;
        $this->cleanData();
        $this->setPage();
    }

    public function getParams()
    {
        return $this->params;
    }

    public function setPage()
    {
        $this->page = (int) (floor($this->params['start'] / $this->params['length']) + 1);
    }

    public function getPage()
    {
        return $this->page;
    }

    public function getColumnsSearch()
    {
        return array_filter(array_map(function ($item) {
            return (isset($item['search']['value']) && strlen($item['search']['value'])) ? $item : null;
        }, $this->params['columns']));
    }

    public function getSearchableColumns()
    {
        return array_filter(array_map(function ($item) {
            return (isset($item['searchable']) && $item['searchable'] === "true") ? $item['data'] : null;
        }, $this->params['columns']));
    }

    public function getOrderableColumns()
    {
        return array_filter(array_map(function ($item) {
            return (isset($item['orderable']) && $item['orderable'] === "true") ? $item['data'] : null;
        }, $this->params['columns']));
    }

    public function getDraw()
    {
        return $this->params['draw'];
    }

    public function getLimit()
    {
        return $this->params['length'];
    }

    public function getOffset()
    {
        return $this->params['start'];
    }

    public function getColumns()
    {
        return $this->params['columns'];
    }

    public function getColumnById($id)
    {
        return isset($this->params['columns'][$id]['data']) ? $this->params['columns'][$id]['data'] : null;
    }


    public function getColumnByIdFull($id)
    {
        return $this->params['columns'][$id];
    }

    public function getSearch()
    {
        return $this->params['search'];
    }

    public function getOrder()
    {
        return $this->params['order'];
    }

    public function getSearchValue()
    {
        return isset($this->params['search']['value']) ? $this->params['search']['value'] : '';
    }

    private function cleanData()
    {
        $this->params = $this->cleanDataStringRecurse($this->params);
    }

    /**
     * Clean input data, recursively
     * @param $input
     * @param bool $cleanxss
     * @param bool $force_utf8
     * @return array|bool|mixed|null|string|string[]
     */
    public function cleanDataStringRecurse($input, $force_utf8 = false)
    {
        if (is_array($input)) {
            $output = [];
            foreach ($input as $key => $val) {
                if (is_array($val)) {
                    $output[$key] = $this->cleanDataStringRecurse($val, $force_utf8);
                } else {
                    if ($force_utf8) {
                        $output[$key] = utf8_encode($this->clean_input($val));
                    } else {
                        $output[$key] = $this->clean_input($val);
                    }
                }
            }

            return $output;
        } else {
            if ($force_utf8) {
                return utf8_encode($this->clean_input($input));
            } else {
                return $this->clean_input($input);
            }
        }
    }

    /**
     * Clean input
     * @param $input
     * @param bool $cleanxss
     * @param int $safe_level
     * @return mixed|null|string|string[]
     */
    private function clean_input($input, $safe_level = 0)
    {
        $output = $input;
        do {
            // Treat $input as buffer on each loop, faster than new var
            $input = $output;
            // Remove unwanted tags
            $output = $this->strip_tags($output);
            $output = $this->strip_encoded_entities($output);
            // Use 2nd input param if not empty or '0'
            if ($safe_level !== 0) {
                $output = $this->strip_base64($output);
            }
        } while ($output !== $input);

        return $output;
    }

    /**
     * Strip encoded char
     * @param $input
     * @return mixed|null|string|string[]
     */
    private function strip_encoded_entities($input)
    {
        // Fix &entity\n;
        $input = str_replace(array('&amp;', '&lt;', '&gt;'), array('&amp;amp;', '&amp;lt;', '&amp;gt;'), $input);
        $input = preg_replace('/(&#*\w+)[\x00-\x20]+;/u', '$1;', $input);
        $input = preg_replace('/(&#x*[0-9A-F]+);*/iu', '$1;', $input);
        $input = html_entity_decode($input, ENT_COMPAT, 'UTF-8');
        // Remove any attribute starting with "on" or xmlns
        $input = preg_replace('#(<[^>]+?[\x00-\x20"\'])(?:on|xmlns)[^>]*+[>\b]?#iu', '$1>', $input);
        // Remove javascript: and vbscript: protocols
        $input = preg_replace('#([a-z]*)[\x00-\x20]*=[\x00-\x20]*([`\'"]*)[\x00-\x20]*j[\x00-\x20]*a[\x00-\x20]*v[\x00-\x20]*a[\x00-\x20]*s[\x00-\x20]*c[\x00-\x20]*r[\x00-\x20]*i[\x00-\x20]*p[\x00-\x20]*t[\x00-\x20]*:#iu', '$1=$2nojavascript...', $input);
        $input = preg_replace('#([a-z]*)[\x00-\x20]*=([\'"]*)[\x00-\x20]*v[\x00-\x20]*b[\x00-\x20]*s[\x00-\x20]*c[\x00-\x20]*r[\x00-\x20]*i[\x00-\x20]*p[\x00-\x20]*t[\x00-\x20]*:#iu', '$1=$2novbscript...', $input);
        $input = preg_replace('#([a-z]*)[\x00-\x20]*=([\'"]*)[\x00-\x20]*-moz-binding[\x00-\x20]*:#u', '$1=$2nomozbinding...', $input);
        // Only works in IE: <span style="width: expression(alert('Ping!'));"></span>
        $input = preg_replace('#(<[^>]+?)style[\x00-\x20]*=[\x00-\x20]*[`\'"]*.*?expression[\x00-\x20]*\([^>]*+>#i', '$1>', $input);
        $input = preg_replace('#(<[^>]+?)style[\x00-\x20]*=[\x00-\x20]*[`\'"]*.*?behaviour[\x00-\x20]*\([^>]*+>#i', '$1>', $input);
        $input = preg_replace('#(<[^>]+?)style[\x00-\x20]*=[\x00-\x20]*[`\'"]*.*?s[\x00-\x20]*c[\x00-\x20]*r[\x00-\x20]*i[\x00-\x20]*p[\x00-\x20]*t[\x00-\x20]*:*[^>]*+>#iu', '$1>', $input);

        return $input;
    }

    /**
     * Remove dangerous tags
     * @param $input
     * @return null|string|string[]
     */
    private function strip_tags($input)
    {
        // Remove tags
        $input = preg_replace('#</*(?:applet|b(?:ase|gsound|link)|embed|frame(?:set)?|i(?:frame|layer)|l(?:ayer|ink)|meta|object|s(?:cript|tyle)|title|xml)[^>]*+>#i', '', $input);
        // Remove namespaced elements
        $input = preg_replace('#</*\w+:\w[^>]*+>#i', '', $input);

        return $input;
    }

    /**
     * Remove base64 dangerous tags
     * @param $input
     * @return string
     */
    private function strip_base64($input)
    {
        $decoded = base64_decode($input);
        $decoded = $this->strip_tags($decoded);
        $decoded = $this->strip_encoded_entities($decoded);
        $output = base64_encode($decoded);

        return $output;
    }
}